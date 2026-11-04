<?php
/**
 * Copyright (c) Iurii Tkachenko. Licensed under OSL-3.0, see LICENSE.
 */
declare(strict_types=1);

namespace Normaro\Verifactu\Observer;

use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Magento\Sales\Model\Order\Invoice;
use Psr\Log\LoggerInterface;
use Normaro\Verifactu\Model\Config;
use Normaro\Verifactu\Model\Scheduler;

/**
 * sales_order_invoice_save_commit_after: queue the invoice (after the DB commit only).
 *
 * In "on capture" mode only paid invoices are queued; an open invoice is queued by the save
 * that marks it paid. One row per invoice (unique key), so repeated saves do nothing.
 */
class InvoiceSaveCommitAfter implements ObserverInterface
{
    /**
     * @param Config $config
     * @param Scheduler $scheduler
     * @param LoggerInterface $logger
     */
    public function __construct(
        private readonly Config $config,
        private readonly Scheduler $scheduler,
        private readonly LoggerInterface $logger
    ) {
    }

    /**
     * @inheritdoc
     */
    public function execute(Observer $observer)
    {
        $invoice = $observer->getEvent()->getData('invoice');
        if (!$invoice instanceof Invoice || !$this->config->isEnabled((int)$invoice->getStoreId())) {
            return;
        }
        if ($this->config->getTrigger((int)$invoice->getStoreId()) === Config::TRIGGER_CAPTURE
            && (int)$invoice->getState() !== Invoice::STATE_PAID
        ) {
            return;
        }
        try {
            $this->scheduler->scheduleInvoice($invoice);
        } catch (\Throwable $e) {
            // Never break the invoice save in the admin / checkout.
            $this->logger->critical('Verifactu: cannot queue invoice ' . $invoice->getIncrementId() . ': '
                . $e->getMessage());
        }
    }
}
