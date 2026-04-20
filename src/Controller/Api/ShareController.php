<?php

namespace App\Controller\Api;

use App\Repository\ServiceRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

#[Route('/api/share')]
class ShareController extends AbstractController
{
    public function __construct(
        private readonly HttpClientInterface $httpClient,
        private readonly string $telegramBotToken,
        private readonly string $telegramChatId,
    ) {}

    #[Route('/service/{id}', name: 'api_share_service_telegram', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function shareServiceOnTelegram(
        int $id,
        Request $request,
        ServiceRepository $serviceRepo,
    ): JsonResponse {
        $service = $serviceRepo->find($id);

        if ($service === null) {
            return $this->json(['success' => false, 'error' => 'Service introuvable.'], 404);
        }

        $serviceUrl = $this->generateUrl('service_show', ['id' => $id], UrlGeneratorInterface::ABSOLUTE_URL);

        $description = $service->getDescription() ?? 'Découvrez ce service sur CampusLink';
        $shortDesc   = mb_strlen($description) > 200 ? mb_substr($description, 0, 200) . '…' : $description;

        $price    = number_format((float) $service->getPrice(), 2, ',', ' ');
        $category = $service->getCategory()?->getName() ?? 'Non catégorisé';
        $provider = $service->getUser()?->getName() ?? 'Inconnu';

        $message = "🎓 *{$service->getTitle()}*\n\n"
            . "📂 Catégorie : {$category}\n"
            . "👤 Prestataire : {$provider}\n"
            . "💰 Prix : {$price} DT\n\n"
            . "{$shortDesc}\n\n"
            . "🔗 [Voir le service]({$serviceUrl})";

        try {
            $response = $this->httpClient->request('POST', "https://api.telegram.org/bot{$this->telegramBotToken}/sendMessage", [
                'json' => [
                    'chat_id'    => $this->telegramChatId,
                    'text'       => $message,
                    'parse_mode' => 'Markdown',
                ],
            ]);

            $data = $response->toArray(false);

            if (!($data['ok'] ?? false)) {
                return $this->json([
                    'success' => false,
                    'error'   => $data['description'] ?? 'Telegram API error',
                ], 502);
            }

            return $this->json(['success' => true]);
        } catch (\Throwable $e) {
            return $this->json(['success' => false, 'error' => $e->getMessage()], 502);
        }
    }
}
