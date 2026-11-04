<?php
/**
 * Copyright (c) Iurii Tkachenko. Licensed under OSL-3.0, see LICENSE.
 */
declare(strict_types=1);

namespace Normaro\Verifactu\Model\ResourceModel;

use Magento\Framework\App\ResourceConnection;

/**
 * Provider contact ID per registered customer and environment (sandbox / live).
 */
class CustomerContact
{
    public const TABLE = 'verifactu_customer_contact';

    /**
     * @param ResourceConnection $resource
     */
    public function __construct(private readonly ResourceConnection $resource)
    {
    }

    /**
     * Stored contact ID, or null.
     *
     * @param int $customerId
     * @param string $environment
     * @return string|null
     */
    public function get(int $customerId, string $environment): ?string
    {
        $connection = $this->resource->getConnection();
        $select = $connection->select()
            ->from($this->resource->getTableName(self::TABLE), ['contact_id'])
            ->where('customer_id = ?', $customerId)
            ->where('environment = ?', $environment);
        $value = (string)$connection->fetchOne($select);
        return $value === '' ? null : $value;
    }

    /**
     * Store or replace the contact ID.
     *
     * @param int $customerId
     * @param string $environment
     * @param string $contactId
     * @return void
     */
    public function save(int $customerId, string $environment, string $contactId): void
    {
        $this->resource->getConnection()->insertOnDuplicate(
            $this->resource->getTableName(self::TABLE),
            ['customer_id' => $customerId, 'environment' => $environment, 'contact_id' => $contactId],
            ['contact_id']
        );
    }
}
