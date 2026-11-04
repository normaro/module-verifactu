<?php
/**
 * Copyright (c) Iurii Tkachenko. Licensed under OSL-3.0, see LICENSE.
 */
declare(strict_types=1);

namespace Normaro\Verifactu\Model\Provider;

/**
 * One document line: amount is the line total including IVA, after discount.
 */
class LineData
{
    /**
     * @param string $description
     * @param float $amount line total, IVA included, after discount
     * @param float $taxRate IVA rate in percent
     * @param string $taxCode provider tax code (standard, reduced, exempt)
     * @param float $quantity
     * @param string|null $sku
     */
    public function __construct(
        public readonly string $description,
        public readonly float $amount,
        public readonly float $taxRate,
        public readonly string $taxCode,
        public readonly float $quantity = 1.0,
        public readonly ?string $sku = null
    ) {
    }
}
