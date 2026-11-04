<?php
/**
 * Copyright (c) Iurii Tkachenko. Licensed under OSL-3.0, see LICENSE.
 */
declare(strict_types=1);

namespace Normaro\Verifactu\Model\Provider;

/**
 * Provider-neutral document built from a Magento invoice or credit memo.
 */
class DocumentData
{
    public const TRANSACTION_SALE = 'sale';
    public const TRANSACTION_REFUND = 'refund';

    /**
     * @param string $transactionType sale or refund
     * @param string $processorId invoice increment ID; for a refund the original's processor ID
     * @param string $currency
     * @param CustomerData $customer
     * @param LineData[] $lines
     * @param float $grandTotal Magento grand total of the invoice / credit memo
     * @param int $storeId
     * @param string $paymentMethod provider payment method
     * @param string|null $poNumber order increment ID
     * @param int|null $originalCreatedAt refund: unix time the original's row was created (credit lookup bound)
     * @SuppressWarnings(PHPMD.ExcessiveParameterList)
     */
    public function __construct(
        public readonly string $transactionType,
        public readonly string $processorId,
        public readonly string $currency,
        public readonly CustomerData $customer,
        public readonly array $lines,
        public readonly float $grandTotal,
        public readonly int $storeId,
        public readonly string $paymentMethod = 'other',
        public readonly ?string $poNumber = null,
        public readonly ?int $originalCreatedAt = null
    ) {
    }

    /**
     * Sum of line amounts, rounded to cents.
     *
     * @return float
     */
    public function linesTotal(): float
    {
        $sum = 0.0;
        foreach ($this->lines as $line) {
            $sum += $line->amount;
        }
        return round($sum, 2);
    }
}
