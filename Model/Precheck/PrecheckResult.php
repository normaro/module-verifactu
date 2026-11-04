<?php
/**
 * Copyright (c) Iurii Tkachenko. Licensed under OSL-3.0, see LICENSE.
 */
declare(strict_types=1);

namespace Normaro\Verifactu\Model\Precheck;

use Normaro\Verifactu\Model\Provider\DocumentData;

/**
 * Outcome of the precheck: send, block (with a reason) or wait for the original invoice.
 */
class PrecheckResult
{
    public const OK = 'ok';
    public const BLOCKED = 'blocked';
    public const WAIT = 'wait';

    /**
     * @param string $outcome
     * @param string|null $reason
     * @param DocumentData|null $data mapped document when the outcome is OK
     * @param string[] $warnings non-blocking notes shown in the admin
     * @param bool $permanent blocked for a reason a resend cannot fix (no Resend button)
     * @SuppressWarnings(PHPMD.BooleanArgumentFlag) value object: the flag is data, not a code path
     */
    public function __construct(
        public readonly string $outcome,
        public readonly ?string $reason = null,
        public readonly ?DocumentData $data = null,
        public readonly array $warnings = [],
        public readonly bool $permanent = false
    ) {
    }
}
