<?php
/**
 * Copyright (c) Iurii Tkachenko. Licensed under OSL-3.0, see LICENSE.
 */
declare(strict_types=1);

namespace Normaro\Verifactu\Test\Unit\Model;

use Magento\Sales\Api\CreditmemoRepositoryInterface;
use Magento\Sales\Api\InvoiceRepositoryInterface;
use PHPUnit\Framework\TestCase;
use Normaro\Verifactu\Model\Document;
use Normaro\Verifactu\Model\Resender;
use Normaro\Verifactu\Model\Scheduler;

/**
 * Resend never calls Quaderno in the admin request.
 */
class ResenderTest extends TestCase
{
    /**
     * A draft is queued for a refresh; nothing is sent synchronously.
     *
     * @return void
     */
    public function testDraftIsQueuedForRefresh(): void
    {
        $row = $this->getMockBuilder(Document::class)->disableOriginalConstructor()
            ->onlyMethods(['getId'])->getMock();
        $row->setData(['status' => Document::STATUS_DRAFT, 'source_type' => Document::SOURCE_INVOICE]);
        $scheduler = $this->createMock(Scheduler::class);
        $scheduler->expects($this->once())->method('publish')->with($row);
        $scheduler->expects($this->never())->method('resendInvoice');
        $invoices = $this->createMock(InvoiceRepositoryInterface::class);
        $invoices->expects($this->never())->method('get');

        $resender = new Resender($scheduler, $invoices, $this->createMock(CreditmemoRepositoryInterface::class));

        $this->assertSame(Document::STATUS_DRAFT, $resender->resend($row));
    }
}
