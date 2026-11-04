<?php
/**
 * Copyright (c) Iurii Tkachenko. Licensed under OSL-3.0, see LICENSE.
 */
declare(strict_types=1);

namespace Normaro\Verifactu\Model\Log;

use Psr\Log\LoggerInterface;
use Normaro\Verifactu\Model\Config;

/**
 * Module log, var/log/verifactu.log. Always: errors and status changes. Debug logging on: every
 * Quaderno request and response. Tax IDs are masked in every mode; the API key is never passed in.
 */
class Logger
{
    /**
     * @param LoggerInterface $logger file logger of this module (di.xml)
     * @param Masker $masker
     * @param Config $config
     */
    public function __construct(
        private readonly LoggerInterface $logger,
        private readonly Masker $masker,
        private readonly Config $config
    ) {
    }

    /**
     * Debug logging enabled for the store.
     *
     * @param int $storeId
     * @return bool
     */
    public function isDebug(int $storeId): bool
    {
        return $this->config->isDebug($storeId);
    }

    /**
     * One Quaderno call (debug logging only).
     *
     * @param int $storeId
     * @param array<string,mixed> $request method, url, headers (without the key), body
     * @param array<string,mixed> $response status, x_request_id, headers, body
     * @param float $milliseconds
     * @return void
     */
    public function exchange(int $storeId, array $request, array $response, float $milliseconds): void
    {
        if (!$this->isDebug($storeId)) {
            return;
        }
        $this->logger->debug(
            $this->masker->maskString(sprintf(
                'Quaderno %s %s -> HTTP %d in %d ms (x-request-id %s)',
                (string)($request['method'] ?? ''),
                (string)($request['url'] ?? ''),
                (int)($response['status'] ?? 0),
                (int)round($milliseconds),
                (string)($response['x_request_id'] ?? '-')
            )),
            $this->masker->maskArray(['request' => $request, 'response' => $response])
        );
    }

    /**
     * Status change of a document row.
     *
     * @param int|string|null $rowId
     * @param string $processorId
     * @param string $from
     * @param string $to
     * @param string|null $note
     * @return void
     */
    public function status($rowId, string $processorId, string $from, string $to, ?string $note = null): void
    {
        if ($from === $to) {
            return;
        }
        $this->logger->info(
            $this->masker->maskString(sprintf(
                'Document %s (row %s): %s -> %s%s',
                $processorId,
                (string)$rowId,
                $from !== '' ? $from : 'new',
                $to,
                $note !== null && $note !== '' ? ': ' . $note : ''
            ))
        );
    }

    /**
     * Error, always logged.
     *
     * @param string $message
     * @param array<mixed> $context
     * @return void
     */
    public function error(string $message, array $context = []): void
    {
        $this->logger->error($this->masker->maskString($message), $this->masker->maskArray($context));
    }
}
