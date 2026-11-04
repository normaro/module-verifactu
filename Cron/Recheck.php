<?php
/**
 * Copyright (c) Iurii Tkachenko. Licensed under OSL-3.0, see LICENSE.
 */
declare(strict_types=1);

namespace Normaro\Verifactu\Cron;

use Magento\Framework\Stdlib\DateTime\DateTime;
use Normaro\Verifactu\Model\Document;
use Normaro\Verifactu\Model\DocumentRepository;
use Normaro\Verifactu\Model\ResourceModel\Document\Collection;
use Normaro\Verifactu\Model\ResourceModel\Document\CollectionFactory;
use Normaro\Verifactu\Model\Scheduler;
use Normaro\Verifactu\Model\Sender;

/**
 * verifactu_recheck, every 10 minutes:
 * re-publish due retries and deferred rows, refresh drafts and issued rows with a pending delivery.
 */
class Recheck
{
    private const BATCH = 200;
    /** Queued rows without progress for this long are re-published (lost message) */
    private const LOST_MESSAGE_SECONDS = 1800;
    /** Issued rows with a pending AEAT delivery are refreshed for this long */
    private const REFRESH_DAYS = 7;

    /**
     * @param CollectionFactory $collectionFactory
     * @param Scheduler $scheduler
     * @param Sender $sender
     * @param DateTime $dateTime
     * @param DocumentRepository $documentRepository
     */
    public function __construct(
        private readonly CollectionFactory $collectionFactory,
        private readonly Scheduler $scheduler,
        private readonly Sender $sender,
        private readonly DateTime $dateTime,
        private readonly DocumentRepository $documentRepository
    ) {
    }

    /**
     * Run the job.
     *
     * @return void
     */
    public function execute(): void
    {
        $this->republishDue();
        $this->refreshPending();
    }

    /**
     * Failed rows due for a retry, deferred rows, and queued rows whose message was lost.
     *
     * @return void
     */
    private function republishDue(): void
    {
        $now = $this->dateTime->gmtDate();
        $lost = $this->dateTime->gmtDate(null, time() - self::LOST_MESSAGE_SECONDS);
        $collection = $this->collection()
            ->addFieldToFilter('status', ['in' => [Document::STATUS_FAILED, Document::STATUS_QUEUED]])
            ->addFieldToFilter(
                ['next_attempt_at', 'updated_at'],
                [['lteq' => $now], ['lteq' => $lost]]
            );
        foreach ($collection as $row) {
            if (!$row instanceof Document) {
                continue;
            }
            if ($row->getData('status') === Document::STATUS_FAILED && !$row->getData('next_attempt_at')) {
                continue; // permanent error: manual resend only
            }
            $row->setData('status', Document::STATUS_QUEUED);
            $row->setData('next_attempt_at', null);
            $this->documentRepository->save($row);
            $this->scheduler->publish($row);
        }
    }

    /**
     * Drafts and issued rows whose AEAT delivery is still pending.
     *
     * @return void
     */
    private function refreshPending(): void
    {
        $since = $this->dateTime->gmtDate(null, time() - self::REFRESH_DAYS * 86400);
        $collection = $this->collection()
            ->addFieldToFilter('updated_at', ['gteq' => $since])
            ->addFieldToFilter(
                ['status', 'aeat_status'],
                [['eq' => Document::STATUS_DRAFT], ['eq' => 'pending']]
            );
        foreach ($collection as $row) {
            if ($row instanceof Document) {
                $this->sender->refresh($row);
            }
        }
    }

    /**
     * Batch-limited collection.
     *
     * @return Collection
     */
    private function collection(): Collection
    {
        return $this->collectionFactory->create()->setPageSize(self::BATCH)->setCurPage(1);
    }
}
