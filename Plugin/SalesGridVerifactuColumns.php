<?php
/**
 * Copyright (c) Iurii Tkachenko. Licensed under OSL-3.0, see LICENSE.
 */
declare(strict_types=1);

namespace Normaro\Verifactu\Plugin;

use Magento\Framework\DB\Sql\Expression;
use Magento\Framework\Model\ResourceModel\Db\Collection\AbstractCollection;
use Magento\Framework\View\Element\UiComponent\DataProvider\CollectionFactory;
use Normaro\Verifactu\Model\Document;
use Normaro\Verifactu\Model\ResourceModel\Document as DocumentResource;

/**
 * Grid columns: Verifactu number and status in the invoice and credit memo grids ("not_sent" when the
 * document has no row), and the Magento order number in the Verifactu Documents grid.
 */
class SalesGridVerifactuColumns
{
    public const NOT_SENT = 'not_sent';

    private const SOURCES = [
        'sales_order_invoice_grid_data_source' => Document::SOURCE_INVOICE,
        'sales_order_creditmemo_grid_data_source' => Document::SOURCE_CREDITMEMO,
    ];

    /**
     * Join verifactu_document to the sales grids; add the order number to the documents grid.
     *
     * @param CollectionFactory $subject
     * @param mixed $result
     * @param string $requestName
     * @return mixed
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function afterGetReport(CollectionFactory $subject, $result, $requestName)
    {
        if (!$result instanceof AbstractCollection) {
            return $result;
        }
        if ((string)$requestName === 'verifactu_documents_data_source') {
            $orderNumber = new Expression(sprintf(
                '(SELECT so.increment_id FROM %s AS so WHERE so.entity_id = main_table.order_id)',
                $result->getConnection()->quoteIdentifier($result->getTable('sales_order'))
            ));
            $result->getSelect()->columns(['order_increment_id' => $orderNumber]);
            // An expression is accepted: the mapped field goes through quoteIdentifier(), which keeps it as is.
            // @phpstan-ignore-next-line
            $result->addFilterToMap('order_increment_id', $orderNumber);
            return $result;
        }
        $sourceType = self::SOURCES[(string)$requestName] ?? null;
        if ($sourceType === null) {
            return $result;
        }
        $connection = $result->getConnection();
        $status = new Expression($connection->quoteInto('IFNULL(verifactu.status, ?)', self::NOT_SENT));
        $result->getSelect()->joinLeft(
            ['verifactu' => $result->getTable(DocumentResource::TABLE)],
            'verifactu.source_id = main_table.entity_id AND '
            . $connection->quoteInto('verifactu.source_type = ?', $sourceType),
            ['verifactu_number' => 'verifactu.number', 'verifactu_status' => $status]
        );
        // @phpstan-ignore-next-line (expression as mapped field, see above)
        $result->addFilterToMap('verifactu_status', $status);
        $result->addFilterToMap('verifactu_number', 'verifactu.number');
        return $result;
    }
}
