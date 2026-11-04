<?php
/**
 * Copyright (c) Iurii Tkachenko. Licensed under OSL-3.0, see LICENSE.
 */
declare(strict_types=1);

namespace Normaro\Verifactu\Model\Config\Source;

use Magento\Framework\Data\OptionSourceInterface;
use Normaro\Verifactu\Model\Document;

/**
 * Provider document types (Invoice, Simplified invoice, Credit note) for grids.
 */
class DocumentType implements OptionSourceInterface
{
    /**
     * Options with translated labels.
     *
     * @return array<int,array{value:string,label:\Magento\Framework\Phrase}>
     */
    public function toOptionArray(): array
    {
        $options = [];
        foreach (Document::TYPE_LABELS as $value => $label) {
            $options[] = ['value' => $value, 'label' => __($label)];
        }
        return $options;
    }
}
