<?php
/**
 * Copyright (c) Iurii Tkachenko. Licensed under OSL-3.0, see LICENSE.
 */
declare(strict_types=1);

namespace Normaro\Verifactu\Model\Mapper;

use Magento\Sales\Model\Order\Creditmemo;
use Normaro\Verifactu\Model\Document;
use Normaro\Verifactu\Model\Provider\CustomerData;
use Normaro\Verifactu\Model\Provider\DocumentData;

/**
 * Magento credit memo -> refund document linked to the original invoice's document.
 *
 * Quaderno links a refund to the original only by the original's processor ID and contact
 * (Quaderno sandbox); amounts are positive.
 */
class CreditmemoMapper
{
    /**
     * @param LineBuilder $lineBuilder
     * @param CustomerBuilder $customerBuilder
     * @param PaymentMethodMapper $paymentMethodMapper
     */
    public function __construct(
        private readonly LineBuilder $lineBuilder,
        private readonly CustomerBuilder $customerBuilder,
        private readonly PaymentMethodMapper $paymentMethodMapper
    ) {
    }

    /**
     * Map the credit memo against the row of its original invoice.
     *
     * @param Creditmemo $creditmemo
     * @param Document $original
     * @return DocumentData
     * @throws MappingException
     */
    public function map(Creditmemo $creditmemo, Document $original): DocumentData
    {
        $order = $creditmemo->getOrder();
        $payment = $order->getPayment();
        $customer = $this->customerBuilder->build($order);
        $contactId = (string)$original->getData('quaderno_contact_id');
        if ($contactId !== '') {
            $customer = new CustomerData(
                $customer->kind,
                $customer->firstName,
                $customer->lastName,
                $customer->country,
                $customer->taxId,
                $customer->street1,
                $customer->street2,
                $customer->city,
                $customer->postcode,
                $customer->region,
                $customer->email,
                $contactId
            );
        }
        return new DocumentData(
            DocumentData::TRANSACTION_REFUND,
            (string)$original->getData('processor_id'),
            (string)$order->getOrderCurrencyCode(),
            $customer,
            $this->lineBuilder->build($creditmemo, $order),
            round((float)$creditmemo->getGrandTotal(), 2),
            (int)$order->getStoreId(),
            $this->paymentMethodMapper->map($payment ? (string)$payment->getMethod() : ''),
            (string)$order->getIncrementId(),
            $this->createdAt($original)
        );
    }

    /**
     * Unix time the original's row was created (UTC), or null.
     *
     * @param Document $original
     * @return int|null
     */
    private function createdAt(Document $original): ?int
    {
        $value = (string)$original->getData('created_at');
        $time = $value !== '' ? strtotime($value . ' UTC') : false;
        return $time === false ? null : $time;
    }
}
