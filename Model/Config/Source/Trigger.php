<?php
/**
 * Copyright (c) Iurii Tkachenko. Licensed under OSL-3.0, see LICENSE.
 */
declare(strict_types=1);

namespace Normaro\Verifactu\Model\Config\Source;

use Magento\Framework\Data\OptionSourceInterface;
use Normaro\Verifactu\Model\Config;

/**
 * When an invoice is sent.
 */
class Trigger implements OptionSourceInterface
{
    /**
     * Options for the config select.
     *
     * @return array<int,array{value:string,label:\Magento\Framework\Phrase}>
     */
    public function toOptionArray(): array
    {
        return [
            ['value' => Config::TRIGGER_CAPTURE, 'label' => __('On capture (paid invoices only)')],
            ['value' => Config::TRIGGER_INVOICE, 'label' => __('On every new invoice')],
        ];
    }
}
