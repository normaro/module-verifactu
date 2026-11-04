<?php
/**
 * Copyright (c) Iurii Tkachenko. Licensed under OSL-3.0, see LICENSE.
 */
declare(strict_types=1);

namespace Normaro\Verifactu\Model\Provider\Exception;

/**
 * The provider already has a document with this processor ID (422 "has already been taken").
 */
class DuplicateException extends PermanentException
{
}
