<?php
/**
 * Copyright (c) Iurii Tkachenko. Licensed under OSL-3.0, see LICENSE.
 */
declare(strict_types=1);

namespace Normaro\Verifactu\Model;

use Magento\Framework\Model\AbstractModel;
use Normaro\Verifactu\Model\ResourceModel\Document as DocumentResource;

/**
 * One Verifactu document (invoice, receipt or credit) per Magento invoice / credit memo.
 */
class Document extends AbstractModel
{
    public const SOURCE_INVOICE = 'invoice';
    public const SOURCE_CREDITMEMO = 'creditmemo';

    /** Labels of the Magento document types */
    public const SOURCE_LABELS = [self::SOURCE_INVOICE => 'Invoice', self::SOURCE_CREDITMEMO => 'Credit memo'];

    public const STATUS_PENDING = 'pending';
    public const STATUS_QUEUED = 'queued';
    public const STATUS_ISSUED = 'issued';
    public const STATUS_ISSUED_WITH_WARNING = 'issued_with_warning';
    public const STATUS_DRAFT = 'draft';
    public const STATUS_REJECTED = 'rejected';
    public const STATUS_FAILED = 'failed';
    public const STATUS_BLOCKED = 'blocked';

    public const TYPE_INVOICE = 'invoice';
    public const TYPE_RECEIPT = 'receipt';
    public const TYPE_CREDIT = 'credit';

    /** Admin labels of the statuses */
    public const STATUS_LABELS = [
        self::STATUS_PENDING => 'Pending',
        self::STATUS_QUEUED => 'Queued',
        self::STATUS_ISSUED => 'Issued',
        self::STATUS_ISSUED_WITH_WARNING => 'Issued with AEAT warning',
        self::STATUS_DRAFT => 'Draft at provider',
        self::STATUS_REJECTED => 'Rejected by AEAT',
        self::STATUS_FAILED => 'Failed',
        self::STATUS_BLOCKED => 'Blocked',
    ];

    /**
     * @var string
     */
    protected $_eventPrefix = 'verifactu_document';

    /**
     * @inheritdoc
     */
    protected function _construct()
    {
        $this->_init(DocumentResource::class);
    }

    /**
     * Number of the Magento invoice / credit memo (rows created before 0.1.0 fall back to processor_id).
     *
     * @return string
     */
    public function getMagentoNumber(): string
    {
        return (string)($this->getData('source_increment_id') ?: $this->getData('processor_id'));
    }

    /**
     * "invoice 000000019" / "credit memo 000000006".
     *
     * @return string
     */
    public function getMagentoLabel(): string
    {
        $type = $this->getData('source_type') === self::SOURCE_CREDITMEMO ? __('credit memo') : __('invoice');
        return $type . ' ' . $this->getMagentoNumber();
    }

    /**
     * Whether the provider has issued the document (with or without an AEAT warning).
     *
     * @return bool
     */
    public function isIssued(): bool
    {
        return in_array(
            (string)$this->getData('status'),
            [self::STATUS_ISSUED, self::STATUS_ISSUED_WITH_WARNING],
            true
        );
    }
}
