<?php
/**
 * Copyright (c) Iurii Tkachenko. Licensed under OSL-3.0, see LICENSE.
 */
declare(strict_types=1);

namespace Normaro\Verifactu\Model\Log;

/**
 * Masks tax IDs (NIF / NIE / CIF, EU VAT numbers) in log messages and context, in every log mode.
 */
class Masker
{
    /** Keys whose values are tax IDs, at any depth of the context */
    private const TAX_ID_KEYS = ['tax_id', 'vat_number', 'vat_id', 'taxvat', 'nif', 'tax_number'];

    /** Characters left visible at the end of a masked tax ID */
    private const VISIBLE = 3;

    /**
     * Spanish DNI (8 digits + letter), NIE (X/Y/Z + 7 digits + letter), entity NIF (letter + 7 digits +
     * check), K/L/M NIF, and EU VAT numbers (country prefix + 8–12 characters), with an optional ES prefix.
     */
    private const TAX_ID_PATTERN = '/(?<![A-Za-z0-9])(?:'
        . '(?:ES)?[0-9]{8}[A-Z]'
        . '|(?:ES)?[XYZKLM][0-9]{7}[A-Z]'
        . '|(?:ES)?[ABCDEFGHJNPQRSUVW][0-9]{7}[0-9A-J]'
        . '|(?:AT|BE|BG|CY|CZ|DE|DK|EE|EL|FI|FR|HR|HU|IE|IT|LT|LU|LV|MT|NL|PL|PT|RO|SE|SI|SK|XI)[0-9A-Z]{8,12}'
        . ')(?![A-Za-z0-9])/';

    /**
     * Mask tax IDs in a string (also inside JSON bodies).
     *
     * @param string $text
     * @return string
     */
    public function maskString(string $text): string
    {
        return (string)preg_replace_callback(
            self::TAX_ID_PATTERN,
            fn (array $match): string => $this->maskValue($match[0]),
            $text
        );
    }

    /**
     * Mask a context array: tax-ID keys always, every string by pattern.
     *
     * @param array<mixed> $data
     * @return array<mixed>
     */
    public function maskArray(array $data): array
    {
        foreach ($data as $key => $value) {
            if (is_array($value)) {
                $data[$key] = $this->maskArray($value);
            } elseif (is_string($value)) {
                $data[$key] = in_array(strtolower((string)$key), self::TAX_ID_KEYS, true) && $value !== ''
                    ? $this->maskValue($value)
                    : $this->maskString($value);
            }
        }
        return $data;
    }

    /**
     * "12345678Z" -> "******78Z".
     *
     * @param string $value
     * @return string
     */
    private function maskValue(string $value): string
    {
        $length = strlen($value);
        $visible = $length > self::VISIBLE ? self::VISIBLE : 0;
        return str_repeat('*', $length - $visible) . substr($value, $length - $visible);
    }
}
