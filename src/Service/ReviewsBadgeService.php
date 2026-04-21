<?php

namespace App\Service;

class ReviewsBadgeService
{
    // Définition des badges basés sur les trust points et la note moyenne
    public const BADGES = [
        'elite' => [
            'name' => 'Élite',
            'icon' => '👑',
            'color' => '#FFD700',
            'minTrustPoints' => 200,
            'minAvgRating' => 4.5,
            'description' => 'Tuteur d\'élite avec une excellente réputation',
        ],
        'expert' => [
            'name' => 'Expert',
            'icon' => '⭐',
            'color' => '#FF6B6B',
            'minTrustPoints' => 150,
            'minAvgRating' => 4.0,
            'description' => 'Tuteur expert reconnu par la communauté',
        ],
        'pro' => [
            'name' => 'Professionnel',
            'icon' => '🎓',
            'color' => '#4ECDC4',
            'minTrustPoints' => 100,
            'minAvgRating' => 3.5,
            'description' => 'Tuteur professionnel de confiance',
        ],
        'verified' => [
            'name' => 'Vérifié',
            'icon' => '✓',
            'color' => '#95E1D3',
            'minTrustPoints' => 50,
            'minAvgRating' => 3.0,
            'description' => 'Tuteur vérifié et fiable',
        ],
        'rising' => [
            'name' => 'Étoile montante',
            'icon' => '🌟',
            'color' => '#FFA07A',
            'minTrustPoints' => 20,
            'minAvgRating' => 3.0,
            'description' => 'Nouveau tuteur prometteur',
        ],
    ];

    // Badges spéciaux basés sur d'autres critères
    public const SPECIAL_BADGES = [
        'top_rated' => [
            'name' => 'Meilleure note',
            'icon' => '🏆',
            'color' => '#FFD700',
            'description' => 'Top 10 des tuteurs les mieux notés',
        ],
        'most_reviews' => [
            'name' => 'Plus populaire',
            'icon' => '💬',
            'color' => '#9B59B6',
            'description' => 'Parmi les tuteurs avec le plus d\'avis',
        ],
        'consistent' => [
            'name' => 'Régulier',
            'icon' => '📊',
            'color' => '#3498DB',
            'description' => 'Performance constante et fiable',
        ],
    ];

    /**
     * Détermine le badge principal d'un tuteur
     */
    public function getBadge(int $trustPoints, float $avgRating): ?array
    {
        // Parcourir les badges du plus prestigieux au moins prestigieux
        foreach (self::BADGES as $key => $badge) {
            if ($trustPoints >= $badge['minTrustPoints'] && $avgRating >= $badge['minAvgRating']) {
                return array_merge($badge, ['key' => $key]);
            }
        }

        return null;
    }

    /**
     * Obtient tous les badges d'un tuteur (principal + spéciaux)
     */
    public function getAllBadges(int $trustPoints, float $avgRating, int $reviewCount, int $rank): array
    {
        $badges = [];

        // Badge principal
        $mainBadge = $this->getBadge($trustPoints, $avgRating);
        if ($mainBadge) {
            $badges[] = $mainBadge;
        }

        // Badge top rated (uniquement pour le 1er)
        if ($rank === 1 && $avgRating >= 4.0) {
            $badges[] = array_merge(self::SPECIAL_BADGES['top_rated'], ['key' => 'top_rated']);
        }

        // Badge most reviews (plus de 20 avis)
        if ($reviewCount >= 20) {
            $badges[] = array_merge(self::SPECIAL_BADGES['most_reviews'], ['key' => 'most_reviews']);
        }

        // Badge consistent (écart-type faible, à implémenter plus tard)
        // Pour l'instant, on considère qu'un tuteur avec plus de 10 avis et une note >= 4 est régulier
        if ($reviewCount >= 10 && $avgRating >= 4.0) {
            $badges[] = array_merge(self::SPECIAL_BADGES['consistent'], ['key' => 'consistent']);
        }

        return $badges;
    }

    /**
     * Obtient le niveau de progression vers le prochain badge
     */
    public function getProgressToNextBadge(int $trustPoints, float $avgRating): ?array
    {
        $currentBadge = $this->getBadge($trustPoints, $avgRating);
        $currentKey = $currentBadge['key'] ?? null;

        // Trouver le prochain badge
        $badgeKeys = array_keys(self::BADGES);
        $currentIndex = $currentKey ? array_search($currentKey, $badgeKeys) : count($badgeKeys);

        if ($currentIndex === 0) {
            return null; // Déjà au niveau maximum
        }

        $nextBadgeKey = $badgeKeys[$currentIndex - 1];
        $nextBadge = self::BADGES[$nextBadgeKey];

        $trustPointsNeeded = max(0, $nextBadge['minTrustPoints'] - $trustPoints);
        $ratingNeeded = max(0, $nextBadge['minAvgRating'] - $avgRating);

        $trustPointsProgress = $trustPoints / $nextBadge['minTrustPoints'] * 100;
        $ratingProgress = $avgRating / $nextBadge['minAvgRating'] * 100;

        return [
            'nextBadge' => array_merge($nextBadge, ['key' => $nextBadgeKey]),
            'trustPointsNeeded' => $trustPointsNeeded,
            'ratingNeeded' => round($ratingNeeded, 1),
            'trustPointsProgress' => min(100, round($trustPointsProgress, 1)),
            'ratingProgress' => min(100, round($ratingProgress, 1)),
        ];
    }

    /**
     * Obtient le rang d'un badge (1 = meilleur, 5 = débutant)
     */
    public function getBadgeRank(?string $badgeKey): int
    {
        if (!$badgeKey) return 6;

        $ranks = ['elite' => 1, 'expert' => 2, 'pro' => 3, 'verified' => 4, 'rising' => 5];
        return $ranks[$badgeKey] ?? 6;
    }
}
