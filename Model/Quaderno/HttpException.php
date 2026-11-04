<?php
/**
 * Copyright (c) Iurii Tkachenko. Licensed under OSL-3.0, see LICENSE.
 */
declare(strict_types=1);

namespace Normaro\Verifactu\Model\Quaderno;

use Magento\Framework\Phrase;
use Normaro\Verifactu\Model\Provider\Exception\PermanentException;

/**
 * 4xx response from Quaderno, with the status and the decoded error body.
 */
class HttpException extends PermanentException
{
    /**
     * @param Phrase $phrase
     * @param \Exception|null $cause
     * @param int $code
     * @param int $httpStatus
     * @param array<mixed> $body
     */
    public function __construct(
        Phrase $phrase,
        ?\Exception $cause = null,
        int $code = 0,
        private readonly int $httpStatus = 0,
        private readonly array $body = []
    ) {
        parent::__construct($phrase, $cause, $code);
    }

    /**
     * HTTP status.
     *
     * @return int
     */
    public function getHttpStatus(): int
    {
        return $this->httpStatus;
    }

    /**
     * Decoded error body, e.g. ['errors' => ['processor_id' => ['has already been taken']]].
     *
     * @return array<mixed>
     */
    public function getBody(): array
    {
        return $this->body;
    }

    /**
     * 422 because the processor ID already exists (Quaderno sandbox).
     *
     * @return bool
     */
    public function isProcessorIdTaken(): bool
    {
        $errors = $this->body['errors']['processor_id'] ?? [];
        return $this->httpStatus === 422
            && is_array($errors)
            && in_array('has already been taken', $errors, true);
    }
}
