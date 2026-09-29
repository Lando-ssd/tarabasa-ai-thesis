<?php

namespace App\Http\Controllers;

use App\Models\Activity;
use App\Models\ActivityBundle;
use App\Models\SchoolClass;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Activity Bundles — the instructor's relayed request: create a named bundle ("Bundle 1"), drop
 * Approved activities into it, then assign the whole bundle to a class. An activity dropped into a
 * bundle that is already assigned to a class reaches that class immediately, with no separate
 * assign step — LearnerAuthService resolves bundle membership live, not from a snapshot.
 *
 * This is a distinct concept from Activity::$bundle_title (gemini_activity_gen's own "bundle" —
 * one generation call's batch of Easy/Medium/Hard levels). Do not conflate the two.
 */
class BundleController extends Controller
{
    /** New bundle — a plain named folder, empty until activities are dropped into it. */
    public function store(Request $request): RedirectResponse
    {
        $teacher = $request->user()->teacher;

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:80'],
        ], [
            'name.required' => 'Give the bundle a name.',
        ]);

        ActivityBundle::create([
            'teacher_id' => $teacher->id,
            'name' => $validated['name'],
        ]);

        return redirect()->route('teacher.activities.index')
            ->with('status', "Bundle \"{$validated['name']}\" created. Drop approved activities into it, then assign it to a class.");
    }

    /**
     * One bundle's window: its activities and the classes it is assigned to. Fetched when a
     * bundle tray opens, same pattern as an activity's own window. Assigning a NEW class to the
     * bundle happens from that class's own window (its Bundles tab) — the one clear place for it,
     * matching how the instructor described the flow ("assign the bundle to a class").
     */
    public function window(Request $request, ActivityBundle $bundle): View
    {
        $this->authorizeOwnership($request, $bundle);
        $teacher = $request->user()->teacher;

        return view('teacher.bundles._window', [
            'bundle' => $bundle->load(['activities' => fn ($q) => $q->orderBy('title'), 'classes' => fn ($q) => $q->orderBy('name')]),
            'locked' => $teacher->status !== 'Active',
        ]);
    }

    /**
     * Drop an Approved activity into a bundle — drag and drop on the board, or the card's own
     * "Add to bundle" menu. Both post here the same way.
     */
    public function addActivity(Request $request, ActivityBundle $bundle): RedirectResponse|JsonResponse
    {
        $this->authorizeOwnership($request, $bundle);
        $teacher = $request->user()->teacher;

        $validated = $request->validate(['activity_id' => ['required', 'integer']]);

        $activity = Activity::where('created_by_teacher_id', $teacher->id)
            ->where('status', 'Approved')
            ->find($validated['activity_id']);

        abort_if(! $activity, 403, 'Only your own Approved activities can join a bundle.');

        $bundle->activities()->syncWithoutDetaching([$activity->id => ['added_at' => now()]]);

        $message = $bundle->classes->isEmpty()
            ? "\"{$activity->title}\" added to \"{$bundle->name}\". Assign the bundle to a class to deliver it."
            : "\"{$activity->title}\" added to \"{$bundle->name}\" — already reaching {$bundle->classes->pluck('name')->join(', ')}.";

        return $this->respond($request, $message);
    }

    public function removeActivity(Request $request, ActivityBundle $bundle, Activity $activity): RedirectResponse|JsonResponse
    {
        $this->authorizeOwnership($request, $bundle);

        $bundle->activities()->detach($activity->id);

        return $this->respond($request, "\"{$activity->title}\" removed from \"{$bundle->name}\".");
    }

    /**
     * Un-assign a class from here, the bundle's own window (fetched into the Activities page's
     * #dlg) — stays on that page, unlike ClassController::unassignBundle(), which does the same
     * detach but returns to the Classes page since it's reached from there instead.
     */
    public function unassignClass(Request $request, ActivityBundle $bundle, SchoolClass $class): RedirectResponse|JsonResponse
    {
        $this->authorizeOwnership($request, $bundle);
        abort_if($class->teacher_id !== $request->user()->teacher->id, 403);

        $bundle->classes()->detach($class->id);

        return $this->respond($request, "\"{$bundle->name}\" no longer assigned to {$class->name}.");
    }

    /** A bundle nobody needs any more. Its activities and class links go with it (cascade). */
    public function destroy(Request $request, ActivityBundle $bundle): RedirectResponse
    {
        $this->authorizeOwnership($request, $bundle);
        $name = $bundle->name;
        $bundle->delete();

        return redirect()->route('teacher.activities.index')->with('status', "Bundle \"{$name}\" deleted.");
    }

    private function authorizeOwnership(Request $request, ActivityBundle $bundle): void
    {
        abort_if($bundle->teacher_id !== $request->user()->teacher->id, 403);
    }

    private function respond(Request $request, string $message): RedirectResponse|JsonResponse
    {
        if ($request->expectsJson()) {
            return response()->json(['message' => $message]);
        }

        return back()->with('status', $message);
    }
}
