<?php
/**
 * Copyright (c) Iurii Tkachenko. Licensed under OSL-3.0, see LICENSE.
 */
declare(strict_types=1);

namespace Normaro\Verifactu\Test\Unit\Model\Log;

use PHPUnit\Framework\TestCase;
use Normaro\Verifactu\Model\Log\Masker;

/**
 * Tax IDs never reach the log in clear.
 */
class MaskerTest extends TestCase
{
    /**
     * @var Masker
     */
    private Masker $masker;

    /**
     * @inheritdoc
     */
    protected function setUp(): void
    {
        $this->masker = new Masker();
    }

    /**
     * DNI, NIE, entity NIF, ES-prefixed NIF and EU VAT numbers are masked in free text.
     *
     * @dataProvider taxIds
     * @param string $taxId
     * @param string $masked
     * @return void
     */
    public function testTaxIdsInTextAreMasked(string $taxId, string $masked): void
    {
        $this->assertSame('NIF ' . $masked . ' is invalid', $this->masker->maskString('NIF ' . $taxId . ' is invalid'));
    }

    /**
     * Tax IDs and their masked form.
     *
     * @return array<string,array{string,string}>
     */
    public static function taxIds(): array
    {
        return [
            'DNI' => ['12345678Z', '******78Z'],
            'NIE' => ['X1234567L', '******67L'],
            'entity NIF' => ['B12345674', '******674'],
            'ES prefix' => ['ES12345678Z', '********78Z'],
            'DE VAT' => ['DE123456788', '********788'],
            'FR VAT' => ['FRXX999999999', '**********999'],
        ];
    }

    /**
     * Inside a JSON request body.
     *
     * @return void
     */
    public function testJsonBodyIsMasked(): void
    {
        $json = '{"customer":{"tax_id":"12345678Z","first_name":"Ana"},"processor_id":"000000010"}';

        $this->assertSame(
            '{"customer":{"tax_id":"******78Z","first_name":"Ana"},"processor_id":"000000010"}',
            $this->masker->maskString($json)
        );
    }

    /**
     * Tax-ID keys are masked whatever the value looks like; nested arrays too.
     *
     * @return void
     */
    public function testTaxIdKeysAreMaskedAtAnyDepth(): void
    {
        $masked = $this->masker->maskArray([
            'request' => ['body' => ['customer' => ['tax_id' => 'ABC-123', 'city' => 'Alicante']]],
            'vat_number' => 'GB123',
        ]);

        $this->assertSame('****123', $masked['request']['body']['customer']['tax_id']);
        $this->assertSame('Alicante', $masked['request']['body']['customer']['city']);
        $this->assertSame('**123', $masked['vat_number']);
    }

    /**
     * Identifiers that are not tax IDs stay readable.
     *
     * @return void
     */
    public function testOtherIdentifiersStay(): void
    {
        $text = 'Receipt FS2026-00014 row 000000011_1793731749 x-request-id 229bacc2-0edc-4b43-a299-87c628296fc6'
            . ' contact 9795222 order 100000012 total 3029.84';

        $this->assertSame($text, $this->masker->maskString($text));
    }
}
