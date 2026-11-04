<?php
/**
 * Copyright (c) Iurii Tkachenko. Licensed under OSL-3.0, see LICENSE.
 */
declare(strict_types=1);

namespace Normaro\Verifactu\Model\Queue;

use Normaro\Verifactu\Model\Log\Logger;
use Normaro\Verifactu\Model\Sender;

/**
 * Consumer of verifactu.document.send (message = verifactu_document.entity_id).
 */
class Consumer
{
    /**
     * @param Sender $sender
     * @param Logger $logger
     */
    public function __construct(
        private readonly Sender $sender,
        private readonly Logger $logger
    ) {
    }

    /**
     * Handle one message; never throws, so the message is not re-delivered in a loop.
     *
     * @param string $documentId
     * @return void
     */
    public function process(string $documentId): void
    {
        try {
            $this->sender->process((int)$documentId);
        } catch (\Throwable $e) {
            $this->logger->error('Consumer error: ' . $e->getMessage(), ['row' => $documentId]);
        }
    }
}
