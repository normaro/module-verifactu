<?php
/**
 * Copyright (c) Iurii Tkachenko. Licensed under OSL-3.0, see LICENSE.
 */
declare(strict_types=1);

namespace Normaro\Verifactu\Model;

use Magento\Sales\Model\Order;
use Normaro\Verifactu\Model\Provider\DocumentData;
use Normaro\Verifactu\Model\Provider\ProviderResult;
use Normaro\Verifactu\Model\ResourceModel\CustomerContact;

/**
 * Remembers the provider contact of a registered customer.
 */
class ContactRecorder
{
    /**
     * @param CustomerContact $customerContact
     * @param Config $config
     */
    public function __construct(
        private readonly CustomerContact $customerContact,
        private readonly Config $config
    ) {
    }

    /**
     * Store the contact created for this order's registered customer.
     *
     * @param Order $order
     * @param DocumentData $data
     * @param ProviderResult $result
     * @return void
     */
    public function record(Order $order, DocumentData $data, ProviderResult $result): void
    {
        $customerId = (int)$order->getCustomerId();
        if ($customerId <= 0 || $order->getCustomerIsGuest() || $result->contactId === null
            || $data->customer->contactId !== null
        ) {
            return;
        }
        $this->customerContact->save(
            $customerId,
            $this->config->getEnvironment((int)$order->getStoreId()),
            $result->contactId
        );
    }
}
