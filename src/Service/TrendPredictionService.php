<?php

namespace App\Service;

use App\Entity\TrendHistory;
use App\Repository\CategorieRepository;
use App\Repository\NotificationRepository;
use App\Repository\PublicationRepository;
use App\Repository\ServiceRepository;
use App\Repository\TrendHistoryRepository;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class TrendPredictionService
{
    private const CACHE_KEY = 'trend_categories_result';
    private const OPENROUTER_URL = 'https://openrouter.ai/api/v1/chat/completions';
    private const MODEL = 'openai/gpt-4o'; // ✅ UPDATED

    private readonly string $apiKey;

    public function __construct(
        private readonly HttpClientInterface $client,
        private readonly PublicationRepository $pubRepo,
        private readonly CategorieRepository $catRepo,
        private readonly ServiceRepository $serviceRepo,
        private readonly NotificationRepository $notifRepo,
        private readonly NotificationService $notif,
        private readonly CacheInterface $cache,
        private readonly LoggerInterface $logger,
        private readonly TrendHistoryRepository $trendHistoryRepo,
        private readonly EntityManagerInterface $em,
        ?string $huggingFaceApiKey = null,
    ) {
        $this->apiKey = $huggingFaceApiKey ?? '';
    }

    public function predictTrendingCategories(bool $bypassCache = false): array
    {
        $key = trim($this->apiKey);
        if ($key === '') {
            $this->logger->error('OpenRouter API key is missing.');
            return [];
        }

        if ($bypassCache) {
            try {
                $this->cache->delete(self::CACHE_KEY);
            } catch (\Throwable $e) {
                $this->logger->warning('Cache delete failed: ' . $e->getMessage());
            }
            return $this->fetchFromOpenRouter($key);
        }

        return $this->cache->get(self::CACHE_KEY, function (ItemInterface $item) use ($key) {
            $item->expiresAfter(21600);
            return $this->fetchFromOpenRouter($key);
        });
    }

    private function fetchFromOpenRouter(string $key): array
    {
        $categories = $this->catRepo->findAll();
        $labels = array_map(fn($c) => $c->getName(), $categories);

        if ($labels === []) {
            return [];
        }

        $publications = $this->pubRepo->findRecentForTrend(150);

        $text = mb_substr(
            implode(' ', array_map(fn($p) => $p->getTitre().' '.$p->getMessage(), $publications)),
            0,
            1000
        );

        if (!$text) {
            return [];
        }

        $labelsStr = implode(', ', $labels);

        // ✅ IMPROVED PROMPT
        $prompt = "Analyse des tendances.\n\n"
            . "Publications:\n{$text}\n\n"
            . "Catégories: {$labelsStr}\n\n"
            . "Instructions:\n"
            . "- Score entre 0.0 et 1.0 pour chaque catégorie\n"
            . "- 1.0 = très populaire\n"
            . "- 0.0 = absent\n"
            . "- Si absente → score 0.0–0.2\n"
            . "- Retourne UNIQUEMENT du JSON valide\n\n"
            . "{\"results\": [{\"category\": \"nom_exact\", \"score\": 0.85}]}";

        try {
            $response = $this->client->request('POST', self::OPENROUTER_URL, [
                'headers' => [
                    'Authorization' => 'Bearer ' . $key,
                    'Content-Type'  => 'application/json',
                    'HTTP-Referer'  => 'https://campuslink.tn',
                    'X-Title'       => 'CampusLink',
                ],
                'json' => [
                    'model' => self::MODEL,
                    'messages' => [
                        [
                            'role' => 'system',
                            'content' => 'Return ONLY valid JSON. No text.'
                        ],
                        [
                            'role' => 'user',
                            'content' => $prompt
                        ]
                    ],
                    'temperature' => 0.1,
                    'max_tokens' => 300,
                ],
                'timeout' => 45,
            ]);

            $data = $response->toArray(false);
            $content = $data['choices'][0]['message']['content'] ?? '';

            // Clean JSON
            $content = preg_replace('/```json\s*|\s*```/', '', $content);
            $parsed = json_decode(trim($content), true);

            if (!isset($parsed['results'])) {
                $this->logger->error('Invalid JSON: ' . $content);
                return [];
            }

            $rows = [];

            foreach ($parsed['results'] as $item) {
                $category = (string) ($item['category'] ?? '');
                $score = (float) ($item['score'] ?? 0);

                if (!$category) continue;

                [$trend, $emoji] = $this->mapScoreToTrend($score);

                $rows[] = [
                    'category' => $category,
                    'score' => $score,
                    'trend' => $trend,
                    'emoji' => $emoji,
                ];
            }

            usort($rows, fn($a, $b) => $b['score'] <=> $a['score']);

            return $rows;

        } catch (\Throwable $e) {
            $this->logger->error('OpenRouter error: ' . $e->getMessage());
            return [];
        }
    }

    private function mapScoreToTrend(float $score): array
    {
        if ($score > 0.65) return ['hausse', '📈'];
        if ($score >= 0.35) return ['stable', '➡️'];
        return ['baisse', '📉'];
    }

    /**
     * Returns simple publication stats grouped by category.
     *
     * @return array{byCategory: array<string,int>, topCategory: string, total: int}
     */
    public function getSimpleStats(): array
    {
        $publications = $this->pubRepo->findRecentForTrend(300);

        $byCategory = [];
        foreach ($publications as $pub) {
            $cat = $pub->getCategory();
            $name = $cat ? $cat->getName() : 'Autre';
            $byCategory[$name] = ($byCategory[$name] ?? 0) + 1;
        }

        arsort($byCategory);

        $topCategory = array_key_first($byCategory) ?? '—';
        $total = array_sum($byCategory);

        return [
            'byCategory'  => $byCategory,
            'topCategory' => $topCategory,
            'total'       => $total,
        ];
    }

    /**
     * Builds 7-day chart data for a given category.
     *
     * @return array{labels: string[], data: int[]}
     */
    public function getWeeklyTrendData(string $categoryName, float $baseScore): array
    {
        $labels = [];
        $data   = [];

        for ($i = 6; $i >= 0; --$i) {
            $day = new \DateTime("-{$i} days");
            $labels[] = $day->format('D d/m');

            // Count publications for this category on this day
            $dayStart = (clone $day)->setTime(0, 0, 0);
            $dayEnd   = (clone $day)->setTime(23, 59, 59);

            try {
                $count = (int) $this->pubRepo->createQueryBuilder('p')
                    ->select('COUNT(p.id)')
                    ->leftJoin('p.category', 'c')
                    ->andWhere('c.name = :cat')
                    ->andWhere('p.createdAt BETWEEN :start AND :end')
                    ->setParameter('cat', $categoryName)
                    ->setParameter('start', $dayStart)
                    ->setParameter('end', $dayEnd)
                    ->getQuery()
                    ->getSingleScalarResult();
            } catch (\Throwable) {
                $count = 0;
            }

            $data[] = $count;
        }

        return ['labels' => $labels, 'data' => $data];
    }

    /**
     * Builds 4-day chart data for trending (hausse) categories only.
     * Each category gets a unique color via HSL hue rotation.
     *
     * @return array{labels: string[], datasets: array}
     */
    public function getWeeklyTrendDataAllCategories(array $trending): array
    {
        $labels = [];
        for ($i = 3; $i >= 0; --$i) {
            $day = new \DateTime("-{$i} days");
            $labels[] = $day->format('D d/m');
        }

        // Filter hausse categories first so we know the total count for color spacing
        $hausseCats = [];
        foreach ($trending as $row) {
            $categoryName = (string) ($row['category'] ?? '');
            $trend = (string) ($row['trend'] ?? 'stable');
            if ($trend === 'hausse' && $categoryName !== '') {
                $hausseCats[] = $categoryName;
            }
        }

        $datasets = [];
        $colorIndex = 0;

        foreach ($hausseCats as $categoryName) {
            // Golden-angle hue rotation: evenly distributes hues regardless of count
            $hue = (int) (($colorIndex * 137.508) % 360);
            $borderColor = "hsl({$hue}, 70%, 50%)";
            $bgColor     = "hsla({$hue}, 70%, 50%, 0.12)";

            $counts = [];
            for ($i = 3; $i >= 0; --$i) {
                $day = new \DateTime("-{$i} days");
                $dayStart = (clone $day)->setTime(0, 0, 0);
                $dayEnd   = (clone $day)->setTime(23, 59, 59);

                try {
                    $count = (int) $this->pubRepo->createQueryBuilder('p')
                        ->select('COUNT(p.id)')
                        ->leftJoin('p.category', 'c')
                        ->andWhere('c.name = :cat')
                        ->andWhere('p.createdAt BETWEEN :start AND :end')
                        ->setParameter('cat', $categoryName)
                        ->setParameter('start', $dayStart)
                        ->setParameter('end', $dayEnd)
                        ->getQuery()
                        ->getSingleScalarResult();
                } catch (\Throwable) {
                    $count = 0;
                }

                $counts[] = $count;
            }

            $datasets[] = [
                'label'                => $categoryName,
                'data'                 => $counts,
                'borderColor'          => $borderColor,
                'backgroundColor'      => $bgColor,
                'fill'                 => true,
                'tension'              => 0.35,
                'pointRadius'          => 5,
                'pointBackgroundColor' => $borderColor,
                'borderWidth'          => 2,
            ];

            ++$colorIndex;
        }

        return ['labels' => $labels, 'datasets' => $datasets];
    }

    /**
     * Sends in-app notifications to prestataires whose category is trending (hausse).
     * Returns the number of notifications sent.
     */
    public function notifyPrestatairesOnTrend(array $trending): int
    {
        $count = 0;

        foreach ($trending as $row) {
            if (($row['trend'] ?? '') !== 'hausse') {
                continue;
            }

            $categoryName = (string) ($row['category'] ?? '');
            if ($categoryName === '') {
                continue;
            }

            $prestataires = $this->serviceRepo
                ->findPrestatairesWithConfirmedServiceInCategoryName($categoryName);

            foreach ($prestataires as $user) {
                try {
                    $this->notif->notifyInApp(
                        $user,
                        '📈 Tendance en hausse : ' . $categoryName,
                        "La catégorie \"{$categoryName}\" est actuellement en forte hausse sur CampusLink. "
                        . 'Mettez en avant vos services pour en profiter !'
                    );
                    ++$count;
                } catch (\Throwable $e) {
                    $this->logger->warning('Notification failed for user ' . $user->getId() . ': ' . $e->getMessage());
                }
            }
        }

        return $count;
    }

    /**
     * Saves trending categories to history.
     */
    public function saveTrendHistory(array $rows): void
    {
        foreach ($rows as $row) {
            $cat = (string) ($row['category'] ?? '');
            $score = (float) ($row['score'] ?? 0);

            if (!$cat) {
                continue;
            }

            $history = new TrendHistory();
            $history->setCategoryName($cat);
            $history->setScore($score);
            $history->setRecordedAt(new \DateTimeImmutable());
            $this->em->persist($history);
        }
        $this->em->flush();
    }
}