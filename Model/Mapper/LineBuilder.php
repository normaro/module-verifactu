<?php
/**
 * Copyright (c) Iurii Tkachenko. Licensed under OSL-3.0, see LICENSE.
 */
declare(strict_types=1);

namespace Normaro\Verifactu\Model\Mapper;

use Magento\Sales\Model\Order;
use Magento\Sales\Model\Order\Creditmemo;
use Magento\Sales\Model\Order\Invoice;
use Normaro\Verifactu\Model\Config;
use Normaro\Verifactu\Model\Provider\LineData;

/**
 * Lines of an invoice or credit memo (tax-inclusive amount after discount,
 * shipping as its own line, IVA always explicit).
 *
 * The IVA rate sent is the rate Magento charged on the line (order item tax_percent), so 0 %
 * destinations (Canarias, Ceuta, Melilla, EU B2B, export) go out as 0 % "exempt". The mapping
 * gives the Quaderno tax code and must exist for every tax class.
 */
class LineBuilder
{
    private const EXEMPT = 'exempt';

    /**
     * @param Config $config
     * @param TaxClassResolver $taxClassResolver
     */
    public function __construct(
        private readonly Config $config,
        private readonly TaxClassResolver $taxClassResolver
    ) {
    }

    /**
     * Product lines and the shipping line.
     *
     * @param Invoice|Creditmemo $source
     * @param Order $order
     * @return LineData[]
     * @throws MappingException
     */
    public function build(Invoice|Creditmemo $source, Order $order): array
    {
        $storeId = (int)$order->getStoreId();
        $mapping = $this->config->getTaxMapping($storeId);
        $lines = [];
        $itemDiscounts = 0.0;
        foreach ($source->getAllItems() as $item) {
            $orderItem = $item->getOrderItem();
            if ((float)$item->getQty() <= 0 || $orderItem === null) {
                continue;
            }
            $parent = $orderItem->getParentItem();
            if ($parent !== null && $parent->getProductType() !== 'bundle') {
                continue; // configurable child: the parent line carries the price
            }
            $discount = (float)$item->getDiscountAmount();
            $amount = round((float)$item->getRowTotalInclTax() - $discount, 2);
            if ($amount == 0.0) {
                continue; // e.g. a dynamic-price bundle parent; its children carry the amounts
            }
            $itemDiscounts += $discount;
            $rate = (float)$orderItem->getTaxPercent();
            $taxClassId = $this->taxClassResolver->forProduct((int)$orderItem->getProductId(), $storeId);
            $lines[] = new LineData(
                (string)$item->getName(),
                $amount,
                $rate,
                $this->taxCode($taxClassId, $rate, $mapping, (string)$item->getSku()),
                (float)$item->getQty(),
                (string)$item->getSku()
            );
        }

        $shipping = $this->shippingLine($source, $order, $itemDiscounts, $mapping);
        if ($shipping !== null) {
            $lines[] = $shipping;
        }
        return $lines;
    }

    /**
     * Shipping line; its discount is the document discount minus the item discounts.
     *
     * @param Invoice|Creditmemo $source
     * @param Order $order
     * @param float $itemDiscounts
     * @param array<int,array{rate:float,code:string}> $mapping
     * @return LineData|null
     * @throws MappingException
     */
    private function shippingLine(
        Invoice|Creditmemo $source,
        Order $order,
        float $itemDiscounts,
        array $mapping
    ): ?LineData {
        $shippingInclTax = (float)$source->getShippingInclTax();
        if ($shippingInclTax <= 0.0) {
            return null;
        }
        $shippingDiscount = max(0.0, round(abs((float)$source->getDiscountAmount()) - $itemDiscounts, 2));
        $shippingAmount = (float)$order->getShippingAmount();
        $rate = $shippingAmount > 0.0
            ? round((float)$order->getShippingTaxAmount() / $shippingAmount * 100, 1)
            : 0.0;
        $classId = $this->config->getShippingTaxClassId((int)$order->getStoreId());
        return new LineData(
            (string)__('Shipping'),
            round($shippingInclTax - $shippingDiscount, 2),
            $rate,
            $this->taxCode($classId ?: null, $rate, $mapping, 'shipping')
        );
    }

    /**
     * Quaderno tax code; 0 % lines are "exempt".
     *
     * @param int|null $taxClassId
     * @param float $rate
     * @param array<int,array{rate:float,code:string}> $mapping
     * @param string $what for the error message
     * @return string
     * @throws MappingException
     */
    private function taxCode(?int $taxClassId, float $rate, array $mapping, string $what): string
    {
        if ($taxClassId === null || !isset($mapping[$taxClassId])) {
            throw new MappingException(__(
                'No IVA mapping for the tax class of "%1" (class ID %2). Add it in Stores > Configuration'
                . ' > Sales > Verifactu.',
                $what,
                $taxClassId ?? 'none'
            ));
        }
        return $rate == 0.0 ? self::EXEMPT : $mapping[$taxClassId]['code'];
    }
}
