<?php
/**
 * Copyright (c) Iurii Tkachenko. Licensed under OSL-3.0, see LICENSE.
 */
declare(strict_types=1);

namespace Normaro\Verifactu\Model\Precheck;

use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Sales\Api\InvoiceRepositoryInterface;
use Magento\Sales\Model\Order\Creditmemo;
use Magento\Sales\Model\Order\Invoice;
use Normaro\Verifactu\Model\Config;
use Normaro\Verifactu\Model\Document;
use Normaro\Verifactu\Model\DocumentRepository;
use Normaro\Verifactu\Model\Mapper\CreditmemoMapper;
use Normaro\Verifactu\Model\Mapper\InvoiceMapper;
use Normaro\Verifactu\Model\Mapper\MappingException;
use Normaro\Verifactu\Model\Provider\DocumentData;

/**
 * Checks before anything is sent (sale and credit memo rules).
 */
class Precheck
{
    /** Postcode prefixes of Canarias (35, 38), Ceuta (51), Melilla (52) */
    private const ZERO_IVA_POSTCODES = ['35', '38', '51', '52'];

    /**
     * @param Config $config
     * @param InvoiceMapper $invoiceMapper
     * @param CreditmemoMapper $creditmemoMapper
     * @param NifValidator $nifValidator
     * @param DocumentRepository $documentRepository
     * @param InvoiceRepositoryInterface $invoiceRepository
     */
    public function __construct(
        private readonly Config $config,
        private readonly InvoiceMapper $invoiceMapper,
        private readonly CreditmemoMapper $creditmemoMapper,
        private readonly NifValidator $nifValidator,
        private readonly DocumentRepository $documentRepository,
        private readonly InvoiceRepositoryInterface $invoiceRepository
    ) {
    }

    /**
     * Check an invoice.
     *
     * @param Invoice $invoice
     * @return PrecheckResult
     */
    public function checkInvoice(Invoice $invoice): PrecheckResult
    {
        try {
            $data = $this->invoiceMapper->map($invoice);
        } catch (MappingException $e) {
            return new PrecheckResult(PrecheckResult::BLOCKED, (string)$e->getMessage());
        }
        return $this->checkData($data);
    }

    /**
     * Check a credit memo against the row of its original invoice.
     *
     * @param Creditmemo $creditmemo
     * @param Document $row
     * @return PrecheckResult
     */
    public function checkCreditmemo(Creditmemo $creditmemo, Document $row): PrecheckResult
    {
        $originalId = (int)$row->getData('original_document_id');
        if ($originalId <= 0) {
            return $this->blocked((string)__(
                'The original invoice has no Verifactu document, so the credit memo cannot be linked to it.'
            ));
        }
        $original = $this->documentRepository->getById($originalId);
        $originalCheck = $this->checkOriginal($original, $row);
        if ($originalCheck !== null) {
            return $originalCheck;
        }
        if ((float)$creditmemo->getGrandTotal() > $this->originalTotal($original) + 0.005) {
            return $this->blocked((string)__('The credit memo total exceeds the original invoice.'));
        }
        try {
            $data = $this->creditmemoMapper->map($creditmemo, $original);
        } catch (MappingException $e) {
            return new PrecheckResult(PrecheckResult::BLOCKED, (string)$e->getMessage());
        }
        return $this->checkData($data);
    }

    /**
     * Original must be issued, and only one credit per original (Quaderno sandbox).
     *
     * @param Document $original
     * @param Document $row
     * @return PrecheckResult|null null = fine
     */
    private function checkOriginal(Document $original, Document $row): ?PrecheckResult
    {
        $status = (string)$original->getData('status');
        if (in_array($status, [Document::STATUS_PENDING, Document::STATUS_QUEUED, Document::STATUS_FAILED], true)) {
            return new PrecheckResult(
                PrecheckResult::WAIT,
                (string)__('Waiting for the original invoice to be issued.')
            );
        }
        if (!$original->isIssued()) {
            return $this->blocked((string)__('The original invoice is not issued (status: %1).', $status));
        }
        foreach ($this->documentRepository->findCredits((int)$original->getId(), (int)$row->getId()) as $other) {
            if ((string)$other->getData('status') !== Document::STATUS_BLOCKED) {
                return $this->blocked((string)__(
                    'Not sent: this invoice already has a credit note, and Quaderno links only one per invoice'
                    . ' through the API. Create this refund as a credit note directly in Quaderno.'
                ), true);
            }
        }
        return null;
    }

    /**
     * Grand total of the original Magento invoice.
     *
     * @param Document $original
     * @return float
     */
    private function originalTotal(Document $original): float
    {
        try {
            return (float)$this->invoiceRepository->get((int)$original->getData('source_id'))->getGrandTotal();
        } catch (NoSuchEntityException $e) {
            return 0.0;
        }
    }

    /**
     * Checks on the mapped document: totals, NIF check letter, NIF required; IVA warning.
     *
     * @param DocumentData $data
     * @return PrecheckResult
     */
    private function checkData(DocumentData $data): PrecheckResult
    {
        if (abs($data->linesTotal() - $data->grandTotal) > 0.005) {
            return $this->blocked((string)__(
                'Not sent: %1 of this document is an adjustment, fee or gift card, which this version cannot'
                . ' send. Create the document in Quaderno, or leave the adjustment out (products and shipping'
                . ' only).',
                number_format(abs($data->grandTotal - $data->linesTotal()), 2, '.', '')
            ), true);
        }
        $address = $this->checkAddress($data);
        if ($address !== null) {
            return $address;
        }
        $customer = $data->customer;
        $taxId = $customer->taxId;
        if ($customer->country === 'ES' && $taxId !== null && !$this->nifValidator->isValid($taxId)) {
            return $this->blocked((string)__('The NIF %1 is not a valid Spanish NIF.', $taxId));
        }
        return $this->checkNifRequired($data)
            ?? new PrecheckResult(PrecheckResult::OK, null, $data, $this->warnings($data));
    }

    /**
     * Address fields Quaderno requires on the contact of a full invoice / credit note.
     *
     * Without them Quaderno keeps the document as a numberless draft ("Street line 1 can't be blank"
     * in its Inbox, sandbox 2026-11-04). Checked for sales and refunds; a refund's contact is
     * built from the same order address.
     *
     * @param DocumentData $data
     * @return PrecheckResult|null null = complete
     */
    private function checkAddress(DocumentData $data): ?PrecheckResult
    {
        $customer = $data->customer;
        $missing = [];
        foreach (['street' => $customer->street1, 'city' => $customer->city,
            'postcode' => $customer->postcode, 'country' => $customer->country] as $field => $value) {
            if (trim((string)$value) === '') {
                $missing[] = $field;
            }
        }
        if (!$missing) {
            return null;
        }
        return $this->blocked((string)__(
            'The billing address is incomplete (missing: %1). Quaderno needs street, city, postcode and'
            . ' country, otherwise it keeps the document as a draft without a number. Fix the order\'s'
            . ' billing address and resend.',
            implode(', ', $missing)
        ));
    }

    /**
     * A Spanish sale without NIF: blocked for Canarias / Ceuta / Melilla and from the threshold.
     *
     * @param DocumentData $data
     * @return PrecheckResult|null null = fine
     */
    private function checkNifRequired(DocumentData $data): ?PrecheckResult
    {
        $isSaleWithoutNif = $data->customer->country === 'ES'
            && $data->customer->taxId === null
            && $data->transactionType === DocumentData::TRANSACTION_SALE;
        if ($isSaleWithoutNif && $this->isZeroIvaTerritory($data)) {
            return $this->blocked((string)__(
                'NIF required: sales to Canarias, Ceuta and Melilla are exports and need a full invoice with'
                . ' the customer\'s NIF at any amount (RF art. 2.2.d). Add the NIF to the order\'s billing'
                . ' address (Address > Edit), then click Resend.'
            ));
        }
        if ($isSaleWithoutNif && $data->grandTotal >= $this->config->getThreshold($data->storeId)) {
            return $this->blocked((string)__(
                'NIF required: from %1 EUR a Spanish order needs a full invoice with the customer\'s NIF.'
                . ' Add the NIF to the order\'s billing address (Address > Edit), then click Resend.',
                number_format($this->config->getThreshold($data->storeId), 2, '.', '')
            ));
        }
        return null;
    }

    /**
     * Non-blocking notes.
     *
     * @param DocumentData $data
     * @return string[]
     */
    private function warnings(DocumentData $data): array
    {
        if (!$this->isZeroIvaTerritory($data)) {
            return [];
        }
        foreach ($data->lines as $line) {
            if ($line->taxRate > 0.0) {
                return [(string)__(
                    'IVA %1 %% charged to a Canarias / Ceuta / Melilla postcode: check the tax rules.',
                    $line->taxRate
                )];
            }
        }
        return [];
    }

    /**
     * Billing address in Canarias, Ceuta or Melilla (by postcode).
     *
     * @param DocumentData $data
     * @return bool
     */
    private function isZeroIvaTerritory(DocumentData $data): bool
    {
        return $data->customer->country === 'ES'
            && in_array(substr((string)$data->customer->postcode, 0, 2), self::ZERO_IVA_POSTCODES, true);
    }

    /**
     * Blocked result.
     *
     * @param string $reason
     * @param bool $permanent a resend cannot fix it (second credit note, adjustment)
     * @return PrecheckResult
     * @SuppressWarnings(PHPMD.BooleanArgumentFlag) the flag is stored on the result, not a code path
     */
    private function blocked(string $reason, bool $permanent = false): PrecheckResult
    {
        return new PrecheckResult(PrecheckResult::BLOCKED, $reason, null, [], $permanent);
    }
}
