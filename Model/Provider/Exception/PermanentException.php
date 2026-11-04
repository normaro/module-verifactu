<?php
/**
 * Copyright (c) Iurii Tkachenko. Licensed under OSL-3.0, see LICENSE.
 */
declare(strict_types=1);

namespace Normaro\Verifactu\Model\Provider\Exception;

/**
 * Rejected request (validation, 4xx, configuration): retrying the same data will not help.
 */
class PermanentException extends ProviderException
{
}
