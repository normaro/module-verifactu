<?php
/**
 * Copyright (c) Iurii Tkachenko. Licensed under OSL-3.0, see LICENSE.
 */
declare(strict_types=1);

namespace Normaro\Verifactu\Model\Mapper;

use Magento\Catalog\Model\ResourceModel\Product as ProductResource;

/**
 * Product tax class of an order line (order items do not store it).
 */
class TaxClassResolver
{
    /**
     * @param ProductResource $productResource
     */
    public function __construct(private readonly ProductResource $productResource)
    {
    }

    /**
     * Tax class ID of the product, or null if the product no longer exists.
     *
     * @param int $productId
     * @param int $storeId
     * @return int|null
     */
    public function forProduct(int $productId, int $storeId): ?int
    {
        if ($productId <= 0) {
            return null;
        }
        $value = $this->productResource->getAttributeRawValue($productId, 'tax_class_id', $storeId);
        if ($value === false || is_array($value)) {
            return null;
        }
        return (int)$value;
    }
}
