<?php

namespace App\Tests\Service;

use PHPUnit\Framework\TestCase;
use App\Service\SmsService;
use Twilio\Rest\Client;

class SmsServiceTest extends TestCase
{
    private function makeSmsService(): SmsService
    {
        // Mock du client Twilio pour éviter les vrais appels API
        $mockClient = $this->createMock(Client::class);

        // Créer le service sans passer par le vrai constructeur
        $service = $this->getMockBuilder(SmsService::class)
            ->setConstructorArgs(['fake_sid', 'fake_token', '+123456789'])
            ->onlyMethods([]) // ne mock aucune méthode → garde validate() réelle
            ->getMock();

        return $service;
    }

    public function testValidSms(): void
    {
        $service = new SmsService('fake_sid', 'fake_token', '+123456789');

        $this->assertTrue(
            $service->validate('+21655279985', 'Bonjour')
        );
    }

    public function testEmptyPhone(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('ne peut pas être vide');

        $service = new SmsService('fake_sid', 'fake_token', '+123456789');
        $service->validate('', 'Bonjour');
    }

    public function testEmptyMessage(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('ne peut pas être vide');

        $service = new SmsService('fake_sid', 'fake_token', '+123456789');
        $service->validate('+21655279985', '');
    }

    public function testShortMessage(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('trop court');

        $service = new SmsService('fake_sid', 'fake_token', '+123456789');
        $service->validate('+21655279985', 'a');
    }
}