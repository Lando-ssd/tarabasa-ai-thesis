<?php

namespace App\Http\Controllers;

use App\Models\LearnerBadge;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * My Badges: all 100 badges with the learner's real state. Opening the page
 * also records any badge their reading history already earned (see
 * App\Services\BadgeService), so it is never out of date.
 */
class BadgeController extends Controller
{
    public function index(Request $request): View
    {
        $learner = $request->user('learner');

        return view('learner.badges', [
            'learner' => $learner,
            'badges' => LearnerBadge::summaryFor($learner),
            'categories' => config('badges.categories'),
        ]);
    }
}
