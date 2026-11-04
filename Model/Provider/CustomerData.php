<?php
/**
 * Copyright (c) Iurii Tkachenko. Licensed under OSL-3.0, see LICENSE.
 */
declare(strict_types=1);

namespace Normaro\Verifactu\Model\Provider;

/**
 * Customer of a document, provider-neutral.
 */
class CustomerData
{
    public const KIND_PERSON = 'person';
    public const KIND_COMPANY = 'company';

    /**
     * Value object; all fields are constructor arguments.
     *
     * @SuppressWarnings(PHPMD.ExcessiveParameterList)
     *
     * @param string $kind person or company
     * @param string $firstName person first name, or the company name
     * @param string $lastName
     * @param string $country ISO 3166-1 alpha-2
     * @param string|null $taxId NIF / VAT number without spaces
     * @param string|null $street1
     * @param string|null $street2
     * @param string|null $city
     * @param string|null $postcode
     * @param string|null $region
     * @param string|null $email
     * @param string|null $contactId existing provider contact
     */
    public function __construct(
        public readonly string $kind,
        public readonly string $firstName,
        public readonly string $lastName,
        public readonly string $country,
        public readonly ?string $taxId = null,
        public readonly ?string $street1 = null,
        public readonly ?string $street2 = null,
        public readonly ?string $city = null,
        public readonly ?string $postcode = null,
        public readonly ?string $region = null,
        public readonly ?string $email = null,
        public readonly ?string $contactId = null
    ) {
    }
}
