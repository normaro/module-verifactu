<?php
/**
 * Copyright (c) Iurii Tkachenko. Licensed under OSL-3.0, see LICENSE.
 */
declare(strict_types=1);

namespace Normaro\Verifactu\Block\Adminhtml\Document;

use Magento\Backend\Block\Template;
use Magento\Backend\Block\Template\Context;
use Normaro\Verifactu\Model\Document;
use Normaro\Verifactu\Model\DocumentRepository;

/**
 * "Verifactu" block on the admin invoice / credit memo page.
 *
 * Layout arguments: source_type (invoice | creditmemo), id_param (invoice_id | creditmemo_id).
 */
class Info extends Template
{
    /**
     * @var Document|null
     */
    private ?Document $document = null;

    /**
     * @var bool
     */
    private bool $loaded = false;

    /**
     * @param Context $context
     * @param DocumentRepository $documentRepository
     * @param mixed[] $data
     */
    public function __construct(
        Context $context,
        private readonly DocumentRepository $documentRepository,
        array $data = []
    ) {
        parent::__construct($context, $data);
    }

    /**
     * Row of the current invoice / credit memo.
     *
     * @return Document|null
     */
    public function getDocument(): ?Document
    {
        if (!$this->loaded) {
            $this->loaded = true;
            $id = (int)$this->getRequest()->getParam((string)$this->getData('id_param'));
            $this->document = $id > 0
                ? $this->documentRepository->findBySource((string)$this->getData('source_type'), $id)
                : null;
        }
        return $this->document;
    }

    /**
     * Status label.
     *
     * @param Document $document
     * @return string
     */
    public function getStatusLabel(Document $document): string
    {
        $status = (string)$document->getData('status');
        return Document::STATUS_LABELS[$status] ?? $status;
    }

    /**
     * Issue date in the admin locale (the provider date has no time; formatted in UTC so it never shifts).
     *
     * @param Document $document
     * @return string
     */
    public function getIssueDate(Document $document): string
    {
        $date = (string)$document->getData('issue_date');
        return $date === '' ? '' : (string)$this->_localeDate->formatDateTime(
            $date,
            \IntlDateFormatter::MEDIUM,
            \IntlDateFormatter::NONE,
            null,
            'UTC'
        );
    }

    /**
     * Resend button shown: not issued, not blocked for good, and the admin may resend.
     *
     * @param Document $document
     * @return bool
     */
    public function canResend(Document $document): bool
    {
        return !$document->isIssued()
            && (string)$document->getData('status') !== Document::STATUS_QUEUED
            && !(int)$document->getData('block_permanent')
            && $this->_authorization->isAllowed('Normaro_Verifactu::resend');
    }

    /**
     * Resend action URL.
     *
     * @param Document $document
     * @return string
     */
    public function getResendUrl(Document $document): string
    {
        return $this->getUrl('verifactu/document/resend', ['id' => $document->getId()]);
    }
}
