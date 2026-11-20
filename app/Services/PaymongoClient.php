<?php

namespace App\Services;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

/**
 * The three calls TaraBasa makes to PayMongo's hosted checkout, over plain HTTPS (no payment package: the legal test that forbids one still holds).
 * Basic authentication with the secret key as the user name and an empty password, as PayMongo's guide shows. Settings are in config/payments.php.
 *
 *   createCheckout    POST   /{version}/checkout_sessions            makes the checkout address the parent is sent to
 *   retrieveCheckout  GET    /{version}/checkout_sessions/{id}       asks whether it was paid (the page that describes this call was NOT readable
 *                                                                    on 2026-11-20: confirm it in a real test)
 *   expireCheckout    POST   /{version}/checkout_sessions/{id}/expire  closes it (PayMongo never closes one by itself and sends no message when it expires)
 *
 * Whatever it sends is chosen by App\Services\PaymentService: only the price, the activity title and a reference number.
 */
class PaymongoClient
{
    /** @return array{id: string, url: string} */
    public function createCheckout(array $attributes): array
    {
        $data = $this->call('post', 'checkout_sessions', ['data' => ['attributes' => $attributes]]);

        $id = (string) ($data['data']['id'] ?? '');
        $url = (string) ($data['data']['attributes']['checkout_url'] ?? '');
        if ($id === '' || $url === '') {
            throw new PaymentProviderException('The payment provider answered without a checkout address.');
        }
        // The parent is sent to this address, so it must be an https address. A plain http address is only accepted away from production
        // (the local rehearsal tool, tools/pretend-provider.py).
        if (! str_starts_with($url, 'https://') && (app()->environment('production') || ! str_starts_with($url, 'http://'))) {
            throw new PaymentProviderException('The payment provider answered with a checkout address that is not https.');
        }

        return ['id' => $id, 'url' => $url];
    }

    /** @return array{id?: string, attributes?: array<string, mixed>} the session as PayMongo describes it (id and attributes) */
    public function retrieveCheckout(string $id): array
    {
        $data = $this->call('get', 'checkout_sessions/'.rawurlencode($id));

        return is_array($data['data'] ?? null) ? $data['data'] : [];
    }

    public function expireCheckout(string $id): void
    {
        $this->call('post', 'checkout_sessions/'.rawurlencode($id).'/expire');
    }

    private function url(string $path): string
    {
        return rtrim((string) config('payments.paymongo.base_url'), '/').'/'.trim((string) config('payments.paymongo.api_version'), '/').'/'.ltrim($path, '/');
    }

    private function request(): PendingRequest
    {
        return Http::withBasicAuth((string) config('payments.paymongo.secret_key'), '')->acceptJson()->asJson()->timeout(20)->connectTimeout(8);
    }

    private function call(string $method, string $path, array $body = []): array
    {
        try {
            $response = $method === 'get'
                ? $this->request()->get($this->url($path))
                : $this->request()->post($this->url($path), $body);
        } catch (ConnectionException $e) {
            throw new PaymentProviderException('The payment provider could not be reached.', 0, $e);
        }

        if ($response->failed()) {
            // The provider's own words for what was wrong (for example a price it does not accept) are kept for the team, never shown to a parent.
            $detail = (string) ($response->json('errors.0.detail') ?? '');

            throw new PaymentProviderException('The payment provider answered '.$response->status().($detail !== '' ? ': '.Str::limit($detail, 110, '') : '.'), $response->status());
        }

        $json = $response->json();

        return is_array($json) ? $json : [];
    }
}
