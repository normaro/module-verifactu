<?php
/**
 * Copyright (c) Iurii Tkachenko. Licensed under OSL-3.0, see LICENSE.
 */
declare(strict_types=1);

namespace Normaro\Verifactu\Test\Unit\Block\Adminhtml\System\Config;

use Magento\Backend\Block\Context;
use Magento\Framework\Data\Form\Element\AbstractElement;
use Magento\Framework\Escaper;
use Normaro\Verifactu\Block\Adminhtml\System\Config\EmailNotice;
use PHPUnit\Framework\TestCase;

class EmailNoticeTest extends TestCase
{
    /**
     * A warning message that names the setting to change.
     *
     * @return void
     */
    public function testWarnsAboutMagentoInvoiceEmails(): void
    {
        $escaper = $this->createMock(Escaper::class);
        $escaper->method('escapeHtml')->willReturnCallback(static fn ($s) => (string)$s);
        $notice = new EmailNotice($this->createMock(Context::class), $escaper);

        $html = $notice->render($this->createMock(AbstractElement::class));

        $this->assertStringContainsString('message-warning', $html);
        $this->assertStringContainsString('Sales Emails (Invoice, Credit Memo)', $html);
    }
}
