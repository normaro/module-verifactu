<?php
/**
 * Copyright (c) Iurii Tkachenko. Licensed under OSL-3.0, see LICENSE.
 */
declare(strict_types=1);

namespace Normaro\Verifactu\Model;

use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Sales\Api\CreditmemoRepositoryInterface;
use Magento\Sales\Api\InvoiceRepositoryInterface;
use Magento\Sales\Model\Order;
use Magento\Sales\Model\Order\Creditmemo;
use Magento\Sales\Model\Order\Invoice;
use Normaro\Verifactu\Model\Precheck\Precheck;
use Normaro\Verifactu\Model\Precheck\PrecheckResult;

/**
 * Load the Magento invoice / credit memo of a row and run the precheck on it.
 */
class SourceCheck
{
    /**
     * @param Precheck $precheck
     * @param InvoiceRepositoryInterface $invoiceRepository
     * @param CreditmemoRepositoryInterface $creditmemoRepository
     */
    public function __construct(
        private readonly Precheck $precheck,
        private readonly InvoiceRepositoryInterface $invoiceRepository,
        private readonly CreditmemoRepositoryInterface $creditmemoRepository
    ) {
    }

    /**
     * Precheck result and order of the row's Magento document.
     *
     * @param Document $row
     * @return array{0:PrecheckResult,1:Order}
     * @throws NoSuchEntityException
     */
    public function check(Document $row): array
    {
        $sourceId = (int)$row->getData('source_id');
        if ($row->getData('source_type') === Document::SOURCE_CREDITMEMO) {
            /** @var Creditmemo $creditmemo */
            $creditmemo = $this->creditmemoRepository->get($sourceId);
            return [$this->precheck->checkCreditmemo($creditmemo, $row), $creditmemo->getOrder()];
        }
        /** @var Invoice $invoice */
        $invoice = $this->invoiceRepository->get($sourceId);
        return [$this->precheck->checkInvoice($invoice), $invoice->getOrder()];
    }
}
