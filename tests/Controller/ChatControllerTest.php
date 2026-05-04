<?php

namespace App\Tests\Controller\Etudiant;

use PHPUnit\Framework\TestCase;
use App\Controller\Etudiant\ChatController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;

class ChatControllerTest extends TestCase
{
    public function testChatReturnsResponse()
    {
        // 🔹 Mock HTTP response
        $responseMock = $this->createMock(ResponseInterface::class);
        $responseMock->method('toArray')
            ->willReturn([
                'choices' => [
                    [
                        'message' => [
                            'content' => 'Hello from AI'
                        ]
                    ]
                ]
            ]);

        // 🔹 Mock HTTP client
        $httpClient = $this->createMock(HttpClientInterface::class);
        $httpClient->method('request')
            ->willReturn($responseMock);

        // 🔹 Create controller with string API key
        $controller = new ChatController('fake-api-key');

        // 🔹 Fake request body
        $request = new Request([], [], [], [], [], [], json_encode([
            'messages' => [
                ['role' => 'user', 'content' => 'Hi']
            ]
        ]));

        // 🔹 Call controller
        $response = $controller->chat($request, $httpClient);

        // 🔹 Assertions
        $this->assertEquals(200, $response->getStatusCode());

        $data = json_decode($response->getContent(), true);

        $this->assertArrayHasKey('choices', $data);
    }
}