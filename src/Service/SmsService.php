<?php

namespace App\Service;

use Twilio\Rest\Client;

class SmsService
{
    private Client $client;
    private string $from;

    public function __construct(string $twilioSid, string $twilioToken, string $twilioPhone)
    {
        $this->client = new Client($twilioSid, $twilioToken);
        $this->from   = $twilioPhone;
    }

    public function sendSms(string $to, string $message): void
    {
        // Correction format numéro tunisien
        if (!str_starts_with($to, '+')) {
            $to = '+21655279985' . ltrim($to, '0');
        }

        $this->client->messages->create($to, [
            'from' => $this->from,
            'body' => $message,
        ]);
    }
}