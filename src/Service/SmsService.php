<?php

namespace App\Service;

use Twilio\Rest\Client;

class SmsService
{
    private Client $client;
    private string $from;

    public function __construct(
        string $twilioSid,
        string $twilioToken,
        string $twilioPhone,
        ?Client $client = null
    ) {
        $this->client = $client ?? new Client($twilioSid, $twilioToken);
        $this->from   = $twilioPhone;
    }

    public function validate(string $phone, string $message): bool
    {
        if (empty($phone)) {
            throw new \InvalidArgumentException('Le numéro de téléphone ne peut pas être vide.');
        }

        if (empty($message)) {
            throw new \InvalidArgumentException('Le message ne peut pas être vide.');
        }

        if (strlen($message) < 2) {
            throw new \InvalidArgumentException('Le message est trop court (minimum 2 caractères).');
        }

        return true;
    }

    public function sendSms(string $to, string $message): void
    {
        if (!str_starts_with($to, '+')) {
            $to = '+216' . ltrim($to, '0');
        }

        $this->client->messages->create($to, [
            'from' => $this->from,
            'body' => $message,
        ]);
    }
}