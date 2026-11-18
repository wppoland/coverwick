<?php

declare(strict_types=1);

namespace Coverage\Tests;

use Coverage\Support\Calculator;
use PHPUnit\Framework\TestCase;

final class CalculatorTest extends TestCase
{
    public function testExactMultipleDoesNotOvershootOnFloatNoise(): void
    {
        // 7.6 / 0.76 is 10.000000000000002 in floating point.
        self::assertSame(10, Calculator::piecesFor(7.6, 0.76));
        self::assertSame(10, Calculator::piecesFor(Calculator::areaFor(10, 0.76), 0.76));
        self::assertSame(3, Calculator::piecesFor(0.3, 0.1));
    }

    public function testRoundsUpToWholePiece(): void
    {
        self::assertSame(10, Calculator::piecesFor(7.5, 0.76));
        self::assertSame(11, Calculator::piecesFor(7.61, 0.76));
        self::assertSame(1, Calculator::piecesFor(0.01, 0.76));
    }

    public function testZeroOrNegativeInputsGiveZero(): void
    {
        self::assertSame(0, Calculator::piecesFor(0.0, 0.76));
        self::assertSame(0, Calculator::piecesFor(5.0, 0.0));
        self::assertSame(0.0, Calculator::areaFor(0, 0.76));
        self::assertSame(0.0, Calculator::areaFor(4, -1.0));
    }

    public function testAreaForPieces(): void
    {
        self::assertSame(7.6, Calculator::areaFor(10, 0.76));
        self::assertSame(0.3, Calculator::areaFor(3, 0.1));
    }

    public function testPricePerArea(): void
    {
        self::assertSame(100.0, Calculator::pricePerArea(76.0, 0.76));
        self::assertSame(261.842105, Calculator::pricePerArea(199.0, 0.76));
        self::assertNull(Calculator::pricePerArea(10.0, 0.0));
        self::assertSame(0.0, Calculator::pricePerArea(0.0, 0.5));
    }

    public function testVariationFallsBackToParent(): void
    {
        self::assertSame(0.5, Calculator::resolvePerUnit(0.5, 0.76));
        self::assertSame(0.76, Calculator::resolvePerUnit(0.0, 0.76));
        self::assertSame(0.0, Calculator::resolvePerUnit(0.0, 0.0));
    }

    public function testLowestIgnoresNull(): void
    {
        self::assertSame(80.0, Calculator::lowest([null, 100.0, 80.0]));
        self::assertNull(Calculator::lowest([null, null]));
        self::assertNull(Calculator::lowest([]));
    }

    public function testParseDecimal(): void
    {
        self::assertSame(0.76, Calculator::parseDecimal('0,76'));
        self::assertSame(0.76, Calculator::parseDecimal(' 0.76 '));
        self::assertSame(1234.5, Calculator::parseDecimal('1 234,5'));
        self::assertSame(1234.5, Calculator::parseDecimal('1.234,5'));
        self::assertSame(1234.5, Calculator::parseDecimal('1,234.5'));
        self::assertSame(0.0, Calculator::parseDecimal('abc'));
        self::assertSame(0.0, Calculator::parseDecimal('-3'));
    }

    public function testDisplayUnit(): void
    {
        self::assertSame("m\u{00B2}", Calculator::displayUnit('m2'));
        self::assertSame("m\u{00B3}", Calculator::displayUnit('m3'));
        self::assertSame('pcs', Calculator::displayUnit('pcs'));
        self::assertSame("ft\u{00B2}", Calculator::displayUnit(' ft2 '));
    }
}
