<?php

namespace App\Service;

class TrendValidator
{
    public function validateScore(float $score): bool
    {
        if ($score < 0.0 || $score > 1.0) {
            throw new \InvalidArgumentException(
                'Le score doit être compris entre 0 et 1.'
            );
        }
        return true;
    }

    public function validateCategory(string $category): bool
    {
        if (empty(trim($category))) {
            throw new \InvalidArgumentException(
                'Le nom de catégorie est obligatoire.'
            );
        }
        return true;
    }

    public function validateTrend(string $trend): bool
    {
        $allowed = ['hausse', 'stable', 'baisse'];
        if (!in_array($trend, $allowed, true)) {
            throw new \InvalidArgumentException(
                sprintf('La tendance "%s" est invalide.', $trend)
            );
        }
        return true;
    }

    public function validateTrendRecord(string $category, float $score, string $trend): bool
    {
        $this->validateCategory($category);
        $this->validateScore($score);
        $this->validateTrend($trend);
        return true;
    }

    public function mapScoreToTrend(float $score): string
    {
        if ($score > 0.65) {
            return 'hausse';
        }
        if ($score >= 0.35) {
            return 'stable';
        }
        return 'baisse';
    }
}