<?php
namespace App\Service;

use Symfony\Contracts\HttpClient\HttpClientInterface;

/** Mirrors TranslationService.java */
class TranslationService
{
    private array $cache = [];

    public function __construct(private readonly HttpClientInterface $client) {}

    public function translate(string $text, string $targetLang): string
    {
        if (!trim($text)) return $text;
        $key = $text . '_' . $targetLang;
        if (isset($this->cache[$key])) return $this->cache[$key];

        $sourceLang = preg_match('/[\x{0600}-\x{06FF}]/u', $text) ? 'ar' : 'fr';
        if ($sourceLang === $targetLang) return $text;

        try {
            $res  = $this->client->request('GET', 'https://api.mymemory.translated.net/get', [
                'query'   => ['q' => $text, 'langpair' => "$sourceLang|$targetLang"],
                'timeout' => 5,
            ]);
            $data = $res->toArray(false);
            $t    = $data['responseData']['translatedText'] ?? null;
            if ($t) { $this->cache[$key] = $t; return $t; }
        } catch (\Exception) {}
        return $text;
    }
}
