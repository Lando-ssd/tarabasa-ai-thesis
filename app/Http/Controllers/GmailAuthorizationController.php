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
 */
class GmailAuthorizationController extends Controller
{
    public function redirect()
    {
        $params = [
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
        if ($request->has('error')) {
            return 'Google returned an error: '.$request->query('error');
        }

        $response = Http::asForm()->post('https://oauth2.googleapis.com/token', [
            'client_id' => config('services.gmail_send.client_id'),
            'client_secret' => config('services.gmail_send.client_secret'),
            'code' => $request->query('code'),
            'grant_type' => 'authorization_code',
            'redirect_uri' => route('internal.gmail-authorize.callback'),
        ]);

        if ($response->failed()) {
            return 'Token exchange failed: '.$response->body();
        }

        $refreshToken = $response->json('refresh_token');

        if (! $refreshToken) {
            return 'No refresh_token in the response — this usually means you\'ve already authorized this app before. '
                .'Revoke access at https://myaccount.google.com/permissions (find "Tarabasa AI") and try this link again. '
                .'Raw response: '.$response->body();
        }

        return "Success. Copy this value into GMAIL_SEND_REFRESH_TOKEN:<br><br><code style=\"font-size:16px;word-break:break-all;\">{$refreshToken}</code>";
    }
}
