<?php
/**
 * Copyright (c) Iurii Tkachenko. Licensed under OSL-3.0, see LICENSE.
 */
declare(strict_types=1);

namespace Normaro\Verifactu\Model\Precheck;

/**
 * Format and check character of a Spanish NIF (DNI, NIE, entity NIF, K/L/M).
 *
 * Quaderno sandbox: a NIF with a wrong check letter leaves a numberless draft at Quaderno with no
 * reason, so the module rejects it before sending.
 */
class NifValidator
{
    private const DNI_LETTERS = 'TRWAGMYFPDXBNJZSQVHLCKE';
    private const ENTITY_CONTROL_LETTERS = 'JABCDEFGHI';
    /** Entities whose control character is always a letter */
    private const ENTITY_LETTER_ONLY = 'KPQRSNW';
    /** Entities whose control character is always a digit */
    private const ENTITY_DIGIT_ONLY = 'ABEH';

    /**
     * Whether the value is a valid Spanish NIF.
     *
     * @param string $nif normalised: upper case, no spaces, no "ES" prefix
     * @return bool
     */
    public function isValid(string $nif): bool
    {
        if (preg_match('/^(\d{8})([A-Z])$/', $nif, $m)) {
            return $this->dniLetter((int)$m[1]) === $m[2];
        }
        if (preg_match('/^([XYZ])(\d{7})([A-Z])$/', $nif, $m)) {
            $number = (int)(strpos('XYZ', $m[1]) . $m[2]);
            return $this->dniLetter($number) === $m[3];
        }
        if (preg_match('/^([KLM])(\d{7})([A-Z])$/', $nif, $m)) {
            return $this->dniLetter((int)$m[2]) === $m[3];
        }
        if (preg_match('/^([ABCDEFGHJNPQRSUVW])(\d{7})([0-9A-J])$/', $nif, $m)) {
            return $this->entityControlMatches($m[1], $m[2], $m[3]);
        }
        return false;
    }

    /**
     * DNI / NIE check letter.
     *
     * @param int $number
     * @return string
     */
    private function dniLetter(int $number): string
    {
        return self::DNI_LETTERS[$number % 23];
    }

    /**
     * Control character of an entity NIF (former CIF).
     *
     * @param string $letter
     * @param string $digits seven digits
     * @param string $control
     * @return bool
     */
    private function entityControlMatches(string $letter, string $digits, string $control): bool
    {
        $sum = 0;
        for ($i = 0; $i < 7; $i++) {
            $digit = (int)$digits[$i];
            if ($i % 2 === 0) {
                $double = $digit * 2;
                $sum += intdiv($double, 10) + $double % 10;
            } else {
                $sum += $digit;
            }
        }
        $value = (10 - $sum % 10) % 10;
        $asDigit = (string)$value;
        $asLetter = self::ENTITY_CONTROL_LETTERS[$value];
        if (str_contains(self::ENTITY_LETTER_ONLY, $letter)) {
            return $control === $asLetter;
        }
        if (str_contains(self::ENTITY_DIGIT_ONLY, $letter)) {
            return $control === $asDigit;
        }
        return $control === $asDigit || $control === $asLetter;
    }
}
