<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\PaymentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * The payment provider's signed message (POST /api/webhooks/paymongo). No sign in and no session: the signature IS the proof
 * (App\Support\PaymongoSignature), and nothing in the body is read before it has been checked (PaymentService::handleWebhook).
 *
 * PayMongo wants a 200 to 209 answer with a JSON body, and tries a failed delivery again, so everything that is not a fault of ours answers 200.
 * Create the webhook in the PayMongo dashboard (Developers, Webhooks) for the event `checkout_session.payment.paid` and put its secret (whsk_...)
 * in the hosting settings as PAYMONGO_WEBHOOK_SECRET.
 */
class PaymongoWebhookController extends Controller
{
    public function __invoke(Request $request, PaymentService $payments): JsonResponse
    {
        $result = $payments->handleWebhook($request->getContent(), $request->header('Paymongo-Signature'));

        return response()->json(['received' => $result['status'] < 300, 'outcome' => $result['outcome']], $result['status']);
    }
}
