<?php
/**
 * Copyright (c) Iurii Tkachenko. Licensed under OSL-3.0, see LICENSE.
 */
declare(strict_types=1);

namespace Normaro\Verifactu\Model;

use Magento\Framework\Exception\NoSuchEntityException;
use Normaro\Verifactu\Model\ResourceModel\Document as DocumentResource;
use Normaro\Verifactu\Model\ResourceModel\Document\CollectionFactory;

/**
 * Load and save Verifactu document rows.
 */
class DocumentRepository
{
    /**
     * @param DocumentFactory $documentFactory
     * @param DocumentResource $resource
     * @param CollectionFactory $collectionFactory
     */
    public function __construct(
        private readonly DocumentFactory $documentFactory,
        private readonly DocumentResource $resource,
        private readonly CollectionFactory $collectionFactory
    ) {
    }

    /**
     * Load a row by ID.
     *
     * @param int $id
     * @return Document
     * @throws NoSuchEntityException
     */
    public function getById(int $id): Document
    {
        $document = $this->documentFactory->create();
        $this->resource->load($document, $id);
        if (!$document->getId()) {
            throw new NoSuchEntityException(__('Verifactu document %1 does not exist.', $id));
        }
        return $document;
    }

    /**
     * Find the row of a Magento invoice / credit memo.
     *
     * @param string $sourceType
     * @param int $sourceId
     * @return Document|null
     */
    public function findBySource(string $sourceType, int $sourceId): ?Document
    {
        $collection = $this->collectionFactory->create()
            ->addFieldToFilter('source_type', $sourceType)
            ->addFieldToFilter('source_id', (string)$sourceId)
            ->setPageSize(1);
        $document = $collection->getFirstItem();
        return $document instanceof Document && $document->getId() ? $document : null;
    }

    /**
     * Rows of credit memos that reference the given original row.
     *
     * @param int $originalId
     * @param int|null $excludeId
     * @return Document[]
     */
    public function findCredits(int $originalId, ?int $excludeId = null): array
    {
        $collection = $this->collectionFactory->create()
            ->addFieldToFilter('original_document_id', (string)$originalId);
        if ($excludeId !== null) {
            $collection->addFieldToFilter('entity_id', ['neq' => $excludeId]);
        }
        $documents = [];
        foreach ($collection->getItems() as $item) {
            if ($item instanceof Document) {
                $documents[] = $item;
            }
        }
        return $documents;
    }

    /**
     * New empty row.
     *
     * @return Document
     */
    public function create(): Document
    {
        return $this->documentFactory->create();
    }

    /**
     * Save a row.
     *
     * @param Document $document
     * @return Document
     */
    public function save(Document $document): Document
    {
        $this->resource->save($document);
        return $document;
    }
}
