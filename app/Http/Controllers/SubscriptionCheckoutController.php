<?php

namespace App\Http\Controllers;

use App\Actions\Payment\CreateMidtransSnapTransactionAction;
use App\Models\PaymentHistory;
use App\Models\SubscriptionPlan;
use App\Models\User;
use App\Models\UserSubscription;
use App\Services\SubscriptionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

/**
 * @deprecated LEGACY-01 / BR-504 — Retired SaaS subscription checkout controller.
 *
 * Web routes to this controller have been disabled. The controller, its actions,
 * and all associated models/migrations/data are preserved intact for historic
 * reference and potential future re-activation. Do NOT delete.
 * @see routes/web.php — subscription routes commented out
 * @see App\Actions\Payment\CreateMidtransSnapTransactionAction
 * @see App\Services\SubscriptionService
 */
class SubscriptionCheckoutController extends Controller
{
    /**
     * Display subscription plans catalog, active subscription status, and payment history.
     */
    public function index(SubscriptionService $subService): View
    {
        $user = Auth::user() ?? User::where('role', 'guru')->first() ?? User::first();

        $plans = SubscriptionPlan::where('is_active', true)->orderBy('price', 'asc')->get();

        $activeSub = $user ? UserSubscription::where('user_id', $user->id)
            ->where('status', 'active')
            ->where('ends_at', '>', now())
            ->with('plan')
            ->latest('id')
            ->first() : null;

        $remainingQuota = $user ? $subService->getRemainingQuota($user) : 0;

        $payments = $user ? PaymentHistory::where('user_id', $user->id)
            ->with('plan')
            ->latest('id')
            ->take(10)
            ->get() : collect();

        return view('pages.subscription.index', [
            'user' => $user,
            'plans' => $plans,
            'activeSub' => $activeSub,
            'remainingQuota' => $remainingQuota,
            'payments' => $payments,
            'clientKey' => config('midtrans.client_key', env('MIDTRANS_CLIENT_KEY', 'SB-Mid-client-simulated-key')),
        ]);
    }

    /**
     * Initiate checkout transaction and generate Snap Token.
     */
    public function checkout(Request $request, CreateMidtransSnapTransactionAction $action): JsonResponse|RedirectResponse
    {
        $request->validate([
            'plan_id' => 'required|exists:subscription_plans,id',
        ]);

        $user = Auth::user() ?? User::where('role', 'guru')->first() ?? User::first();

        if (! $user) {
            return redirect()->route('subscription.index')->with('error', 'Silakan login terlebih dahulu untuk berlangganan.');
        }

        $plan = SubscriptionPlan::findOrFail($request->input('plan_id'));

        $result = $action->execute($user, $plan);

        if ($request->wantsJson() || $request->ajax()) {
            return response()->json([
                'success' => true,
                'snap_token' => $result['snap_token'],
                'order_id' => $result['payment']->order_id,
                'redirect_url' => $result['redirect_url'],
            ]);
        }

        if ($result['payment']->status === 'settlement') {
            return redirect()->route('subscription.index')
                ->with('success', 'Paket '.$plan->name.' berhasil diaktifkan.');
        }

        return redirect()->route('subscription.index', [
            'snap_token' => $result['snap_token'],
            'order_id' => $result['payment']->order_id,
        ])->with('success', 'Transaksi dibuat. Silakan selesaikan pembayaran.');
    }
}
