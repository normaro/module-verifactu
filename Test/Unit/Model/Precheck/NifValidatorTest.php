<?php
/**
 * Copyright (c) Iurii Tkachenko. Licensed under OSL-3.0, see LICENSE.
 */
declare(strict_types=1);

namespace Normaro\Verifactu\Test\Unit\Model\Precheck;

use PHPUnit\Framework\TestCase;
use Normaro\Verifactu\Model\Precheck\NifValidator;

/**
 * Spanish NIF check characters.
 */
class NifValidatorTest extends TestCase
{
    /**
     * Valid and invalid examples (as tried in the Quaderno sandbox: 12345678Z / 12345678A).
     *
     * @dataProvider cases
     * @param string $nif
     * @param bool $valid
     * @return void
     */
    public function testIsValid(string $nif, bool $valid): void
    {
        $this->assertSame($valid, (new NifValidator())->isValid($nif));
    }

    /**
     * Examples.
     *
     * @return array<string,array{0:string,1:bool}>
     */
    public static function cases(): array
    {
        return [
            'DNI valid' => ['12345678Z', true],
            'DNI wrong letter (draft at Quaderno)' => ['12345678A', false],
            'DNI valid 2' => ['00000000T', true],
            'NIE X valid' => ['X1234567L', true],
            'NIE Y valid' => ['Y1234567X', true],
            'NIE Z valid' => ['Z1234567R', true],
            'NIE wrong letter' => ['X1234567A', false],
            'entity B digit control valid' => ['B12345674', true],
            'entity B with letter control' => ['B1234567D', false],
            'entity A valid' => ['A58818501', true],
            'entity P letter control valid' => ['P1234567D', true],
            'entity Q digit control invalid' => ['Q12345674', false],
            'too short' => ['1234567Z', false],
            'garbage' => ['ABC', false],
            'empty' => ['', false],
        ];
    }
}
