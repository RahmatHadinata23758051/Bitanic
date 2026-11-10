<?php

namespace App\Services;

use App\Models\User;
use App\Models\UserSubscription;
use Illuminate\Support\Facades\Cache;

/**
 * @deprecated LEGACY-01 / BR-504 — Subscription & AI Quota Service.
 *
 * Direct SaaS subscription checkout routes are retired. This service remains active
 * as an internal entitlement bridge for AI Gemini crop recommendation quota checks
 * (CropRecommendationController / GenerateCropRecommendationJob) and user quota display.
 * Do NOT delete.
 */
class SubscriptionService
{
    /**
     * Check if user has an active, unexpired subscription.
     */
    public function hasActiveSubscription(User $user): bool
    {
        // Superuser dan legacy admin selalu memiliki akses penuh tanpa batas operasional
        if ($user->isSuperuser() || $user->isAdmin()) {
            return true;
        }

        $subscription = UserSubscription::where('user_id', $user->id)
            ->where('status', 'active')
            ->where('ends_at', '>', now())
            ->with('plan')
            ->latest('id')
            ->first();

        return $subscription !== null;
    }

    /**
     * Check whether user has available AI quota.
     */
    public function canConsumeAi(User $user): bool
    {
        if ($user->isSuperuser() || $user->isAdmin()) {
            return true;
        }

        $subscription = UserSubscription::where('user_id', $user->id)
            ->where('status', 'active')
            ->where('ends_at', '>', now())
            ->with('plan')
            ->latest('id')
            ->first();

        if (! $subscription) {
            return false;
        }

        $plan = $subscription->plan;
        $maxQuota = (int) ($plan?->features['ai_quota'] ?? 0);

        // 0 kuota diartikan unlimited
        if ($maxQuota === 0) {
            return true;
        }

        $usedKey = "user_ai_quota_used_{$user->id}_{$subscription->id}";
        $used = (int) Cache::get($usedKey, 0);

        return $used < $maxQuota;
    }

    /**
     * Increment used AI quota for active subscription.
     */
    public function consumeAi(User $user): void
    {
        if ($user->isSuperuser() || $user->isAdmin()) {
            return;
        }

        $subscription = UserSubscription::where('user_id', $user->id)
            ->where('status', 'active')
            ->where('ends_at', '>', now())
            ->latest('id')
            ->first();

        if ($subscription) {
            $usedKey = "user_ai_quota_used_{$user->id}_{$subscription->id}";
            Cache::increment($usedKey);
        }
    }

    /**
     * Get remaining quota count (null = unlimited).
     */
    public function getRemainingQuota(User $user): ?int
    {
        if ($user->isSuperuser()) {
            return null; // Unlimited
        }

        $subscription = UserSubscription::where('user_id', $user->id)
            ->where('status', 'active')
            ->where('ends_at', '>', now())
            ->with('plan')
            ->latest('id')
            ->first();

        if (! $subscription) {
            return 0;
        }

        $maxQuota = (int) ($subscription->plan?->features['ai_quota'] ?? 0);
        if ($maxQuota === 0) {
            return null; // Unlimited
        }

        $usedKey = "user_ai_quota_used_{$user->id}_{$subscription->id}";
        $used = (int) Cache::get($usedKey, 0);

        return max(0, $maxQuota - $used);
    }
}
