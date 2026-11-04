<?php
/**
 * Copyright (c) Iurii Tkachenko. Licensed under OSL-3.0, see LICENSE.
 */
declare(strict_types=1);

namespace Normaro\Verifactu\Model\Config\Source;

use Magento\Framework\Data\OptionSourceInterface;

/**
 * Quaderno tax codes used for Spanish IVA lines.
 */
class TaxCode implements OptionSourceInterface
{
    /**
     * Options for the config select.
     *
     * @return array<int,array{value:string,label:\Magento\Framework\Phrase}>
     */
    public function toOptionArray(): array
    {
        return [
            ['value' => 'standard', 'label' => __('Standard')],
            ['value' => 'reduced', 'label' => __('Reduced (10 % and 4 %)')],
            ['value' => 'exempt', 'label' => __('Exempt (0 %)')],
        ];
    }
}
