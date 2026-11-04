<?php
/**
 * Copyright (c) Iurii Tkachenko. Licensed under OSL-3.0, see LICENSE.
 */
declare(strict_types=1);

namespace Normaro\Verifactu\Test\Unit\Model;

use Magento\Backend\Model\UrlInterface;
use Magento\Framework\Notification\NotifierInterface;
use Normaro\Verifactu\Model\AdminNotifier;
use Normaro\Verifactu\Model\Document;
use PHPUnit\Framework\TestCase;

class AdminNotifierTest extends TestCase
{
    /**
     * Blocked: "... not sent (blocked)", the reason, and Read Details to the document without a secret key.
     *
     * @return void
     */
    public function testBlockedNotificationLinksToTheDocument(): void
    {
        $document = $this->createPartialMock(Document::class, ['getMagentoLabel', 'getId']);
        $document->method('getMagentoLabel')->willReturn('invoice 000000020');
        $document->method('getId')->willReturn(17);
        $document->setData(['status' => Document::STATUS_BLOCKED, 'last_error' => 'NIF required: ...']);

        $url = $this->createMock(UrlInterface::class);
        $url->expects($this->once())->method('getUrl')
            ->with('verifactu/document/open', ['id' => 17, '_nosecret' => true])
            ->willReturn('https://shop/admin/verifactu/document/open/id/17/');
        $notifier = $this->createMock(NotifierInterface::class);
        $notifier->expects($this->once())->method('addMajor')->with(
            'Verifactu: invoice 000000020 not sent (blocked)',
            'NIF required: ...',
            'https://shop/admin/verifactu/document/open/id/17/'
        );

        (new AdminNotifier($notifier, $url))->notify($document);
    }
}
