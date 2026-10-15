<?php

namespace App\Http\Middleware;

use App\Models\Learner;
use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Which kind of person a phone token belongs to. The phone app signs in three kinds of people (a Learner by code and
 * PIN, a Teacher or a Parent by email), and they all get the same kind of Sanctum token, so without this check a
 * Teacher's token could reach a Learner route (where `$request->user()` is expected to be a Learner) or the reverse.
 * Usage: `token.actor:learner`, `token.actor:teacher`, `token.actor:parent`, or `token.actor:teacher,parent`.
 *
 * It also stops a deactivated account at once. The website blocks a deactivated account at sign in; a token that was
 * already issued would otherwise keep working until it was deleted, so a deactivated Teacher or Parent gets their
 * tokens revoked here on the very next call. A Teacher or Parent whose email is not confirmed (while confirming is
 * required) is refused the same way, and an Admin is never accepted on the phone at all.
 */
class EnsureTokenActor
{
    public function handle(Request $request, Closure $next, string ...$actors): Response
    {
        $who = $request->user();

        if ($who instanceof Learner) {
            $actor = 'learner';
        } elseif ($who instanceof User) {
            $actor = match ($who->user_type) {
                'Teacher' => 'teacher',
                'Parent' => 'parent',
                default => 'admin',
            };
        } else {
            return $this->refuse('wrong_account_type', 'This account cannot use this part of the app.', 403);
        }

        // Said first and plainly: Admin screens are on the website only, whatever the route.
        if ($actor === 'admin') {
            return $this->refuse('admin_web_only', 'Admin accounts sign in on the website.', 403);
        }

        if (! in_array($actor, $actors, true)) {
            return $this->refuse('wrong_account_type', 'This account cannot use this part of the app.', 403);
        }

        if ($who instanceof User) {
            if ($who->status === 'Inactive') {
                $who->tokens()->delete();

                return $this->refuse('deactivated', 'This account has been deactivated. Contact your school\'s TaraBasa admin.', 401);
            }

            if (config('auth.require_verified_email', true) && ! $who->hasVerifiedEmail()) {
                return $this->refuse('email_not_confirmed', 'Please confirm your email first.', 403);
            }

            if ($who->user_type === 'Teacher' && $who->teacher && $who->teacher->status === 'Rejected') {
                $who->tokens()->delete();

                return $this->refuse('not_approved', 'Your teacher registration was not approved.', 401);
            }
        }

        return $next($request);
    }

    private function refuse(string $code, string $message, int $status): Response
    {
        return response()->json(['error' => $code, 'message' => $message], $status);
    }
}
