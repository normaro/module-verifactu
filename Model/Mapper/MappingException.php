<?php
/**
 * Copyright (c) Iurii Tkachenko. Licensed under OSL-3.0, see LICENSE.
 */
declare(strict_types=1);

namespace Normaro\Verifactu\Model\Mapper;

use Magento\Framework\Exception\LocalizedException;

/**
 * The Magento document cannot be mapped (missing tax mapping, unknown data): the row is blocked.
 */
class MappingException extends LocalizedException
{
}
