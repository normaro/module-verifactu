<?php
/**
 * Copyright (c) Iurii Tkachenko. Licensed under OSL-3.0, see LICENSE.
 */
declare(strict_types=1);

namespace Normaro\Verifactu\Model\Quaderno;

use Normaro\Verifactu\Api\ProviderInterface;
use Normaro\Verifactu\Model\Document;
use Normaro\Verifactu\Model\Provider\DocumentData;
use Normaro\Verifactu\Model\Provider\Exception\DuplicateException;
use Normaro\Verifactu\Model\Provider\Exception\PermanentException;
use Normaro\Verifactu\Model\Provider\ProviderResult;

/**
 * Quaderno implementation of the provider.
 */
class QuadernoProvider implements ProviderInterface
{
    private const ENDPOINT_BY_TYPE = [
        Document::TYPE_INVOICE => 'invoices',
        Document::TYPE_RECEIPT => 'receipts',
        Document::TYPE_CREDIT => 'credits',
    ];

    /** Page size of the credit lookup (newest first, cursor created_before = last ID) */
    private const CREDIT_PAGE_SIZE = 100;

    /** Safety bound on pages of the credit lookup */
    private const CREDIT_MAX_PAGES = 50;

    /** Clock margin when stopping the credit lookup at the original's creation time */
    private const CREDIT_TIME_MARGIN = 86400;

    /**
     * @param Client $client
     * @param PayloadBuilder $payloadBuilder
     * @param ResponseParser $responseParser
     */
    public function __construct(
        private readonly Client $client,
        private readonly PayloadBuilder $payloadBuilder,
        private readonly ResponseParser $responseParser
    ) {
    }

    /**
     * @inheritdoc
     */
    public function send(DocumentData $data): ProviderResult
    {
        $isRefund = $data->transactionType === DocumentData::TRANSACTION_REFUND;
        try {
            $created = $this->client->post('transactions', $this->payloadBuilder->build($data), $data->storeId);
        } catch (HttpException $e) {
            if (!$e->isProcessorIdTaken()) {
                throw $e;
            }
            $existing = $isRefund
                ? $this->findCredit($data, (int)round($data->grandTotal * 100))
                : $this->findByProcessorId($data->processorId, null, $data->storeId);
            if ($existing === null) {
                throw new DuplicateException(
                    __('Quaderno already has a document for %1, but it was not found.', $data->processorId),
                    $e
                );
            }
            return $this->fetch($existing->providerId, $existing->type, $data->storeId);
        }
        $result = $this->responseParser->parse($created, $isRefund ? Document::TYPE_CREDIT : null);
        if ($result->providerId === '') {
            throw new PermanentException(__('Quaderno returned no document ID for %1.', $data->processorId));
        }
        // The create response has no deliveries / verification fields (Quaderno sandbox): fetch once.
        return $this->fetch($result->providerId, $result->type, $data->storeId);
    }

    /**
     * @inheritdoc
     */
    public function findByProcessorId(string $processorId, ?string $type, int $storeId): ?ProviderResult
    {
        if ($type === Document::TYPE_CREDIT) {
            return $this->scanCredits($processorId, $storeId, null, '', null);
        }
        // A receipt is found only in /receipts, an invoice only in /invoices (Quaderno sandbox).
        $types = $type === null ? [Document::TYPE_INVOICE, Document::TYPE_RECEIPT] : [$type];
        foreach ($types as $docType) {
            $list = $this->client->get(
                self::ENDPOINT_BY_TYPE[$docType],
                ['processor_id' => $processorId],
                $storeId
            );
            foreach ($list as $item) {
                if (is_array($item) && (string)($item['processor_id'] ?? '') === $processorId) {
                    return $this->responseParser->parse($item, $docType);
                }
            }
        }
        return null;
    }

    /**
     * @inheritdoc
     */
    public function fetch(string $providerId, string $type, int $storeId): ProviderResult
    {
        $endpoint = self::ENDPOINT_BY_TYPE[$type] ?? null;
        if ($endpoint === null) {
            throw new PermanentException(__('Unknown Quaderno document type "%1".', $type));
        }
        $body = $this->client->get($endpoint . '/' . rawurlencode($providerId), [], $storeId);
        return $this->responseParser->parse($body, $type);
    }

    /**
     * Find the credit of a refund whose create response was lost (send() got a 422).
     *
     * @param DocumentData $data
     * @param int $totalCents
     * @return ProviderResult|null
     */
    private function findCredit(DocumentData $data, int $totalCents): ?ProviderResult
    {
        $cutoff = $data->originalCreatedAt !== null ? $data->originalCreatedAt - self::CREDIT_TIME_MARGIN : null;
        return $this->scanCredits(
            $data->processorId,
            $data->storeId,
            $totalCents,
            (string)$data->customer->contactId,
            $cutoff
        );
    }

    /**
     * Scan credits, newest first, for the processor-ID prefix and total.
     *
     * Quaderno stores a credit's processor ID as "<sent>_<unix time>" (Quaderno sandbox), so the
     * processor_id filter does not find it. The list is narrowed to the original's contact
     * (`contact` filter) and paged with the `created_before` cursor until the credits are
     * older than the original (sandbox, 2026-11-04).
     *
     * @param string $processorId
     * @param int $storeId
     * @param int|null $totalCents only accept a credit with this total (a refund of the same amount)
     * @param string $contactId original's contact; '' = all contacts
     * @param int|null $cutoff stop at credits created before this unix time; null = page bound only
     * @return ProviderResult|null
     */
    private function scanCredits(
        string $processorId,
        int $storeId,
        ?int $totalCents,
        string $contactId,
        ?int $cutoff
    ): ?ProviderResult {
        $query = ['limit' => (string)self::CREDIT_PAGE_SIZE];
        if ($contactId !== '') {
            $query['contact'] = $contactId;
        }
        for ($page = 0; $page < self::CREDIT_MAX_PAGES; $page++) {
            $list = array_values(array_filter($this->client->get('credits', $query, $storeId), 'is_array'));
            foreach ($list as $item) {
                if ($this->isCreditOf($item, $processorId, $totalCents)) {
                    return $this->responseParser->parse($item, Document::TYPE_CREDIT);
                }
            }
            $last = end($list);
            if (count($list) < self::CREDIT_PAGE_SIZE || $last === false
                || ($cutoff !== null && (int)($last['created_at'] ?? 0) < $cutoff)
            ) {
                return null;
            }
            $query['created_before'] = (string)($last['id'] ?? '');
        }
        return null;
    }

    /**
     * Credit with the processor-ID prefix and, if given, the same total.
     *
     * @param array<mixed> $item
     * @param string $processorId
     * @param int|null $totalCents
     * @return bool
     */
    private function isCreditOf(array $item, string $processorId, ?int $totalCents): bool
    {
        $stored = (string)($item['processor_id'] ?? '');
        if ($stored !== $processorId && !str_starts_with($stored, $processorId . '_')) {
            return false;
        }
        return $totalCents === null || abs((int)($item['total_cents'] ?? 0)) === abs($totalCents);
    }
}
