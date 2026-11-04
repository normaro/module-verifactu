<?php
/**
 * Copyright (c) Iurii Tkachenko. Licensed under OSL-3.0, see LICENSE.
 */
declare(strict_types=1);

namespace Normaro\Verifactu\Model;

use Magento\Backend\Model\UrlInterface;
use Magento\Framework\Notification\NotifierInterface;

/**
 * Admin notifications for documents that need the merchant (blocked, draft, rejected, failed).
 * "Read Details" opens the Magento invoice / credit memo (through verifactu/document/open).
 */
class AdminNotifier
{
    /**
     * @param NotifierInterface $notifier
     * @param UrlInterface $backendUrl
     */
    public function __construct(
        private readonly NotifierInterface $notifier,
        private readonly UrlInterface $backendUrl
    ) {
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
        $statusLabel = Document::STATUS_LABELS[$status] ?? $status;
        $title = $status === Document::STATUS_BLOCKED
            ? (string)__('Verifactu: %1 not sent (blocked)', $document->getMagentoLabel())
            : (string)__('Verifactu: %1 is %2', $document->getMagentoLabel(), __($statusLabel));
        $this->notifier->addMajor(
            $title,
            (string)($document->getData('last_error') ?: __('See Sales > Operations > Verifactu Documents.')),
            $this->backendUrl->getUrl('verifactu/document/open', ['id' => $document->getId(), '_nosecret' => true])
        );
    }
}
