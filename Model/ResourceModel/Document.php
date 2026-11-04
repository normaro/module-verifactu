<?php
/**
 * Copyright (c) Iurii Tkachenko. Licensed under OSL-3.0, see LICENSE.
 */
declare(strict_types=1);

namespace Normaro\Verifactu\Model\ResourceModel;

use Magento\Framework\Model\AbstractModel;
use Magento\Framework\Model\ResourceModel\Db\AbstractDb;

/**
 * Resource model of verifactu_document.
 */
class Document extends AbstractDb
{
    public const TABLE = 'verifactu_document';

    /**
     * @inheritdoc
     */
    protected function _construct()
    {
        $this->_init(self::TABLE, 'entity_id');
    }

    /**
     * Let the database set updated_at (ON UPDATE): the loaded value would otherwise be written back.
     *
     * Cron uses updated_at for lost queue messages and the refresh window.
     *
     * @param AbstractModel $object
     * @return $this
     */
    protected function _beforeSave(AbstractModel $object)
    {
        $object->unsetData('updated_at');
        return parent::_beforeSave($object);
    }
}
