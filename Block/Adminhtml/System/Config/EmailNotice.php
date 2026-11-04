<?php
/**
 * Copyright (c) Iurii Tkachenko. Licensed under OSL-3.0, see LICENSE.
 */
declare(strict_types=1);

namespace Normaro\Verifactu\Block\Adminhtml\System\Config;

use Magento\Backend\Block\AbstractBlock;
use Magento\Backend\Block\Context;
use Magento\Framework\Data\Form\Element\AbstractElement;
use Magento\Framework\Data\Form\Element\Renderer\RendererInterface;
use Magento\Framework\Escaper;

/**
 * Warning at the top of the Verifactu config section: Magento's own invoice / credit memo emails.
 */
class EmailNotice extends AbstractBlock implements RendererInterface
{
    /**
     * @param Context $context
     * @param Escaper $escaper
     * @param mixed[] $data
     */
    public function __construct(
        Context $context,
        private readonly Escaper $escaper,
        array $data = []
    ) {
        parent::__construct($context, $data);
    }

    /**
     * @inheritdoc
     */
    public function render(AbstractElement $element)
    {
        $text = (string)__(
            'Magento\'s own invoice and credit memo emails show the Magento number, not the Verifactu invoice,'
            . ' and are sent before Quaderno issues it. The legal invoice is the one Quaderno issues (PDF and'
            . ' AEAT link in the customer account). Disable these emails in Stores > Configuration > Sales >'
            . ' Sales Emails (Invoice, Credit Memo), or change their templates so they do not look like an invoice.'
        );
        return '<div class="message message-warning verifactu-email-notice">'
            . $this->escaper->escapeHtml($text) . '</div>';
    }
}
