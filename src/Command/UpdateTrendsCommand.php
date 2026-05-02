<?php

namespace App\Command;

use App\Service\TrendPredictionService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Contracts\Cache\CacheInterface;

#[AsCommand(name: 'app:trends:update', description: 'Met à jour les tendances de catégories et notifie les prestataires')]
class UpdateTrendsCommand extends Command
{
    private const CACHE_KEY = 'trend_categories_result';

    public function __construct(
        private readonly TrendPredictionService $trends,
        private readonly CacheInterface $cache,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        try {
            $this->cache->delete(self::CACHE_KEY);
        } catch (\Throwable $e) {
            $io->warning('Impossible de vider le cache : '.$e->getMessage());
        }

        $trending = $this->trends->predictTrendingCategories(false);
        $analyzed = \count($trending);

        // Sauvegarde de l'historique
        $this->trends->saveTrendHistory($trending);

        $sent = $this->trends->notifyPrestatairesOnTrend($trending);

        $io->success("{$analyzed} catégorie(s) analysée(s), {$sent} notification(s) envoyée(s).");

        return Command::SUCCESS;
    }
}
