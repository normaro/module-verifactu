<?php
/**
 * Copyright (c) Iurii Tkachenko. Licensed under OSL-3.0, see LICENSE.
 */
declare(strict_types=1);

namespace Normaro\Verifactu\Test\Unit\Model\Quaderno;

/**
 * Masked Quaderno sandbox responses (account data removed).
 */
class Fixture
{
    /**
     * Decoded fixture file from Test/Unit/_files/quaderno.
     *
     * @param string $name
     * @return array<mixed>
     */
    public static function load(string $name): array
    {
        // phpcs:ignore Magento2.Functions.DiscouragedFunction -- test fixture read
        $json = file_get_contents(__DIR__ . '/../../_files/quaderno/' . $name);
        $data = json_decode((string)$json, true, 512, JSON_THROW_ON_ERROR);
        return is_array($data) ? $data : [];
    }
}
