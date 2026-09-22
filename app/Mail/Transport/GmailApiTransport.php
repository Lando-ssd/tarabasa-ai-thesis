<?php

namespace App\Mail\Transport;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Symfony\Component\Mailer\Exception\TransportException;
use Symfony\Component\Mailer\SentMessage;
use Symfony\Component\Mailer\Transport\AbstractTransport;

/**
 * Sends real mail through Gmail's own API, authenticated as
 * tarabasaai.noreply@gmail.com via a stored OAuth refresh token — not raw
 * SMTP (Railway blocks outbound port 587, confirmed via a real connection
 * timeout) and not a third-party service pretending to be a Gmail sender
 * (Amazon SES was tried first; Gmail silently drops third-party mail that
 * claims to be "From" a gmail.com address that isn't actually Gmail's own
 * infrastructure sending it — a DMARC alignment failure, confirmed by a
 * real test send that reported success but never arrived). This transport
 * sends genuinely AS the real Gmail account through Gmail's real servers,
 * so neither problem applies.
 */
class GmailApiTransport extends AbstractTransport
{
    public function __construct(
        private readonly string $clientId,
        private readonly string $clientSecret,
        private readonly string $refreshToken,
    ) {
        parent::__construct();
    }

    protected function doSend(SentMessage $message): void
    {
        $accessToken = $this->getAccessToken();

        $raw = rtrim(strtr(base64_encode($message->toString()), '+/', '-_'), '=');

        $response = Http::withToken($accessToken)
            ->timeout(20)
            ->post('https://gmail.googleapis.com/gmail/v1/users/me/messages/send', [
                'raw' => $raw,
            ]);

        if ($response->failed()) {
            Log::error('Gmail API send failed', [
                'status' => $response->status(),
                'body' => $response->body(),
            ]);

            throw new TransportException('Gmail API rejected the message: '.$response->body());
        }
    }

    private function getAccessToken(): string
    {
        $response = Http::asForm()->timeout(15)->post('https://oauth2.googleapis.com/token', [
            'client_id' => $this->clientId,
            'client_secret' => $this->clientSecret,
            'refresh_token' => $this->refreshToken,
            'grant_type' => 'refresh_token',
        ]);

        if ($response->failed()) {
            Log::error('Gmail API token refresh failed', [
                'status' => $response->status(),
                'body' => $response->body(),
            ]);

            throw new TransportException('Could not refresh the Gmail API access token: '.$response->body());
        }

        return $response->json('access_token');
    }

    public function __toString(): string
    {
        return 'gmail-api';
    }
}
