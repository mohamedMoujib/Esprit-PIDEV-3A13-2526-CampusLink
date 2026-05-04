<?php

namespace App\Tests\Service;

use App\Service\TrendValidator;
use PHPUnit\Framework\TestCase;

class TrendValidatorTest extends TestCase
{
    private TrendValidator $validator;

    protected function setUp(): void
    {
        // Aucun mock — instanciation directe comme dans le workshop
        $this->validator = new TrendValidator();
    }

    // ── Règle 1 : Score entre 0 et 1 ─────────────────────────────────────────

    public function testValidScoreReturnsTrue(): void
    {
        $this->assertTrue($this->validator->validateScore(0.75));
    }

    public function testScoreBelowZeroThrowsException(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->validator->validateScore(-0.1);
    }

    public function testScoreAboveOneThrowsException(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->validator->validateScore(1.1);
    }

    public function testScoreAtBoundariesIsValid(): void
    {
        $this->assertTrue($this->validator->validateScore(0.0));
        $this->assertTrue($this->validator->validateScore(1.0));
    }

    // ── Règle 2 : Catégorie non vide ─────────────────────────────────────────

    public function testValidCategoryReturnsTrue(): void
    {
        $this->assertTrue($this->validator->validateCategory('Cours particuliers'));
    }

    public function testEmptyCategoryThrowsException(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->validator->validateCategory('');
    }

    public function testWhitespaceCategoryThrowsException(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->validator->validateCategory('   ');
    }

    // ── Règle 3 : Tendance parmi les valeurs autorisées ───────────────────────

    public function testValidTrendHausseReturnsTrue(): void
    {
        $this->assertTrue($this->validator->validateTrend('hausse'));
    }

    public function testValidTrendStableReturnsTrue(): void
    {
        $this->assertTrue($this->validator->validateTrend('stable'));
    }

    public function testValidTrendBaisseReturnsTrue(): void
    {
        $this->assertTrue($this->validator->validateTrend('baisse'));
    }

    public function testInvalidTrendThrowsException(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->validator->validateTrend('inconnu');
    }

    // ── Règle 4 : Enregistrement complet valide ───────────────────────────────

    public function testValidTrendRecordReturnsTrue(): void
    {
        $this->assertTrue(
            $this->validator->validateTrendRecord('Révisions', 0.80, 'hausse')
        );
    }

    public function testTrendRecordWithInvalidScoreThrowsException(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->validator->validateTrendRecord('Révisions', 1.5, 'hausse');
    }

    public function testTrendRecordWithEmptyCategoryThrowsException(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->validator->validateTrendRecord('', 0.80, 'hausse');
    }

    // ── Règle 5 : Mapping score → tendance ───────────────────────────────────

    public function testHighScoreMapsToHausse(): void
    {
        $this->assertSame('hausse', $this->validator->mapScoreToTrend(0.90));
    }

    public function testMidScoreMapsToStable(): void
    {
        $this->assertSame('stable', $this->validator->mapScoreToTrend(0.50));
    }

    public function testLowScoreMapsToBaisse(): void
    {
        $this->assertSame('baisse', $this->validator->mapScoreToTrend(0.20));
    }

    public function testBoundaryScoreAbove65MapsToHausse(): void
    {
        $this->assertSame('hausse', $this->validator->mapScoreToTrend(0.66));
    }

    public function testBoundaryScoreAt65MapsToStable(): void
    {
        $this->assertSame('stable', $this->validator->mapScoreToTrend(0.65));
    }

    public function testBoundaryScoreAt35MapsToStable(): void
    {
        $this->assertSame('stable', $this->validator->mapScoreToTrend(0.35));
    }

    public function testBoundaryScoreBelow35MapsToBaisse(): void
    {
        $this->assertSame('baisse', $this->validator->mapScoreToTrend(0.34));
    }
}