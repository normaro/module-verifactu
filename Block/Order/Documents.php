<?php
/**
 * Copyright (c) Iurii Tkachenko. Licensed under OSL-3.0, see LICENSE.
 */
declare(strict_types=1);

namespace Normaro\Verifactu\Block\Order;

use Magento\Framework\View\Element\Template;
use Magento\Framework\View\Element\Template\Context;
use Normaro\Verifactu\Model\Document;
use Normaro\Verifactu\Model\ResourceModel\Document\CollectionFactory;

/**
 * Customer account, order view: issued Verifactu documents of the order.
 * The page controller has already checked that the order belongs to the customer.
 */
class Documents extends Template
{
    /**
     * @param Context $context
     * @param CollectionFactory $collectionFactory
     * @param mixed[] $data
     */
    public function __construct(
        Context $context,
        private readonly CollectionFactory $collectionFactory,
        array $data = []
    ) {
        parent::__construct($context, $data);
    }

    /**
     * Issued documents of the order in the URL.
     *
     * @return Document[]
     */
    public function getDocuments(): array
    {
        $orderId = (int)$this->getRequest()->getParam('order_id');
        if ($orderId <= 0) {
            return [];
        }
        $collection = $this->collectionFactory->create()
            ->addFieldToFilter('order_id', (string)$orderId)
            ->addFieldToFilter(
                'status',
                ['in' => [Document::STATUS_ISSUED, Document::STATUS_ISSUED_WITH_WARNING]]
            )
            ->setOrder('entity_id', 'ASC');
        $documents = [];
        foreach ($collection as $row) {
            if ($row instanceof Document) {
                $documents[] = $row;
            }
        }
        return $documents;
    }

    /**
     * Issue date in the store locale (the provider date has no time; formatted in UTC so it never shifts).
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
}
