<?php
/**
 * Copyright (c) Iurii Tkachenko. Licensed under OSL-3.0, see LICENSE.
 */
declare(strict_types=1);

namespace Normaro\Verifactu\Plugin;

use Magento\Framework\Model\ResourceModel\Db\Collection\AbstractCollection;
use Magento\Framework\View\Element\UiComponent\DataProvider\CollectionFactory;
use Normaro\Verifactu\Model\Document;
use Normaro\Verifactu\Model\ResourceModel\Document as DocumentResource;

/**
 * Adds Quaderno number and Verifactu status to the invoice and credit memo grids.
 */
class SalesGridVerifactuColumns
{
    private const SOURCES = [
        'sales_order_invoice_grid_data_source' => Document::SOURCE_INVOICE,
        'sales_order_creditmemo_grid_data_source' => Document::SOURCE_CREDITMEMO,
    ];

    /**
     * Join verifactu_document to the grid collection.
     *
     * @param CollectionFactory $subject
     * @param mixed $result
     * @param string $requestName
     * @return mixed
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function afterGetReport(CollectionFactory $subject, $result, $requestName)
    {
        $sourceType = self::SOURCES[(string)$requestName] ?? null;
        if ($sourceType === null || !$result instanceof AbstractCollection) {
            return $result;
        }
        $connection = $result->getConnection();
        $result->getSelect()->joinLeft(
            ['verifactu' => $result->getTable(DocumentResource::TABLE)],
            'verifactu.source_id = main_table.entity_id AND '
            . $connection->quoteInto('verifactu.source_type = ?', $sourceType),
            ['verifactu_number' => 'verifactu.number', 'verifactu_status' => 'verifactu.status']
        );
        $result->addFilterToMap('verifactu_status', 'verifactu.status');
        $result->addFilterToMap('verifactu_number', 'verifactu.number');
        return $result;
    }
}
