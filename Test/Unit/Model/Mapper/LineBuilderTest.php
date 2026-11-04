<?php
/**
 * Copyright (c) Iurii Tkachenko. Licensed under OSL-3.0, see LICENSE.
 */
declare(strict_types=1);

namespace Normaro\Verifactu\Test\Unit\Model\Mapper;

use Magento\Sales\Model\Order;
use Magento\Sales\Model\Order\Invoice;
use Magento\Sales\Model\Order\Invoice\Item as InvoiceItem;
use Magento\Sales\Model\Order\Item as OrderItem;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Normaro\Verifactu\Model\Config;
use Normaro\Verifactu\Model\Mapper\LineBuilder;
use Normaro\Verifactu\Model\Mapper\MappingException;
use Normaro\Verifactu\Model\Mapper\TaxClassResolver;

/**
 * Lines from Magento invoices: two rates and shipping, Canarias at 0 %, a partial invoice.
 */
class LineBuilderTest extends TestCase
{
    private const TAXABLE = 2;
    private const REDUCED = 4;

    /**
     * @var Config&MockObject
     */
    private Config $config;

    /**
     * @var LineBuilder
     */
    private LineBuilder $builder;

    /**
     * @inheritdoc
     */
    protected function setUp(): void
    {
        $this->config = $this->createMock(Config::class);
        $this->config->method('getTaxMapping')->willReturn([
            self::TAXABLE => ['rate' => 21.0, 'code' => 'standard'],
            self::REDUCED => ['rate' => 10.0, 'code' => 'reduced'],
        ]);
        $this->config->method('getShippingTaxClassId')->willReturn(self::TAXABLE);
        $resolver = $this->createMock(TaxClassResolver::class);
        $resolver->method('forProduct')->willReturnCallback(
            static fn (int $productId): int => $productId === 4 ? self::REDUCED : self::TAXABLE
        );
        $this->builder = new LineBuilder($this->config, $resolver);
    }

    /**
     * 2 x 24.20 at 21 %, 11.00 at 10 %, shipping 4.84 at 21 %; total 64.24.
     *
     * @return void
     */
    public function testTwoRatesAndShipping(): void
    {
        $order = $this->order(4.00, 0.84);
        $invoice = $this->invoice([
            $this->item('Camiseta', 'VF-TSHIRT', 2, 48.40, 21.0, 1),
            $this->item('Producto 10 %', 'VF-REDUCED', 1, 11.00, 10.0, 4),
        ], 4.84);

        $lines = $this->builder->build($invoice, $order);

        $this->assertCount(3, $lines);
        $this->assertSame([48.40, 21.0, 'standard'], [$lines[0]->amount, $lines[0]->taxRate, $lines[0]->taxCode]);
        $this->assertSame([11.00, 10.0, 'reduced'], [$lines[1]->amount, $lines[1]->taxRate, $lines[1]->taxCode]);
        $this->assertSame([4.84, 21.0, 'standard'], [$lines[2]->amount, $lines[2]->taxRate, $lines[2]->taxCode]);
        $this->assertSame(64.24, round($lines[0]->amount + $lines[1]->amount + $lines[2]->amount, 2));
    }

    /**
     * Canarias, Magento charged 0 %: lines go out as 0 % "exempt".
     *
     * @return void
     */
    public function testCanariasZeroRateIsExempt(): void
    {
        $order = $this->order(4.00, 0.00);
        $invoice = $this->invoice([
            $this->item('Camiseta', 'VF-TSHIRT', 1, 20.00, 0.0, 1),
            $this->item('Taza', 'VF-MUG', 1, 10.00, 0.0, 2),
        ], 4.00);

        $lines = $this->builder->build($invoice, $order);

        $this->assertCount(3, $lines);
        foreach ($lines as $line) {
            $this->assertSame(0.0, $line->taxRate);
            $this->assertSame('exempt', $line->taxCode);
        }
    }

    /**
     * Second partial invoice has no shipping line.
     *
     * @return void
     */
    public function testPartialInvoiceWithoutShipping(): void
    {
        $lines = $this->builder->build(
            $this->invoice([$this->item('Mochila', 'VF-BACKPACK', 1, 60.50, 21.0, 3)], 0.0),
            $this->order(4.00, 0.84)
        );

        $this->assertCount(1, $lines);
        $this->assertSame(60.50, $lines[0]->amount);
    }

    /**
     * A tax class without mapping blocks the document.
     *
     * @return void
     */
    public function testMissingMappingThrows(): void
    {
        $config = $this->createMock(Config::class);
        $config->method('getTaxMapping')->willReturn([]);
        $resolver = $this->createMock(TaxClassResolver::class);
        $resolver->method('forProduct')->willReturn(self::TAXABLE);
        $builder = new LineBuilder($config, $resolver);

        $this->expectException(MappingException::class);
        $builder->build(
            $this->invoice([$this->item('Camiseta', 'VF-TSHIRT', 1, 24.20, 21.0, 1)], 0.0),
            $this->order(0.0, 0.0)
        );
    }

    /**
     * Order with shipping net amount and shipping tax.
     *
     * @param float $shippingAmount
     * @param float $shippingTax
     * @return Order&MockObject
     */
    private function order(float $shippingAmount, float $shippingTax): Order
    {
        $order = $this->createMock(Order::class);
        $order->method('getStoreId')->willReturn(1);
        $order->method('getShippingAmount')->willReturn($shippingAmount);
        $order->method('getShippingTaxAmount')->willReturn($shippingTax);
        return $order;
    }

    /**
     * Invoice with items and shipping incl. IVA, no discount.
     *
     * @param InvoiceItem[] $items
     * @param float $shippingInclTax
     * @return Invoice&MockObject
     */
    private function invoice(array $items, float $shippingInclTax): Invoice
    {
        $invoice = $this->createMock(Invoice::class);
        $invoice->method('getAllItems')->willReturn($items);
        $invoice->method('getShippingInclTax')->willReturn($shippingInclTax);
        $invoice->method('getDiscountAmount')->willReturn(0.0);
        return $invoice;
    }

    /**
     * Invoice item with its order item.
     *
     * @param string $name
     * @param string $sku
     * @param float $qty
     * @param float $rowTotalInclTax
     * @param float $taxPercent
     * @param int $productId
     * @return InvoiceItem&MockObject
     */
    private function item(
        string $name,
        string $sku,
        float $qty,
        float $rowTotalInclTax,
        float $taxPercent,
        int $productId
    ): InvoiceItem {
        $orderItem = $this->createMock(OrderItem::class);
        $orderItem->method('getParentItem')->willReturn(null);
        $orderItem->method('getTaxPercent')->willReturn($taxPercent);
        $orderItem->method('getProductId')->willReturn($productId);
        $item = $this->createMock(InvoiceItem::class);
        $item->method('getOrderItem')->willReturn($orderItem);
        $item->method('getQty')->willReturn($qty);
        $item->method('getRowTotalInclTax')->willReturn($rowTotalInclTax);
        $item->method('getDiscountAmount')->willReturn(0.0);
        $item->method('getName')->willReturn($name);
        $item->method('getSku')->willReturn($sku);
        return $item;
    }
}
