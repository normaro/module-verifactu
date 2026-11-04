<?php
/**
 * Copyright (c) Iurii Tkachenko. Licensed under OSL-3.0, see LICENSE.
 */
declare(strict_types=1);

namespace Normaro\Verifactu\Observer;

use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Magento\Sales\Model\Order\Creditmemo;
use Psr\Log\LoggerInterface;
use Normaro\Verifactu\Model\Config;
use Normaro\Verifactu\Model\Scheduler;

/**
 * sales_order_creditmemo_save_commit_after: queue the credit memo (always, except cancelled).
 */
class CreditmemoSaveCommitAfter implements ObserverInterface
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
        $creditmemo = $observer->getEvent()->getData('creditmemo');
        if (!$creditmemo instanceof Creditmemo || !$this->config->isEnabled((int)$creditmemo->getStoreId())) {
            return;
        }
        if ((int)$creditmemo->getState() === Creditmemo::STATE_CANCELED) {
            return;
        }
        try {
            $this->scheduler->scheduleCreditmemo($creditmemo);
        } catch (\Throwable $e) {
            $this->logger->critical('Verifactu: cannot queue credit memo ' . $creditmemo->getIncrementId() . ': '
                . $e->getMessage());
        }
    }
}
