<?php
/**
 * Copyright (c) Iurii Tkachenko. Licensed under OSL-3.0, see LICENSE.
 */
declare(strict_types=1);

namespace Normaro\Verifactu\Model\Quaderno;

use Normaro\Verifactu\Model\Provider\CustomerData;
use Normaro\Verifactu\Model\Provider\DocumentData;
use Normaro\Verifactu\Model\Provider\LineData;

/**
 * DocumentData -> body of POST /transactions (api_version 20241028).
 *
 * Never sends "date" or "issue_date": the provider sets the issue date (the
 * Quaderno sandbox: a past date is accepted, so sending one would be wrong).
 */
class PayloadBuilder
{
    public const PROCESSOR = 'magento';

    /**
     * Build the request body.
     *
     * @param DocumentData $data
     * @return array<string,mixed>
     */
    public function build(DocumentData $data): array
    {
        $body = [
            'type' => $data->transactionType,
            'currency' => $data->currency,
            'processor' => self::PROCESSOR,
            'processor_id' => $data->processorId,
            'customer' => $data->transactionType === DocumentData::TRANSACTION_REFUND
                ? $this->refundCustomer($data->customer)
                : $this->customer($data->customer),
            'items' => array_map(fn (LineData $line): array => $this->item($line), $data->lines),
            'payment' => ['method' => $data->paymentMethod],
        ];
        if ($data->poNumber !== null && $data->poNumber !== '') {
            $body['po_number'] = $data->poNumber;
        }
        if ($data->transactionType === DocumentData::TRANSACTION_SALE) {
            $body['evidence'] = ['billing_country' => $data->customer->country];
        }
        return $body;
    }

    /**
     * Full customer data of a sale.
     *
     * @param CustomerData $customer
     * @return array<string,string>
     */
    private function customer(CustomerData $customer): array
    {
        $fields = [
            'id' => $customer->contactId,
            'kind' => $customer->kind,
            'first_name' => $customer->firstName,
            'last_name' => $customer->kind === CustomerData::KIND_COMPANY ? '' : $customer->lastName,
            'tax_id' => $customer->taxId,
            'street_line_1' => $customer->street1,
            'street_line_2' => $customer->street2,
            'city' => $customer->city,
            'postal_code' => $customer->postcode,
            'region' => $customer->region,
            'country' => $customer->country,
            'email' => $customer->email,
        ];
        return array_filter($fields, static fn ($value) => $value !== null && $value !== '');
    }

    /**
     * A refund carries only the original's contact (Quaderno sandbox; as the official WooCommerce plugin).
     *
     * @param CustomerData $customer
     * @return array<string,string>
     */
    private function refundCustomer(CustomerData $customer): array
    {
        if ($customer->contactId !== null && $customer->contactId !== '') {
            return ['id' => $customer->contactId];
        }
        return $this->customer($customer);
    }

    /**
     * One item: amount = line total incl. IVA after discount; IVA always explicit (Quaderno sandbox).
     *
     * @param LineData $line
     * @return array<string,mixed>
     */
    private function item(LineData $line): array
    {
        $item = [
            'description' => $line->description,
            'quantity' => $line->quantity,
            'amount' => round($line->amount, 2),
            'tax' => [
                'country' => 'ES',
                'name' => 'IVA',
                'rate' => $line->taxRate,
                'tax_code' => $line->taxCode,
            ],
        ];
        if ($line->sku !== null && $line->sku !== '') {
            $item['product_code'] = $line->sku;
        }
        return $item;
    }
}
