<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

/**
 * A one-time, manual setup tool — NOT part of any user-facing flow. Run
 * once by whoever owns tarabasaai.noreply@gmail.com to grant this app
 * permission to send mail through Gmail's real API on that account's
 * behalf, capturing a long-lived refresh token in the process (stored as
 * GMAIL_SEND_REFRESH_TOKEN). Deliberately separate from AuthController's
 * "Continue with Google" login flow — that one asks for basic sign-in
 * scopes on behalf of any Teacher/Parent; this one asks for gmail.send
 * specifically, and only ever needs to run once for one specific account.
 *
 * Reachable only by a logged-in Admin (see routes/web.php). Everything it prints is escaped and it
 * is answered as plain text, because this page used to echo a URL parameter straight back as
 * HTML (cross-site scripting) and was open to anyone.
 */
class GmailAuthorizationController extends Controller
{
    public function redirect(Request $request)
    {
        // The state ties the way back to this browser, so a link someone else made cannot finish it.
        $request->session()->put('gmail_authorize_state', $state = bin2hex(random_bytes(16)));

        $params = [
            'state' => $state,
            'client_id' => config('services.gmail_send.client_id'),
            'redirect_uri' => route('internal.gmail-authorize.callback'),
            'response_type' => 'code',
            'scope' => 'https://www.googleapis.com/auth/gmail.send',
            'access_type' => 'offline',
            'prompt' => 'consent',
        ];

        return redirect('https://accounts.google.com/o/oauth2/v2/auth?'.http_build_query($params));
    }

    public function callback(Request $request)
    {
        $expected = $request->session()->pull('gmail_authorize_state');
        if (! is_string($expected) || ! hash_equals($expected, (string) $request->query('state'))) {
            return $this->text('This link did not start from the Admin session. Start again from /internal/gmail-authorize.', 400);
        }

        if ($request->has('error')) {
            return $this->text('Google returned an error: '.$request->query('error'), 400);
        }

        $response = Http::asForm()->post('https://oauth2.googleapis.com/token', [
            'client_id' => config('services.gmail_send.client_id'),
            'client_secret' => config('services.gmail_send.client_secret'),
            'code' => $request->query('code'),
            'grant_type' => 'authorization_code',
            'redirect_uri' => route('internal.gmail-authorize.callback'),
        ]);

        if ($response->failed()) {
            return $this->text('Token exchange failed (status '.$response->status().'). Check the Google client settings and try again.', 502);
        }

        $refreshToken = $response->json('refresh_token');

        if (! $refreshToken) {
            return 'No refresh_token in the response — this usually means you\'ve already authorized this app before. '
                .'Revoke access at https://myaccount.google.com/permissions (find "Tarabasa AI") and try this link again. '
                .'Raw response: '.$response->body();
        }

        return $this->text("Success. Copy this value into GMAIL_SEND_REFRESH_TOKEN, then close this page:

{$refreshToken}");
    }

    /** Plain text, never HTML, so nothing here can run as a script. */
    private function text(string $body, int $status = 200)
    {
        return response($body, $status)->header('Content-Type', 'text/plain; charset=utf-8')->header('Cache-Control', 'no-store');
    }
}
