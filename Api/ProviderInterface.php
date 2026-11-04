<?php
/**
 * Copyright (c) Iurii Tkachenko. Licensed under OSL-3.0, see LICENSE.
 */
declare(strict_types=1);

namespace Normaro\Verifactu\Api;

use Normaro\Verifactu\Model\Provider\DocumentData;
use Normaro\Verifactu\Model\Provider\Exception\PermanentException;
use Normaro\Verifactu\Model\Provider\Exception\TransientException;
use Normaro\Verifactu\Model\Provider\ProviderResult;

/**
 * Certified Verifactu provider (Quaderno in v0.1).
 *
 * @api
 */
interface ProviderInterface
{
    /**
     * Create the document (sale or refund) and return its state after a follow-up fetch.
     *
     * If the provider already has a document with this processor ID (a retry after a lost
     * response), the existing document is returned instead of an error.
     *
     * @param DocumentData $data
     * @return ProviderResult
     * @throws TransientException
     * @throws PermanentException
     */
    public function send(DocumentData $data): ProviderResult;

    /**
     * Find a document created with this processor ID.
     *
     * @param string $processorId
     * @param string|null $type invoice, receipt, credit; null = invoice or receipt
     * @param int $storeId
     * @return ProviderResult|null
     * @throws TransientException
     * @throws PermanentException
     */
    public function findByProcessorId(string $processorId, ?string $type, int $storeId): ?ProviderResult;

    /**
     * Current state of a document (number, deliveries / AEAT status, links).
     *
     * @param string $providerId
     * @param string $type invoice, receipt or credit
     * @param int $storeId
     * @return ProviderResult
     * @throws TransientException
     * @throws PermanentException
     */
    public function fetch(string $providerId, string $type, int $storeId): ProviderResult;
}
