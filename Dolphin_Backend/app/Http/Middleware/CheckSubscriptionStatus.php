<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;
use App\Models\User;
use App\Models\Organization;
use App\Models\Subscription;
use App\Http\Controllers\SubscriptionController;

class CheckSubscriptionStatus
{
    
    protected SubscriptionController $subscriptionController;

    
    public function __construct(SubscriptionController $subscriptionController)
    {
        $this->subscriptionController = $subscriptionController;
    }

    
    protected array $exemptRoles = [
        'superadmin',
        'dolphinadmin',
        'salesperson',
        'user',
    ];

    
    public function handle(Request $request, Closure $next): Response
    {
        
        $user = Auth::user();

        
        $allow = false;
        $forceBlock = false;
        $blockContext = [
            'latest' => null,
            'status' => 'none',
            'message' => 'You have not selected any plans yet.',
        ];

        
        if ($user && method_exists($user, 'hasRole') && $user->hasRole('organizationadmin')) {
            $organization = $this->resolveOrganizationForUser($user);
            if ($organization) {
                [$orgAllow, $orgForceBlock, $orgBlockContext] = $this->evaluateOrganizationSubscription($organization);
                $allow = $allow || $orgAllow;
                $forceBlock = $forceBlock || $orgForceBlock;
                $blockContext = $orgBlockContext ?: $blockContext;
            }
        }

        
        if (! Auth::check()) {
            $allow = true;
        }

        
        if (! $forceBlock) {
            $allow = $allow || $this->userHasAnyExemptRole($user) || $this->userHasActiveSubscription($user);
        }

        
        if ($allow) {
            return $next($request);
        }

        
        [$latest, $status, $message] = $this->buildBlockPayload($forceBlock, $user, $blockContext);

        return $this->respondBlocked($request, $latest, $status, $message);
    }

    
    private function resolveOrganizationForUser(User $user): ?Organization
    {
        $organization = null;

        if (! empty($user->organization_id)) {
            $organization = Organization::find($user->organization_id);
        }

        if (! $organization && method_exists($user, 'organization')) {
            $organization = $user->organization()->first();
        }

        return $organization;
    }

    
    private function evaluateOrganizationSubscription(Organization $organization): array
    {
        $allow = false;
        $forceBlock = false;
        $blockContext = [
            'latest' => null,
            'status' => 'none',
            'message' => 'You have not selected any plans yet.',
        ];

        $active = $organization->activeSubscription()->first();
        if ($active) {
            if ($this->subscriptionController->hasExpired($active)) {
                $active->update(['status' => 'expired']);
                $forceBlock = true;
                $blockContext['latest'] = $active;
                $blockContext['status'] = 'expired';
                $blockContext['message'] = 'Your organization\'s subscription has expired. Please renew your subscription to continue.';
            } else {
                $allow = true;
            }
        } else {
            $forceBlock = true;
            $latest = Subscription::where('user_id', $organization->user_id)->orderByDesc('created_at')->first();
            $blockContext['latest'] = $latest;
            $blockContext['status'] = $latest?->status ?? 'none';
            $blockContext['message'] = $blockContext['status'] === 'expired'
                ? 'Your organization\'s subscription has expired. Please renew your subscription to continue.'
                : 'You have not selected any plans yet.';
        }

        return [$allow, $forceBlock, $blockContext];
    }

    
    private function buildBlockPayload(bool $forceBlock, ?User $user, array $blockContext): array
    {
        if ($forceBlock) {
            return [$blockContext['latest'], $blockContext['status'], $blockContext['message']];
        }

        $latest = $user?->subscriptions()->orderByDesc('created_at')->first();
        $status = $latest?->status ?? 'none';
        $message = $status === 'expired'
            ? 'Your subscription has expired. Please renew your subscription to continue.'
            : 'You have not selected any plans yet.';

        return [$latest, $status, $message];
    }

    
    private function respondBlocked(Request $request, $latest, string $status, string $message): Response
    {
        if ($request->expectsJson() || $request->is('api/*')) {
            return response()->json([
                'message' => $message,
                'status' => $status,
                
                'subscription_end' => $latest?->ends_at?->toDateTimeString() ?? ($latest?->subscription_end?->toDateTimeString() ?? null),
                'subscription_id' => $latest?->id,
                'redirect_url' => url('/manage-subscription'),
            ], 403);
        }

        return redirect('/manage-subscription')->with('error', $message);
    }

    
    protected function userHasAnyExemptRole(?User $user): bool
    {
        $has = false;

        if (! $user) {
            return $has;
        }

        if (method_exists($user, 'hasAnyRole')) {
            $has = (bool) $user->hasAnyRole(...$this->exemptRoles);
        } elseif (method_exists($user, 'hasRole')) {
            foreach ($this->exemptRoles as $role) {
                if ($user->hasRole($role)) {
                    $has = true;
                    break;
                }
            }
        }

        return $has;
    }

    
    protected function userHasActiveSubscription(?User $user): bool
    {
        if (! $user) {
            return false;
        }

        $hasActive = false;

        if (method_exists($user, 'subscriptions')) {
            $subscription = $user->subscriptions()
                ->where('status', 'active')
                ->orderByDesc('created_at')
                ->first();

            if ($subscription) {
                if ($this->subscriptionController->hasExpired($subscription)) {
                    
                    $subscription->update(['status' => 'expired']);
                    $hasActive = false;
                } else {
                    $hasActive = true;
                }
            }
        }

        return $hasActive;
    }
}
