<?php
/**
 * Copyright (c) Iurii Tkachenko. Licensed under OSL-3.0, see LICENSE.
 */
declare(strict_types=1);

namespace Normaro\Verifactu\Model;

use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Sales\Api\CreditmemoRepositoryInterface;
use Magento\Sales\Api\InvoiceRepositoryInterface;
use Magento\Sales\Model\Order\Creditmemo;
use Magento\Sales\Model\Order\Invoice;

/**
 * Manual resend from the admin (single row or mass action).
 *
 * blocked / failed: precheck again and queue; draft / rejected: refresh from the provider
 * (the provider already has a document with this processor ID); issued: nothing to do.
 */
class Resender
{
    /**
     * @param Scheduler $scheduler
     * @param InvoiceRepositoryInterface $invoiceRepository
     * @param CreditmemoRepositoryInterface $creditmemoRepository
     */
    public function __construct(
        private readonly Scheduler $scheduler,
        private readonly InvoiceRepositoryInterface $invoiceRepository,
        private readonly CreditmemoRepositoryInterface $creditmemoRepository
    ) {
    }

    /**
     * Resend one row; returns the resulting status.
     *
     * @param Document $row
     * @return string
     * @throws NoSuchEntityException
     */
    public function resend(Document $row): string
    {
        $status = (string)$row->getData('status');
        if ($row->isIssued()) {
            return $status;
        }
        if (in_array($status, Sender::REFRESH_STATUSES, true)) {
            // No HTTP call in the admin request: the consumer refreshes the row from Quaderno.
            $this->scheduler->publish($row);
            return $status;
        }
        $sourceId = (int)$row->getData('source_id');
        if ($row->getData('source_type') === Document::SOURCE_CREDITMEMO) {
            /** @var Creditmemo $creditmemo */
            $creditmemo = $this->creditmemoRepository->get($sourceId);
            return (string)$this->scheduler->resendCreditmemo($creditmemo)->getData('status');
        }
        /** @var Invoice $invoice */
        $invoice = $this->invoiceRepository->get($sourceId);
        return (string)$this->scheduler->resendInvoice($invoice)->getData('status');
    }
}
