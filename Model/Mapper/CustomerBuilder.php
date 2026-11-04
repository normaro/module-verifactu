<?php
/**
 * Copyright (c) Iurii Tkachenko. Licensed under OSL-3.0, see LICENSE.
 */
declare(strict_types=1);

namespace Normaro\Verifactu\Model\Mapper;

use Magento\Sales\Api\Data\OrderAddressInterface;
use Magento\Sales\Model\Order;
use Normaro\Verifactu\Model\Config;
use Normaro\Verifactu\Model\Provider\CustomerData;
use Normaro\Verifactu\Model\ResourceModel\CustomerContact;

/**
 * Customer of a document from the order's billing address.
 */
class CustomerBuilder
{
    /** Name used when a B2C order has no name at all */
    public const FALLBACK_NAME = 'Cliente';

    /**
     * @param Config $config
     * @param CustomerContact $customerContact
     */
    public function __construct(
        private readonly Config $config,
        private readonly CustomerContact $customerContact
    ) {
    }

    /**
     * Build the customer; reuses the stored provider contact of a registered customer.
     *
     * @param Order $order
     * @return CustomerData
     * @throws MappingException
     */
    public function build(Order $order): CustomerData
    {
        $address = $order->getBillingAddress();
        if (!$address instanceof OrderAddressInterface) {
            throw new MappingException(__('Order %1 has no billing address.', $order->getIncrementId()));
        }
        $country = strtoupper((string)$address->getCountryId());
        [$kind, $first, $last] = $this->name($address);
        $street = array_values((array)$address->getStreet());
        return new CustomerData(
            $kind,
            $first,
            $last,
            $country,
            $this->taxId($order, $country),
            $this->nullable($street[0] ?? null),
            $this->nullable($street[1] ?? null),
            $this->nullable($address->getCity()),
            $this->nullable($address->getPostcode()),
            $this->nullable($address->getRegion()),
            $this->nullable($order->getCustomerEmail()),
            $this->contactId($order)
        );
    }

    /**
     * Kind and name: a company when the billing address has one.
     *
     * @param OrderAddressInterface $address
     * @return array{0:string,1:string,2:string}
     */
    private function name(OrderAddressInterface $address): array
    {
        $company = trim((string)$address->getCompany());
        if ($company !== '') {
            return [CustomerData::KIND_COMPANY, $company, ''];
        }
        $first = trim((string)$address->getFirstname());
        $last = trim((string)$address->getLastname());
        if ($first === '' && $last === '') {
            $first = self::FALLBACK_NAME;
        }
        return [CustomerData::KIND_PERSON, $first, $last];
    }

    /**
     * Stored provider contact of a registered customer.
     *
     * @param Order $order
     * @return string|null
     */
    private function contactId(Order $order): ?string
    {
        $customerId = (int)$order->getCustomerId();
        if ($customerId <= 0 || $order->getCustomerIsGuest()) {
            return null;
        }
        return $this->customerContact->get($customerId, $this->config->getEnvironment((int)$order->getStoreId()));
    }

    /**
     * Trimmed string or null.
     *
     * @param mixed $value
     * @return string|null
     */
    private function nullable(mixed $value): ?string
    {
        $value = is_scalar($value) ? trim((string)$value) : '';
        return $value === '' ? null : $value;
    }

    /**
     * NIF / VAT number of the order, normalised.
     *
     * Billing vat_id, else the customer's taxvat; without spaces, dots or dashes; a Spanish
     * number without the "ES" prefix.
     *
     * @param Order $order
     * @param string $country
     * @return string|null
     */
    public function taxId(Order $order, string $country): ?string
    {
        $address = $order->getBillingAddress();
        $raw = $address instanceof OrderAddressInterface ? (string)$address->getVatId() : '';
        if (trim($raw) === '') {
            $raw = (string)$order->getCustomerTaxvat();
        }
        $value = strtoupper((string)preg_replace('/[\s.\-]/', '', $raw));
        if ($value === '') {
            return null;
        }
        if ($country === 'ES' && str_starts_with($value, 'ES') && strlen($value) === 11) {
            $value = substr($value, 2);
        }
        return $value;
    }
}
