<?php
/**
 * Copyright (c) Iurii Tkachenko. Licensed under OSL-3.0, see LICENSE.
 */
declare(strict_types=1);

namespace Normaro\Verifactu\Model\Provider\Exception;

/**
 * Temporary failure (network, timeout, 5xx, 429): the document is retried.
 */
class TransientException extends ProviderException
{
}
