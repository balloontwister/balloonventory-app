<?php

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Blocks an authenticated user who has not accepted the current Terms/Privacy,
 * routing every non-allowlisted request to the acceptance interstitial. Catches
 * both first-time acceptance (e.g. invited/magic-link users who never saw the
 * registration checkbox) and re-acceptance after a version bump.
 *
 * ⚠️  This is a HARD gate. Bumping config('legal.terms_version') re-triggers it
 * for EVERY user at once — a material, app-wide interruption. See the warning in
 * config/legal.php before changing that value; edit prose without bumping it for
 * minor changes.
 *
 * Runs before EnsureAccountActive so terms acceptance is the outermost gate; the
 * interstitial routes are allowlisted there too, to avoid a redirect loop for a
 * user who is both frozen and unaccepted.
 */
class EnsureTermsAccepted
{
    /**
     * Exact route names reachable without having accepted.
     *
     * @var list<string>
     */
    private const ALLOWED = [
        'terms.show',
        'terms.accept',
        'logout',
        'locale.switch',
        'impersonate.stop',
    ];

    /**
     * Route-name prefixes reachable without having accepted — so the user can
     * read the policies before agreeing, and finish email verification.
     *
     * @var list<string>
     */
    private const ALLOWED_PREFIXES = [
        'legal.',
        'verification.',
    ];

    /**
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = Auth::user();

        if ($user && $this->needsToAccept($user) && ! $this->isAllowed($request)) {
            return redirect()->route('terms.show');
        }

        return $next($request);
    }

    private function needsToAccept(User $user): bool
    {
        return $user->terms_accepted_at === null
            || $user->terms_version !== config('legal.terms_version');
    }

    private function isAllowed(Request $request): bool
    {
        $name = $request->route()?->getName();

        if ($name === null) {
            return false;
        }

        if (in_array($name, self::ALLOWED, true)) {
            return true;
        }

        foreach (self::ALLOWED_PREFIXES as $prefix) {
            if (str_starts_with($name, $prefix)) {
                return true;
            }
        }

        return false;
    }
}
