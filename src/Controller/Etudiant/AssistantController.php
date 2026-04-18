<?php
namespace App\Controller\Etudiant;

use App\Repository\ServiceRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Contracts\HttpClient\HttpClientInterface;

#[Route('/etudiant/assistant')]
class AssistantController extends AbstractController
{
    public function __construct(
        private HttpClientInterface $http,
        private ServiceRepository $serviceRepo,
        private string $geminiKey,
    ) {}
    public function findActiveServices(): array
{
    return $this->createQueryBuilder('s')
        ->leftJoin('s.user', 'u')->addSelect('u')
        ->leftJoin('s.category', 'c')->addSelect('c')
        ->andWhere('s.status = :st')
        ->setParameter('st', 'CONFIRMEE')
        ->getQuery()
        ->getResult();
}

    #[Route('/chat', name: 'etudiant_assistant_chat', methods: ['POST'])]
    public function chat(Request $request): JsonResponse
    {
        /** @var \App\Entity\User $user */
        $user = $this->getUser();
        if (!$user || $user->getUserType() !== 'ETUDIANT') {
            return $this->json(['error' => 'Accès refusé.'], 403);
        }

        $body    = json_decode($request->getContent(), true);
        $message = trim($body['message'] ?? '');

        if ($message === '') {
            return $this->json(['error' => 'Message vide'], 400);
        }

      $services = $this->serviceRepo->findActiveServices();
      $servicesList = '';
foreach ($services as $s) {
    $servicesList .= sprintf(
        "- \"%s\" par %s — %s TND (catégorie: %s)\n",
        $s->getTitle(),
        $s->getUser()?->getName() ?? 'Inconnu',
        $s->getPrice(),
        $s->getCategory()?->getName() ?? 'Non classé'
    );
}
        $servicesSection = $servicesList !== ''
    ? "SERVICES DISPONIBLES EN CE MOMENT :\n$servicesList"
    : "SERVICES DISPONIBLES EN CE MOMENT : aucun service disponible pour le moment.";


$systemPrompt = <<<PROMPT
Tu es l'assistant de CampusLink, une plateforme étudiante tunisienne.

RÈGLES STRICTES :
- Réponds UNIQUEMENT aux questions liées à CampusLink et à ses fonctionnalités
- Si la question n'est pas liée à CampusLink, réponds exactement : "Je suis uniquement là pour vous aider avec CampusLink. Posez-moi une question sur la plateforme !"
- N'utilise PAS de markdown (pas de **, pas de *, pas de #)
- Réponds en texte simple et naturel, comme dans un chat
- Sois bref et direct, maximum 4-5 lignes
- Pas de listes numérotées sauf si vraiment nécessaire
- Tutoie l'utilisateur

FONCTIONNALITÉS :
- Parcourir et réserver des services par catégorie
- Réservation : trouver → réserver → confirmation prestataire → choisir lieu + paiement (espèces ou D17) → facture → avis
- Chat en temps réel avec le prestataire
- Laisser un avis et une note après la prestation
- Voir le classement des meilleurs prestataires
- Créer des publications : demande de service ou achat/vente d'objets étudiants (livres, PC, fournitures) manuellement ou via IA

SERVICES DISPONIBLES :
$servicesList
Quand on te demande un service, cite UNIQUEMENT les services de la liste ci-dessus mot pour mot. 
Si le service demandé n'est pas dans la liste, réponds : "Il n'y a pas de service disponible pour ça en ce moment. Tu peux créer une publication pour que les prestataires te contactent !"
PROMPT;

        try {
    $response = $this->http->request(
        'POST',
        'https://generativelanguage.googleapis.com/v1beta/models/gemini-3.1-flash-lite-preview:generateContent?key=' . $this->geminiKey,
        [
            'headers' => ['Content-Type' => 'application/json'],
            'json'    => [
                'system_instruction' => ['parts' => [['text' => $systemPrompt]]],
                'contents'           => [['role' => 'user', 'parts' => [['text' => $message]]]],
                'generationConfig'   => ['maxOutputTokens' => 800, 'temperature' => 0.7],
            ],
        ]
    );
    

    // ── toArray() is where Symfony actually throws on 4xx/5xx ──
    $data  = $response->toArray();
    $reply = $data['candidates'][0]['content']['parts'][0]['text'] ?? 'Erreur de réponse.';

} catch (\Throwable $e) {
    // Check the raw status without throwing again
    $status = method_exists($e, 'getResponse') ? $e->getResponse()->getStatusCode() : 0;

    if ($status === 429) {
        return $this->json(['reply' => 'Trop de requêtes, réessayez dans quelques secondes.']);
    }

    return $this->json(['reply' => 'Erreur de connexion. Réessayez.']);
}

return $this->json(['reply' => $reply]);
    }
}