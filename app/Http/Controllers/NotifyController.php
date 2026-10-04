<?php

namespace App\Http\Controllers;

use App\Models\LaundryTransaction;
use App\Services\FcmService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\URL;

/**
 * Public, signed-link pages that let a customer opt in to a "your laundry is ready" push
 * for one order. The link is the secret: it is signed, expires, and only covers that order.
 */
class NotifyController extends Controller
{
    public function show(LaundryTransaction $transaction, FcmService $fcm): View
    {
        $web = $fcm->webConfig();
        abort_if($web === null, 404);

        return view('notify', [
            'code' => $transaction->basketTag?->code ?? '#'.$transaction->id,
            'web' => $web,
            'subscribeUrl' => request()->fullUrl(),
            'testUrl' => URL::temporarySignedRoute('notify.test', now()->addHour(), ['transaction' => $transaction->id]),
        ]);
    }

    public function subscribe(Request $request, LaundryTransaction $transaction): JsonResponse
    {
        $data = $request->validate(['token' => ['required', 'string', 'max:4096']]);

        Cache::put("fcm:order:{$transaction->id}", $data['token'], now()->addDays(14));

        return response()->json(['message' => 'You will be notified when your laundry is ready.']);
    }

    /** Sends one test push to the device that signed up for this order and reports Google's answer. */
    public function test(LaundryTransaction $transaction, FcmService $fcm): JsonResponse
    {
        $token = Cache::get("fcm:order:{$transaction->id}");
        if (! $token) {
            return response()->json(['result' => 'no_token', 'detail' => 'No device is signed up for this order yet. Tap Notify me first.']);
        }

        return response()->json($fcm->sendWithDetail($token, 'Test from SSK Laba Dami', 'If you can read this, push notifications work on this phone.', url('/')));
    }

    public function serviceWorker(FcmService $fcm): Response
    {
        $web = $fcm->webConfig();
        abort_if($web === null, 404);

        $js = "importScripts('https://www.gstatic.com/firebasejs/10.12.2/firebase-app-compat.js');\n"
            ."importScripts('https://www.gstatic.com/firebasejs/10.12.2/firebase-messaging-compat.js');\n"
            .'firebase.initializeApp('.json_encode($web['firebase']).");\n"
            ."firebase.messaging();\n";

        return response($js, 200, ['Content-Type' => 'application/javascript', 'Cache-Control' => 'no-cache']);
    }
}
