<?php

namespace App\Http\Controllers;

use App\Models\LearnerBadge;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * "My Badges" — the real screen behind BadgeService's award logic.
 * Shows every defined badge (config/badges.php), earned ones lit up with
 * their real earned date, unearned ones as a friendly silhouette — never
 * hidden, so a child can see what's still ahead, matching this app's
 * existing "show the honest full picture, not just what's done" pattern
 * (e.g. the diagnostic's own tier disclosure).
 */
class BadgeController extends Controller
{
    public function index(Request $request): View
    {
        return view('learner.badges', [
            'badges' => LearnerBadge::summaryFor($request->user('learner')),
        ]);
    }
}
