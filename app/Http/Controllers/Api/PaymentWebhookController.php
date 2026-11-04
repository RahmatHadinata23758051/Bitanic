<?php

namespace App\Http\Controllers\Api;

use App\Actions\Payment\ProcessMidtransWebhookAction;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PaymentWebhookController extends Controller
{
    public function handle(Request $request, ProcessMidtransWebhookAction $action): JsonResponse
    {
        $result = $action->execute($request->all());

        return response()->json([
            'success' => $result['success'],
            'message' => $result['message'],
            'status' => $result['status'],
        ], $result['code']);
    }
}
