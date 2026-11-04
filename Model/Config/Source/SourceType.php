<?php
/**
 * Copyright (c) Iurii Tkachenko. Licensed under OSL-3.0, see LICENSE.
 */
declare(strict_types=1);

namespace Normaro\Verifactu\Model\Config\Source;

use Magento\Framework\Data\OptionSourceInterface;
use Normaro\Verifactu\Model\Document;

/**
 * Document statuses for grids.
 */
class SourceType implements OptionSourceInterface
{
    /**
     * Magento document types for the grid.
     *
     * @return array<int,array{value:string,label:string}>
     */
    public function toOptionArray(): array
    {
        $options = [];
        foreach (Document::SOURCE_LABELS as $value => $label) {
            $options[] = ['value' => $value, 'label' => $label];
        }
        return $options;
    }
}
