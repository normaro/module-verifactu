<?php
/**
 * Copyright (c) Iurii Tkachenko. Licensed under OSL-3.0, see LICENSE.
 */
declare(strict_types=1);

namespace Normaro\Verifactu\Model;

use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\Stdlib\DateTime\DateTime;
use Magento\Sales\Model\Order;
use Normaro\Verifactu\Api\ProviderInterface;
use Normaro\Verifactu\Model\Log\Logger;
use Normaro\Verifactu\Model\Precheck\PrecheckResult;
use Normaro\Verifactu\Model\Provider\DocumentData;
use Normaro\Verifactu\Model\Provider\Exception\PermanentException;
use Normaro\Verifactu\Model\Provider\Exception\TransientException;
use Normaro\Verifactu\Model\Provider\ProviderResult;

/**
 * Sends a queued row to the provider and stores the result (queue consumer and cron).
 */
class Sender
{
    public const MAX_ATTEMPTS = 6;

    /** Backoff after attempt 1, 2, 3, then hourly */
    private const BACKOFF_MINUTES = [1 => 5, 2 => 15, 3 => 60];

    /** Statuses a queued message refreshes from the provider instead of sending (admin Resend) */
    public const REFRESH_STATUSES = [Document::STATUS_DRAFT, Document::STATUS_REJECTED];

    /** Statuses that need the merchant: notify when a row enters one of them */
    private const NOTIFY_STATUSES = [Document::STATUS_DRAFT, Document::STATUS_REJECTED];

    /**
     * @param DocumentRepository $documentRepository
     * @param ProviderInterface $provider
     * @param SourceCheck $sourceCheck
     * @param Scheduler $scheduler
     * @param ContactRecorder $contactRecorder
     * @param AdminNotifier $notifier
     * @param DateTime $dateTime
     * @param Logger $logger
     */
    public function __construct(
        private readonly DocumentRepository $documentRepository,
        private readonly ProviderInterface $provider,
        private readonly SourceCheck $sourceCheck,
        private readonly Scheduler $scheduler,
        private readonly ContactRecorder $contactRecorder,
        private readonly AdminNotifier $notifier,
        private readonly DateTime $dateTime,
        private readonly Logger $logger
    ) {
    }

    /**
     * Send one row if it is queued; refresh it if it is a draft / rejected (admin Resend).
     *
     * @param int $documentId
     * @return void
     */
    public function process(int $documentId): void
    {
        try {
            $row = $this->documentRepository->getById($documentId);
        } catch (NoSuchEntityException $e) {
            return;
        }
        $status = (string)$row->getData('status');
        if (in_array($status, self::REFRESH_STATUSES, true)) {
            $this->refresh($row);
            return;
        }
        if ($status !== Document::STATUS_QUEUED) {
            return; // already sent, blocked, or handled by another consumer run
        }
        try {
            [$check, $order] = $this->sourceCheck->check($row);
        } catch (NoSuchEntityException $e) {
            $this->fail($row, (string)__('The Magento document no longer exists.'), false);
            return;
        }
        if ($check->outcome !== PrecheckResult::OK || $check->data === null) {
            $this->scheduler->apply($row, $check);
            return;
        }
        $this->send($row, $check->data, $order);
    }

    /**
     * Refresh an issued / draft row from the provider (deliveries, AEAT state).
     *
     * @param Document $row
     * @return void
     */
    public function refresh(Document $row): void
    {
        $providerId = (string)$row->getData('quaderno_id');
        if ($providerId === '') {
            return;
        }
        try {
            $result = $this->provider->fetch(
                $providerId,
                (string)$row->getData('type'),
                (int)$row->getData('store_id')
            );
        } catch (TransientException | PermanentException $e) {
            $this->logger->error('Refresh failed: ' . $e->getMessage(), ['row' => $row->getId()]);
            return;
        }
        $this->store($row, $result, null);
    }

    /**
     * Call the provider and store the outcome.
     *
     * @param Document $row
     * @param DocumentData $data
     * @param Order $order
     * @return void
     */
    private function send(Document $row, DocumentData $data, Order $order): void
    {
        $row->setData('attempts', (int)$row->getData('attempts') + 1);
        try {
            $result = $this->provider->send($data);
        } catch (TransientException $e) {
            $this->fail($row, (string)$e->getMessage(), true);
            return;
        } catch (PermanentException $e) {
            $this->fail($row, (string)$e->getMessage(), false);
            return;
        }
        $this->store($row, $result, $data);
        $this->contactRecorder->record($order, $data, $result);
    }

    /**
     * Store a provider result on the row.
     *
     * @param Document $row
     * @param ProviderResult $result
     * @param DocumentData|null $data null on refresh
     * @return void
     */
    private function store(Document $row, ProviderResult $result, ?DocumentData $data): void
    {
        $previous = (string)$row->getData('status');
        $row->addData([
            'quaderno_id' => $result->providerId,
            'quaderno_contact_id' => $result->contactId ?? $row->getData('quaderno_contact_id'),
            'number' => $result->number,
            'type' => $result->type,
            'status' => $result->status,
            'aeat_status' => $result->aeatStatus,
            'verification_permalink' => $result->verificationPermalink,
            'aeat_qr_url' => $result->aeatQrUrl,
            'pdf_url' => $result->pdfUrl,
            'issue_date' => $result->issueDate,
            'next_attempt_at' => null,
            'last_error' => $this->notes($result, $data),
        ]);
        $this->documentRepository->save($row);
        $this->logger->status(
            $row->getId(),
            (string)$row->getData('processor_id'),
            $previous,
            $result->status,
            trim($result->number . ' ' . $result->aeatStatus)
        );
        if ($previous !== $result->status && in_array($result->status, self::NOTIFY_STATUSES, true)) {
            $this->notifier->notify($row);
        }
    }

    /**
     * AEAT message and a total mismatch, if any.
     *
     * @param ProviderResult $result
     * @param DocumentData|null $data
     * @return string|null
     */
    private function notes(ProviderResult $result, ?DocumentData $data): ?string
    {
        $notes = [];
        if ($result->aeatMessage !== null) {
            $notes[] = 'AEAT: ' . $result->aeatMessage;
        }
        if ($result->status === Document::STATUS_DRAFT) {
            $notes[] = (string)__('Quaderno left the document as a draft without a number (no reason given;'
                . ' check the customer data in Quaderno).');
        }
        if ($data !== null && $result->totalCents !== null
            && abs($result->totalCents) !== (int)round($data->grandTotal * 100)
        ) {
            $notes[] = (string)__(
                'Quaderno total %1 differs from the Magento total %2.',
                number_format(abs($result->totalCents) / 100, 2, '.', ''),
                number_format($data->grandTotal, 2, '.', '')
            );
        }
        return $notes ? implode(' ', $notes) : null;
    }

    /**
     * Mark failed; transient errors get a retry time, permanent ones wait for a manual resend.
     *
     * @param Document $row
     * @param string $error
     * @param bool $retry
     * @return void
     */
    private function fail(Document $row, string $error, bool $retry): void
    {
        $previous = (string)$row->getData('status');
        $attempts = (int)$row->getData('attempts');
        $retry = $retry && $attempts < self::MAX_ATTEMPTS;
        $minutes = self::BACKOFF_MINUTES[$attempts] ?? 60;
        $row->addData([
            'status' => Document::STATUS_FAILED,
            'last_error' => $error,
            'next_attempt_at' => $retry ? $this->dateTime->gmtDate(null, time() + $minutes * 60) : null,
        ]);
        $this->documentRepository->save($row);
        $this->logger->error('Send failed: ' . $error, ['row' => $row->getId(), 'attempts' => $attempts]);
        $this->logger->status($row->getId(), (string)$row->getData('processor_id'), $previous, Document::STATUS_FAILED);
        if (!$retry) {
            $this->notifier->notify($row);
        }
    }
}
