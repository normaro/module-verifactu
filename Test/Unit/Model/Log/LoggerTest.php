<?php
/**
 * Copyright (c) Iurii Tkachenko. Licensed under OSL-3.0, see LICENSE.
 */
declare(strict_types=1);

namespace Normaro\Verifactu\Test\Unit\Model\Log;

use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Normaro\Verifactu\Model\Config;
use Normaro\Verifactu\Model\Log\Logger;
use Normaro\Verifactu\Model\Log\Masker;

/**
 * Debug off: errors and status changes only. Debug on: requests too. Always masked.
 */
class LoggerTest extends TestCase
{
    /**
     * @var LoggerInterface&MockObject
     */
    private LoggerInterface $file;

    /**
     * Logger with debug logging on or off.
     *
     * @param bool $debug
     * @return Logger
     */
    private function logger(bool $debug): Logger
    {
        $this->file = $this->createMock(LoggerInterface::class);
        $config = $this->createMock(Config::class);
        $config->method('isDebug')->willReturn($debug);
        return new Logger($this->file, new Masker(), $config);
    }

    /**
     * Debug off: requests are not written.
     *
     * @return void
     */
    public function testExchangeIsNotLoggedWithoutDebug(): void
    {
        $logger = $this->logger(false);
        $this->file->expects($this->never())->method('debug');

        $logger->exchange(1, ['method' => 'GET', 'url' => 'https://x/api/invoices'], ['status' => 200], 10.0);
    }

    /**
     * Debug on: method, URL, status, duration and x-request-id; body with the NIF masked.
     *
     * @return void
     */
    public function testExchangeIsLoggedMaskedWithDebug(): void
    {
        $logger = $this->logger(true);
        $this->file->expects($this->once())->method('debug')->with(
            'Quaderno POST https://x/api/transactions -> HTTP 201 in 412 ms (x-request-id abc-1)',
            $this->callback(static function (array $context): bool {
                $encoded = (string)json_encode($context);
                return !str_contains($encoded, '12345678Z')
                    && str_contains($encoded, '******78Z')
                    && $context['request']['headers']['Authorization'] === 'Basic [not logged]';
            })
        );

        $logger->exchange(
            1,
            [
                'method' => 'POST',
                'url' => 'https://x/api/transactions',
                'headers' => ['Authorization' => 'Basic [not logged]'],
                'body' => '{"customer":{"tax_id":"12345678Z"}}',
            ],
            ['status' => 201, 'x_request_id' => 'abc-1', 'body' => '{"contact":{"tax_id":"12345678Z"}}'],
            411.6
        );
    }

    /**
     * Errors are written in every mode, masked.
     *
     * @return void
     */
    public function testErrorIsAlwaysLoggedMasked(): void
    {
        $logger = $this->logger(false);
        $this->file->expects($this->once())->method('error')
            ->with('Send failed: The NIF ******78A is not a valid Spanish NIF.', ['row' => 3]);

        $logger->error('Send failed: The NIF 12345678A is not a valid Spanish NIF.', ['row' => 3]);
    }

    /**
     * Status changes are written in every mode; no line when the status did not change.
     *
     * @return void
     */
    public function testStatusChangeIsAlwaysLogged(): void
    {
        $logger = $this->logger(false);
        $this->file->expects($this->once())->method('info')
            ->with('Document 000000012 (row 3): queued -> issued_with_warning: F2026-00007 AceptadoConErrores');

        $logger->status(3, '000000012', 'queued', 'issued_with_warning', 'F2026-00007 AceptadoConErrores');
        $logger->status(3, '000000012', 'issued', 'issued');
    }
}
