<?php
namespace App\Service;

use Symfony\Contracts\HttpClient\HttpClientInterface;
use Psr\Log\LoggerInterface;

/**
 * Service de traduction utilisant LibreTranslate (gratuit, open-source)
 * API: https://libretranslate.com
 * Limite: 20 requêtes/minute sur l'instance publique
 */
class TranslationService
{
    private array $cache = [];
    private const API_URL = 'https://libretranslate.com/translate';

    public function __construct(
        private readonly HttpClientInterface $client,
        private readonly LoggerInterface $logger
    ) {}

    /**
     * Traduit un texte vers la langue cible
     * 
     * @param string $text Texte à traduire
     * @param string $targetLang Langue cible (fr, en, ar, es, etc.)
     * @param string|null $sourceLang Langue source (auto-détection si null)
     * @return string Texte traduit ou texte original en cas d'erreur
     */
    public function translate(string $text, string $targetLang, ?string $sourceLang = null): string
    {
        // Vérifications de base
        if (!trim($text)) {
            return $text;
        }

        // Détection automatique de la langue source si non spécifiée
        if ($sourceLang === null) {
            $sourceLang = $this->detectLanguage($text);
        }

        // Si la langue source est la même que la cible, pas besoin de traduire
        if ($sourceLang === $targetLang) {
            return $text;
        }

        // Vérifier le cache
        $cacheKey = md5($text . '_' . $sourceLang . '_' . $targetLang);
        if (isset($this->cache[$cacheKey])) {
            return $this->cache[$cacheKey];
        }

        try {
            $response = $this->client->request('POST', self::API_URL, [
                'headers' => [
                    'Content-Type' => 'application/json',
                ],
                'json' => [
                    'q' => $text,
                    'source' => $sourceLang,
                    'target' => $targetLang,
                    'format' => 'text',
                ],
                'timeout' => 10,
            ]);

            $data = $response->toArray();
            $translatedText = $data['translatedText'] ?? null;

            if ($translatedText) {
                // Mettre en cache
                $this->cache[$cacheKey] = $translatedText;
                return $translatedText;
            }

        } catch (\Exception $e) {
            $this->logger->error('Erreur lors de la traduction', [
                'error' => $e->getMessage(),
                'text' => substr($text, 0, 100),
                'source' => $sourceLang,
                'target' => $targetLang,
            ]);
        }

        // En cas d'erreur, retourner le texte original
        return $text;
    }

    /**
     * Détecte automatiquement la langue d'un texte
     * 
     * @param string $text Texte à analyser
     * @return string Code de langue (fr, en, ar, etc.)
     */
    private function detectLanguage(string $text): string
    {
        // Détection basique par patterns Unicode
        if (preg_match('/[\x{0600}-\x{06FF}]/u', $text)) {
            return 'ar'; // Arabe
        }
        
        if (preg_match('/[\x{4E00}-\x{9FFF}]/u', $text)) {
            return 'zh'; // Chinois
        }
        
        if (preg_match('/[\x{3040}-\x{309F}\x{30A0}-\x{30FF}]/u', $text)) {
            return 'ja'; // Japonais
        }
        
        if (preg_match('/[\x{AC00}-\x{D7AF}]/u', $text)) {
            return 'ko'; // Coréen
        }

        // Par défaut, considérer comme français (langue principale de l'app)
        return 'fr';
    }

    /**
     * Traduit un tableau de textes
     * 
     * @param array $texts Tableau de textes à traduire
     * @param string $targetLang Langue cible
     * @param string|null $sourceLang Langue source
     * @return array Tableau de textes traduits
     */
    public function translateBatch(array $texts, string $targetLang, ?string $sourceLang = null): array
    {
        $translated = [];
        
        foreach ($texts as $key => $text) {
            $translated[$key] = $this->translate($text, $targetLang, $sourceLang);
        }
        
        return $translated;
    }

    /**
     * Vide le cache de traduction
     */
    public function clearCache(): void
    {
        $this->cache = [];
    }
}
