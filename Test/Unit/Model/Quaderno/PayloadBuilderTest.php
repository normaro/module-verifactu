<?php
/**
 * Copyright (c) Iurii Tkachenko. Licensed under OSL-3.0, see LICENSE.
 */
declare(strict_types=1);

namespace Normaro\Verifactu\Test\Unit\Model\Quaderno;

use PHPUnit\Framework\TestCase;
use Normaro\Verifactu\Model\Provider\CustomerData;
use Normaro\Verifactu\Model\Provider\DocumentData;
use Normaro\Verifactu\Model\Provider\LineData;
use Normaro\Verifactu\Model\Quaderno\PayloadBuilder;

/**
 * POST /transactions bodies for the main Spanish tax cases.
 */
class PayloadBuilderTest extends TestCase
{
    /**
     * @var PayloadBuilder
     */
    private PayloadBuilder $builder;

    /**
     * @inheritdoc
     */
    protected function setUp(): void
    {
        $this->builder = new PayloadBuilder();
    }

    /**
     * Spanish B2B with NIF, two IVA rates and shipping.
     *
     * @return void
     */
    public function testSpanishCustomerWithNif(): void
    {
        $body = $this->builder->build($this->document(
            new CustomerData(
                'person',
                'Autonomo',
                'Prueba',
                'ES',
                '12345678Z',
                'Calle Prueba 1',
                null,
                'Alicante',
                '03001'
            ),
            [
                new LineData('Camiseta', 48.40, 21.0, 'standard', 2.0, 'VF-TSHIRT'),
                new LineData('Producto 10 %', 11.00, 10.0, 'reduced', 1.0, 'VF-REDUCED'),
                new LineData('Shipping', 4.84, 21.0, 'standard'),
            ],
            64.24
        ));

        $this->assertSame('sale', $body['type']);
        $this->assertSame('000000001', $body['processor_id']);
        $this->assertSame('100000001', $body['po_number']);
        $this->assertSame('12345678Z', $body['customer']['tax_id']);
        $this->assertSame('person', $body['customer']['kind']);
        $this->assertSame('Calle Prueba 1', $body['customer']['street_line_1']);
        $this->assertSame('03001', $body['customer']['postal_code']);
        $this->assertSame(
            ['country' => 'ES', 'name' => 'IVA', 'rate' => 10.0, 'tax_code' => 'reduced'],
            $body['items'][1]['tax']
        );
        $this->assertSame(48.40, $body['items'][0]['amount']);
        $this->assertSame('VF-TSHIRT', $body['items'][0]['product_code']);
        $this->assertNoDates($body);
    }

    /**
     * B2C without NIF below the threshold: no tax_id, Quaderno decides receipt.
     *
     * @return void
     */
    public function testConsumerWithoutNif(): void
    {
        $body = $this->builder->build($this->document(
            new CustomerData('person', 'Cliente', 'Peninsula', 'ES', null, 'Calle Mayor 10', null, 'Madrid', '28013'),
            [new LineData('Taza', 24.20, 21.0, 'standard', 2.0, 'VF-MUG')],
            24.20
        ));

        $this->assertArrayNotHasKey('tax_id', $body['customer']);
        $this->assertSame('ES', $body['evidence']['billing_country']);
        $this->assertNoDates($body);
    }

    /**
     * Canarias: customer data always sent, 0 % exempt lines.
     *
     * @return void
     */
    public function testCanariasExempt(): void
    {
        $body = $this->builder->build($this->document(
            new CustomerData(
                'person',
                'Cliente',
                'Canarias',
                'ES',
                null,
                'Calle Triana 5',
                null,
                'Las Palmas',
                '35002'
            ),
            [
                new LineData('Camiseta', 20.00, 0.0, 'exempt', 1.0, 'VF-TSHIRT'),
                new LineData('Shipping', 4.00, 0.0, 'exempt'),
            ],
            24.00
        ));

        $this->assertSame('35002', $body['customer']['postal_code']);
        $this->assertSame('Calle Triana 5', $body['customer']['street_line_1']);
        foreach ($body['items'] as $item) {
            $this->assertSame(0.0, $item['tax']['rate']);
            $this->assertSame('exempt', $item['tax']['tax_code']);
        }
    }

    /**
     * EU B2B with VAT number: company, foreign tax ID, 0 % exempt.
     *
     * @return void
     */
    public function testEuBusiness(): void
    {
        $body = $this->builder->build($this->document(
            new CustomerData(
                'company',
                'Testfirma GmbH',
                '',
                'DE',
                'DE123456788',
                'Teststrasse 1',
                null,
                'Berlin',
                '10115'
            ),
            [new LineData('Mochila', 100.00, 0.0, 'exempt', 2.0, 'VF-BACKPACK')],
            100.00
        ));

        $this->assertSame('company', $body['customer']['kind']);
        $this->assertSame('Testfirma GmbH', $body['customer']['first_name']);
        $this->assertArrayNotHasKey('last_name', $body['customer']);
        $this->assertSame('DE123456788', $body['customer']['tax_id']);
        $this->assertSame('DE', $body['evidence']['billing_country']);
        $this->assertSame('exempt', $body['items'][0]['tax']['tax_code']);
    }

    /**
     * Shipping is a line of the same document, with its own IVA.
     *
     * @return void
     */
    public function testShippingLine(): void
    {
        $body = $this->builder->build($this->document(
            new CustomerData('person', 'Cliente', 'Peninsula', 'ES'),
            [
                new LineData('Camiseta', 24.20, 21.0, 'standard', 1.0, 'VF-TSHIRT'),
                new LineData('Mochila', 60.50, 21.0, 'standard', 1.0, 'VF-BACKPACK'),
                new LineData('Shipping', 4.84, 21.0, 'standard'),
            ],
            89.54
        ));

        $this->assertCount(3, $body['items']);
        $this->assertSame('Shipping', $body['items'][2]['description']);
        $this->assertSame(4.84, $body['items'][2]['amount']);
        $this->assertArrayNotHasKey('product_code', $body['items'][2]);
    }

    /**
     * Full refund: type refund, the original's processor ID, only the original's contact.
     *
     * @return void
     */
    public function testRefundLinkedByProcessorIdAndContact(): void
    {
        $data = new DocumentData(
            DocumentData::TRANSACTION_REFUND,
            '000000002',
            'EUR',
            new CustomerData(
                'person',
                'Cliente',
                'Peninsula',
                'ES',
                null,
                null,
                null,
                null,
                null,
                null,
                null,
                '9795037'
            ),
            [new LineData('Taza', 24.20, 21.0, 'standard', 2.0, 'VF-MUG')],
            24.20,
            1,
            'check',
            '100000002'
        );
        $body = $this->builder->build($data);

        $this->assertSame('refund', $body['type']);
        $this->assertSame('000000002', $body['processor_id']);
        $this->assertSame(['id' => '9795037'], $body['customer']);
        $this->assertArrayNotHasKey('evidence', $body);
        $this->assertSame(24.20, $body['items'][0]['amount']);
        $this->assertNoDates($body);
    }

    /**
     * Sale document with the given customer and lines.
     *
     * @param CustomerData $customer
     * @param LineData[] $lines
     * @param float $grandTotal
     * @return DocumentData
     */
    private function document(CustomerData $customer, array $lines, float $grandTotal): DocumentData
    {
        return new DocumentData(
            DocumentData::TRANSACTION_SALE,
            '000000001',
            'EUR',
            $customer,
            $lines,
            $grandTotal,
            1,
            'check',
            '100000001'
        );
    }

    /**
     * The module never sends a date (the Quaderno sandbox accepts a past date).
     *
     * @param array<string,mixed> $body
     * @return void
     */
    private function assertNoDates(array $body): void
    {
        $this->assertArrayNotHasKey('date', $body);
        $this->assertArrayNotHasKey('issue_date', $body);
    }
}
