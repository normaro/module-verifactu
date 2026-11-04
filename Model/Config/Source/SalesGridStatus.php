<?php
/**
 * Copyright (c) Iurii Tkachenko. Licensed under OSL-3.0, see LICENSE.
 */
declare(strict_types=1);

namespace Normaro\Verifactu\Model\Config\Source;

use Normaro\Verifactu\Plugin\SalesGridVerifactuColumns;

/**
 * Verifactu statuses for the invoice / credit memo grids, plus "Not sent" for documents without a row.
 */
class SalesGridStatus extends Status
{
    /**
     * Statuses and "Not sent".
     *
     * @return array<int,array{value:string,label:\Magento\Framework\Phrase}>
     */
    public function toOptionArray(): array
    {
        return array_merge(
            parent::toOptionArray(),
            [['value' => SalesGridVerifactuColumns::NOT_SENT, 'label' => __('Not sent')]]
        );
    }
}
