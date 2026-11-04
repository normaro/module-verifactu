<?php
/**
 * Copyright (c) Iurii Tkachenko. Licensed under OSL-3.0, see LICENSE.
 */
declare(strict_types=1);

namespace Normaro\Verifactu\Model;

use Magento\Framework\Notification\NotifierInterface;

/**
 * Admin notifications for documents that need the merchant (blocked, draft, rejected, failed).
 */
class AdminNotifier
{
    /**
     * @param NotifierInterface $notifier
     */
    public function __construct(private readonly NotifierInterface $notifier)
    {
    }

    /**
     * Raise a major notification about a row.
     *
     * @param Document $document
     * @return void
     */
    public function notify(Document $document): void
    {
        $status = (string)$document->getData('status');
        $title = (string)__(
            'Verifactu: %1 is %2',
            $document->getMagentoLabel(),
            Document::STATUS_LABELS[$status] ?? $status
        );
        $this->notifier->addMajor(
            $title,
            (string)($document->getData('last_error') ?: __('See Sales > Operations > Verifactu Documents.'))
        );
    }
}
