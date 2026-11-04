<?php
/**
 * Copyright (c) Iurii Tkachenko. Licensed under OSL-3.0, see LICENSE.
 */
declare(strict_types=1);

namespace Normaro\Verifactu\Plugin;

use Magento\Sales\Block\Adminhtml\Order\View\Info;

/**
 * Renders the Verifactu block right after the "Invoice / Credit Memo Information" section.
 *
 * The core form template prints that section and then its child "order_info"; the Verifactu block is
 * declared (only) in the invoice and credit memo view layouts as a child of the form, so it is put in
 * front of "order_info" there. The order view has no such block and is not affected.
 */
class AdminDocumentBlockPosition
{
    private const BLOCK = 'verifactu.document.info';

    /**
     * Prepend the Verifactu block to the order information of the invoice / credit memo page.
     *
     * @param Info $subject
     * @param string $result
     * @return string
     */
    public function afterToHtml(Info $subject, $result)
    {
        if ($subject->getNameInLayout() !== 'order_info') {
            return $result;
        }
        $block = $subject->getLayout()->getBlock(self::BLOCK);
        return $block ? $block->toHtml() . $result : $result;
    }
}
