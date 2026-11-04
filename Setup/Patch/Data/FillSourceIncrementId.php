<?php
/**
 * Copyright (c) Iurii Tkachenko. Licensed under OSL-3.0, see LICENSE.
 */
declare(strict_types=1);

namespace Normaro\Verifactu\Setup\Patch\Data;

use Magento\Framework\DB\Sql\Expression;
use Magento\Framework\Setup\ModuleDataSetupInterface;
use Magento\Framework\Setup\Patch\DataPatchInterface;
use Normaro\Verifactu\Model\Document;
use Normaro\Verifactu\Model\ResourceModel\Document as DocumentResource;

/**
 * Fill source_increment_id for rows created before the column existed.
 */
class FillSourceIncrementId implements DataPatchInterface
{
    /** Source type => Magento table */
    private const SOURCE_TABLES = [
        Document::SOURCE_INVOICE => 'sales_invoice',
        Document::SOURCE_CREDITMEMO => 'sales_creditmemo',
    ];

    /**
     * @param ModuleDataSetupInterface $setup
     */
    public function __construct(private readonly ModuleDataSetupInterface $setup)
    {
    }

    /**
     * @inheritdoc
     */
    public function apply()
    {
        $connection = $this->setup->getConnection();
        $table = $this->setup->getTable(DocumentResource::TABLE);
        foreach (self::SOURCE_TABLES as $sourceType => $sourceTable) {
            $select = $connection->select()
                ->from(['d' => $table], ['entity_id'])
                ->join(['s' => $this->setup->getTable($sourceTable)], 's.entity_id = d.source_id', ['increment_id'])
                ->where('d.source_type = ?', $sourceType)
                ->where('d.source_increment_id IS NULL');
            foreach ($connection->fetchPairs($select) as $rowId => $incrementId) {
                $connection->update(
                    $table,
                    // keep updated_at: cron uses it (lost messages, refresh window)
                    ['source_increment_id' => $incrementId, 'updated_at' => new Expression('updated_at')],
                    ['entity_id = ?' => $rowId]
                );
            }
        }
        return $this;
    }

    /**
     * @inheritdoc
     */
    public static function getDependencies()
    {
        return [];
    }

    /**
     * @inheritdoc
     */
    public function getAliases()
    {
        return [];
    }
}
