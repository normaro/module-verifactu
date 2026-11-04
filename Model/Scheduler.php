<?php
/**
 * Copyright (c) Iurii Tkachenko. Licensed under OSL-3.0, see LICENSE.
 */
declare(strict_types=1);

namespace Normaro\Verifactu\Model;

use Magento\Framework\MessageQueue\PublisherInterface;
use Magento\Framework\Stdlib\DateTime\DateTime;
use Magento\Sales\Model\Order\Creditmemo;
use Magento\Sales\Model\Order\Invoice;
use Normaro\Verifactu\Model\Log\Logger;
use Normaro\Verifactu\Model\Precheck\Precheck;
use Normaro\Verifactu\Model\Precheck\PrecheckResult;
use Normaro\Verifactu\Model\ResourceModel\Document\CollectionFactory;

/**
 * Creates the row of an invoice / credit memo, runs the precheck and queues it.
 * No API call happens here (no provider call inside the request).
 */
class Scheduler
{
    public const TOPIC = 'verifactu.document.send';
    public const WAIT_SECONDS = 300;

    /**
     * @param DocumentRepository $documentRepository
     * @param Precheck $precheck
     * @param PublisherInterface $publisher
     * @param AdminNotifier $notifier
     * @param DateTime $dateTime
     * @param CollectionFactory $collectionFactory
     * @param Logger $logger
     */
    public function __construct(
        private readonly DocumentRepository $documentRepository,
        private readonly Precheck $precheck,
        private readonly PublisherInterface $publisher,
        private readonly AdminNotifier $notifier,
        private readonly DateTime $dateTime,
        private readonly CollectionFactory $collectionFactory,
        private readonly Logger $logger
    ) {
    }

    /**
     * Schedule a new invoice; an existing row (repeated save) is left alone.
     *
     * @param Invoice $invoice
     * @return Document
     */
    public function scheduleInvoice(Invoice $invoice): Document
    {
        $row = $this->documentRepository->findBySource(Document::SOURCE_INVOICE, (int)$invoice->getId());
        return $row ?? $this->queueInvoice($invoice, null);
    }

    /**
     * Manual resend of an invoice: precheck again and queue.
     *
     * @param Invoice $invoice
     * @return Document
     */
    public function resendInvoice(Invoice $invoice): Document
    {
        $row = $this->documentRepository->findBySource(Document::SOURCE_INVOICE, (int)$invoice->getId());
        return $this->queueInvoice($invoice, $row);
    }

    /**
     * Schedule a new credit memo; an existing row is left alone.
     *
     * @param Creditmemo $creditmemo
     * @return Document
     */
    public function scheduleCreditmemo(Creditmemo $creditmemo): Document
    {
        $row = $this->documentRepository->findBySource(Document::SOURCE_CREDITMEMO, (int)$creditmemo->getId());
        return $row ?? $this->queueCreditmemo($creditmemo, null);
    }

    /**
     * Manual resend of a credit memo.
     *
     * @param Creditmemo $creditmemo
     * @return Document
     */
    public function resendCreditmemo(Creditmemo $creditmemo): Document
    {
        $row = $this->documentRepository->findBySource(Document::SOURCE_CREDITMEMO, (int)$creditmemo->getId());
        return $this->queueCreditmemo($creditmemo, $row);
    }

    /**
     * Create / update the row of an invoice and queue it.
     *
     * @param Invoice $invoice
     * @param Document|null $row
     * @return Document
     */
    private function queueInvoice(Invoice $invoice, ?Document $row): Document
    {
        $row = $row ?? $this->newRow(Document::SOURCE_INVOICE, (int)$invoice->getId(), $invoice->getOrder());
        $row->setData('source_increment_id', (string)$invoice->getIncrementId());
        $row->setData('processor_id', (string)$invoice->getIncrementId());
        return $this->apply($row, $this->precheck->checkInvoice($invoice));
    }

    /**
     * Create / update the row of a credit memo, linked to the row of its invoice, and queue it.
     *
     * @param Creditmemo $creditmemo
     * @param Document|null $row
     * @return Document
     */
    private function queueCreditmemo(Creditmemo $creditmemo, ?Document $row): Document
    {
        $row = $row ?? $this->newRow(
            Document::SOURCE_CREDITMEMO,
            (int)$creditmemo->getId(),
            $creditmemo->getOrder()
        );
        $row->setData('source_increment_id', (string)$creditmemo->getIncrementId());
        $original = $this->originalRow($creditmemo);
        $row->setData('original_document_id', $original?->getId());
        $row->setData('processor_id', $original ? (string)$original->getData('processor_id') : null);
        if ($row->getId() === null) {
            $this->documentRepository->save($row); // the precheck excludes this row by ID
        }
        return $this->apply($row, $this->precheck->checkCreditmemo($creditmemo, $row));
    }

    /**
     * Put a queued row on the queue.
     *
     * @param Document $row
     * @return void
     */
    public function publish(Document $row): void
    {
        $this->publisher->publish(self::TOPIC, (string)$row->getId());
    }

    /**
     * Store the precheck outcome and queue / notify.
     *
     * @param Document $row
     * @param PrecheckResult $result
     * @return Document
     */
    public function apply(Document $row, PrecheckResult $result): Document
    {
        $previous = (string)$row->getData('status');
        $row->setData('attempts', 0);
        if ($result->outcome === PrecheckResult::BLOCKED) {
            $wasBlocked = $row->getData('status') === Document::STATUS_BLOCKED
                && $row->getData('last_error') === $result->reason;
            $row->setData('status', Document::STATUS_BLOCKED);
            $row->setData('last_error', $result->reason);
            $row->setData('next_attempt_at', null);
            $this->documentRepository->save($row);
            $this->logStatus($row, $previous, $result->reason);
            if (!$wasBlocked) {
                $this->notifier->notify($row); // not again for the same reason (mass resend)
            }
            return $row;
        }
        $row->setData('status', Document::STATUS_QUEUED);
        if ($result->outcome === PrecheckResult::WAIT) {
            $row->setData('last_error', $result->reason);
            $row->setData('next_attempt_at', $this->dateTime->gmtDate(null, time() + self::WAIT_SECONDS));
            $this->documentRepository->save($row);
            $this->logStatus($row, $previous, $result->reason);
            return $row;
        }
        $row->setData('last_error', $result->warnings ? implode(' ', $result->warnings) : null);
        $row->setData('next_attempt_at', null);
        $this->documentRepository->save($row);
        $this->logStatus($row, $previous, null);
        $this->publish($row);
        return $row;
    }

    /**
     * Log the status change of the row.
     *
     * @param Document $row
     * @param string $previous
     * @param string|null $reason
     * @return void
     */
    private function logStatus(Document $row, string $previous, ?string $reason): void
    {
        $this->logger->status(
            $row->getId(),
            (string)$row->getData('processor_id'),
            $previous,
            (string)$row->getData('status'),
            $reason
        );
    }

    /**
     * New row with the order data.
     *
     * @param string $sourceType
     * @param int $sourceId
     * @param \Magento\Sales\Model\Order $order
     * @return Document
     */
    private function newRow(string $sourceType, int $sourceId, \Magento\Sales\Model\Order $order): Document
    {
        $row = $this->documentRepository->create();
        $row->setData('source_type', $sourceType);
        $row->setData('source_id', $sourceId);
        $row->setData('order_id', (int)$order->getId());
        $row->setData('store_id', (int)$order->getStoreId());
        $row->setData('status', Document::STATUS_PENDING);
        return $row;
    }

    /**
     * Row of the invoice the credit memo refunds.
     *
     * Without an invoice link: the only invoice row of the order.
     *
     * @param Creditmemo $creditmemo
     * @return Document|null
     */
    private function originalRow(Creditmemo $creditmemo): ?Document
    {
        $invoiceId = (int)$creditmemo->getInvoiceId();
        if ($invoiceId > 0) {
            return $this->documentRepository->findBySource(Document::SOURCE_INVOICE, $invoiceId);
        }
        $rows = $this->collectionFactory->create()
            ->addFieldToFilter('order_id', (string)$creditmemo->getOrderId())
            ->addFieldToFilter('source_type', Document::SOURCE_INVOICE)
            ->getItems();
        if (count($rows) !== 1) {
            return null;
        }
        $row = reset($rows);
        return $row instanceof Document ? $row : null;
    }
}
