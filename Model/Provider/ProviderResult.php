<?php
/**
 * Copyright (c) Iurii Tkachenko. Licensed under OSL-3.0, see LICENSE.
 */
declare(strict_types=1);

namespace Normaro\Verifactu\Model\Provider;

/**
 * State of a provider document after create / fetch.
 */
class ProviderResult
{
    /**
     * Value object; all fields are constructor arguments.
     *
     * @SuppressWarnings(PHPMD.ExcessiveParameterList)
     *
     * @param string $providerId
     * @param string $type invoice, receipt or credit
     * @param string $status one of Document::STATUS_*
     * @param string|null $number
     * @param string|null $aeatStatus AEAT EstadoRegistro, or the delivery state while pending
     * @param string|null $aeatMessage AEAT error code and description, if any
     * @param string|null $verificationPermalink
     * @param string|null $aeatQrUrl
     * @param string|null $pdfUrl
     * @param string|null $issueDate Y-m-d
     * @param string|null $contactId
     * @param string|null $relatedDocumentId original document of a credit
     * @param int|null $totalCents
     * @param string|null $processorId as stored by the provider
     * @param array<string,mixed> $raw
     */
    public function __construct(
        public readonly string $providerId,
        public readonly string $type,
        public readonly string $status,
        public readonly ?string $number = null,
        public readonly ?string $aeatStatus = null,
        public readonly ?string $aeatMessage = null,
        public readonly ?string $verificationPermalink = null,
        public readonly ?string $aeatQrUrl = null,
        public readonly ?string $pdfUrl = null,
        public readonly ?string $issueDate = null,
        public readonly ?string $contactId = null,
        public readonly ?string $relatedDocumentId = null,
        public readonly ?int $totalCents = null,
        public readonly ?string $processorId = null,
        public readonly array $raw = []
    ) {
    }
}
