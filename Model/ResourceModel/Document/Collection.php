<?php
/**
 * Copyright (c) Iurii Tkachenko. Licensed under OSL-3.0, see LICENSE.
 */
declare(strict_types=1);

namespace Normaro\Verifactu\Model\ResourceModel\Document;

use Magento\Framework\Model\ResourceModel\Db\Collection\AbstractCollection;
use Normaro\Verifactu\Model\Document;
use Normaro\Verifactu\Model\ResourceModel\Document as DocumentResource;

/**
 * Collection of Verifactu documents.
 */
class Collection extends AbstractCollection
{
    /**
     * @var string
     */
    protected $_idFieldName = 'entity_id';

    /**
     * @inheritdoc
     */
    protected function _construct()
    {
        $this->_init(Document::class, DocumentResource::class);
    }
}
