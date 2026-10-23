<?php

namespace App\Services;

use App\Models\AdminAction;
use App\Models\DataErasure;
use App\Models\Learner;
use App\Models\Notification;
use App\Models\ParentAccount;
use App\Models\SchoolClass;
use App\Models\Teacher;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

/**
 * Deleting a child's profile or a whole account, on request (the Data Privacy Act right to erasure, and what an app store asks of an app
 * that lets people create accounts). The ONE place this is decided, so the website, the phone app and the Admin cannot disagree.
 *
 * What the rules are, and why:
 *   1. A child's profile can be removed by a guardian linked to that child (anyone else gets "not found", so a code cannot be probed). If
 *      ANOTHER guardian is also linked, removing only unlinks this guardian: one parent must not be able to destroy the other's access.
 *      The child is deleted for good only when the last guardian removes them.
 *   2. Deleting your own account needs your password again (a phone left open must not be enough; five wrong tries lock it for 15
 *      minutes). A Parent's account takes with it the children who have no other guardian. A Teacher's account takes the classes, the
 *      assignments and the shared library listings; the learners stay (their parents own them) and are simply left without a class.
 *   3. A Teacher who released learners for promotion that nobody has claimed yet cannot delete their account until those are claimed,
 *      otherwise those children would be stuck between grades with no record to claim.
 *   4. The Admin can delete a Teacher or Parent account on request, with a reason that is kept in the activity log. The log keeps no name.
 *   5. What is deleted is deleted: rows, phone sign ins, website sessions and the child's photo file. What stays is activity content a
 *      Teacher wrote (children's reading history points at it) but with the Teacher's name and class details taken off it.
 *   6. A line is kept that a deletion happened (DataErasure), with nothing that could identify the person.
 */
class DataErasureService
{
    public const PASSWORD_ATTEMPTS = 5;

    /** What an Admin picks when deleting an account for someone. "Other" needs a note. */
    public const ADMIN_REASONS = [
        'The person asked us to',
        'Their school asked us to',
        'It was made by mistake',
        'Other',
    ];

    /** @var list<string> files to delete from disk once the database change has gone through */
    private array $photos = [];

    /**
     * A guardian removes a child. Returns what happened so the screen can say it plainly.
     *
     * @return array{erased: bool, message: string}
     *
     * @throws ValidationException with the message under `password`
     */
    public function removeChild(User $user, Learner $learner, string $password): array
    {
        $parent = $user->parentProfile;
        abort_if(! $parent || ! $parent->learners()->where('learners.id', $learner->id)->exists(), 404);

        $this->confirmPassword($user, $password);

        $name = $learner->first_name;
        $others = $learner->parents()->where('parents.id', '!=', $parent->id)->pluck('parents.id');

        if ($others->isNotEmpty()) {
            DB::transaction(function () use ($user, $learner, $parent, $others) {
                $this->leave($user, $parent, $learner, $others->first());
            });

            DataErasure::note('unlinked', 'Parent', 'self');

            return ['erased' => false, 'message' => "You are no longer a guardian of {$name}. {$name}'s profile stays for the other guardian."];
        }

        $this->erasing(fn () => $this->eraseLearner($learner));
        DataErasure::note('child', 'Learner', 'self');

        return ['erased' => true, 'message' => "{$name}'s profile and every reading record were deleted."];
    }

    /**
     * What deleting this account would do, in plain sentences, for the screen that asks "are you sure". `blocked` is set when it cannot be
     * done yet.
     *
     * @return array{role: string, lines: list<string>, blocked: ?string, erasedChildren: list<string>, keptChildren: list<string>}
     */
    public function accountImpact(User $user): array
    {
        $role = (string) $user->user_type;
        $lines = [];
        $blocked = null;
        $erased = [];
        $kept = [];

        if ($role === 'Parent' && ($parent = $user->parentProfile)) {
            foreach ($parent->learners()->get() as $learner) {
                if ($learner->parents()->where('parents.id', '!=', $parent->id)->exists()) {
                    $kept[] = $learner->first_name;
                } else {
                    $erased[] = $learner->first_name;
                }
            }

            if ($erased) {
                $lines[] = $this->nameList($erased).' will be deleted for good, with every reading record, badge and word. They have no other guardian.';
            }
            if ($kept) {
                $lines[] = $this->nameList($kept).' stays for the other guardian. You will only be removed.';
            }
        }

        if ($role === 'Teacher' && ($teacher = $user->teacher)) {
            $classIds = SchoolClass::where('teacher_id', $teacher->id)->pluck('id');
            $learners = $classIds->isEmpty() ? 0 : Learner::whereIn('class_id', $classIds)->count();
            $shared = DB::table('open_repository_listings')->where('teacher_id', $teacher->id)->count();
            $pending = DB::table('promotion_records')->where('released_by_teacher_id', $teacher->id)->where('status', 'Pending')->count();

            if ($classIds->isNotEmpty()) {
                $lines[] = 'Your '.$classIds->count().($classIds->count() === 1 ? ' class is' : ' classes are').' deleted'.($learners > 0 ? ". The {$learners} ".($learners === 1 ? 'learner stays' : 'learners stay').' with their parents and are left without a class.' : '.');
            }
            $lines[] = 'The activities you wrote stay in the system without your name, because children\'s reading history points at them.';
            if ($shared > 0) {
                $lines[] = "The {$shared} ".($shared === 1 ? 'activity' : 'activities').' you shared to the Repository '.($shared === 1 ? 'is' : 'are').' taken out of it. Parents who unlocked '.($shared === 1 ? 'it' : 'them').' will no longer see '.($shared === 1 ? 'it' : 'them').'.';
            }
            if ($pending > 0) {
                $blocked = "You released {$pending} ".($pending === 1 ? 'learner' : 'learners').' for promotion that no teacher has claimed yet. They would be stuck between grades. Ask another teacher to claim them first (Promotions), then come back.';
            }
        }

        $lines[] = 'Your sign ins on the website and the phone app end, and your details are deleted. This cannot be undone.';

        return ['role' => $role, 'lines' => $lines, 'blocked' => $blocked, 'erasedChildren' => $erased, 'keptChildren' => $kept];
    }

    /**
     * A Teacher or Parent deletes their own account.
     *
     * @throws ValidationException under `password` (wrong password) or `account` (not possible yet)
     */
    public function deleteOwnAccount(User $user, string $password): string
    {
        abort_if($user->user_type === 'Admin', 403);

        $this->confirmPassword($user, $password);

        $impact = $this->accountImpact($user);
        if ($impact['blocked']) {
            throw ValidationException::withMessages(['account' => $impact['blocked']]);
        }

        $role = (string) $user->user_type;
        $children = $this->erasing(fn () => $this->eraseAccount($user));
        DataErasure::note('account', $role, 'self', $children);

        return 'Your account was deleted.';
    }

    /**
     * The Admin deletes a Teacher or Parent account on request. Never an Admin.
     *
     * @throws ValidationException under `account` when it cannot be done yet
     */
    public function deleteByAdmin(User $admin, User $target, string $reason, ?string $note): string
    {
        abort_if($target->user_type === 'Admin', 403);

        $impact = $this->accountImpact($target);
        if ($impact['blocked']) {
            throw ValidationException::withMessages(['account' => $impact['blocked']]);
        }

        $role = (string) $target->user_type;
        $children = $this->erasing(fn () => $this->eraseAccount($target));
        DataErasure::note('account', $role, 'admin', $children);
        AdminAction::recordErasure($admin, $role, $reason, $note);

        return "The {$role} account was deleted".($children > 0 ? ' with '.$children.($children === 1 ? ' child' : ' children') : '').'.';
    }

    // ------------------------------------------------------------------------------------------------------------------------

    /**
     * The same limit as the new PIN: five wrong passwords lock this for fifteen minutes.
     *
     * @throws ValidationException
     */
    private function confirmPassword(User $user, string $password): void
    {
        $key = 'erase-password|user:'.$user->id;

        if (RateLimiter::tooManyAttempts($key, self::PASSWORD_ATTEMPTS)) {
            $minutes = (int) ceil(RateLimiter::availableIn($key) / 60);

            throw ValidationException::withMessages(['password' => "Too many tries. Please wait {$minutes} ".($minutes === 1 ? 'minute' : 'minutes').' and try again.']);
        }

        if (! Hash::check($password, $user->password)) {
            RateLimiter::hit($key, 900);

            throw ValidationException::withMessages(['password' => 'That password is not right.']);
        }

        RateLimiter::clear($key);
    }

    /** Runs a deletion in one transaction, then removes the files it freed (a missing file must never undo a deletion). */
    private function erasing(callable $work): mixed
    {
        $this->photos = [];

        try {
            $result = DB::transaction($work);
        } catch (\Throwable $e) {
            Log::error('A deletion failed and was rolled back', ['error' => $e->getMessage()]);

            throw ValidationException::withMessages(['account' => 'Something went wrong and nothing was deleted. Please try again in a moment.']);
        }

        foreach ($this->photos as $path) {
            try {
                Storage::disk('public')->delete($path);
            } catch (\Throwable) {
                // The record is gone; a leftover file is not worth failing the request for.
            }
        }

        return $result;
    }

    /** Every row about one child, then the child. Written out table by table so it does not depend on the database enforcing cascades. */
    private function eraseLearner(Learner $learner): void
    {
        $id = $learner->id;

        $learner->tokens()->delete();

        // A recording still waiting to be scored is deleted with the child, not left in the temporary folder.
        \App\Models\ScoringJob::where('learner_id', $id)->get()->each->discardAudio();

        foreach (['scoring_jobs', 'notifications', 'personal_word_bank', 'learner_badges', 'game_plays', 'repository_unlocks', 'repository_ratings', 'teacher_alert_actions', 'learner_pin_changes', 'promotion_records', 'activity_assignments', 'reading_sessions', 'parent_learners', 'consent_records', 'learner_photos'] as $table) {
            DB::table($table)->where('learner_id', $id)->delete();
        }

        if ($learner->avatar_photo_path) {
            $this->photos[] = $learner->avatar_photo_path;
        }

        // The class teacher would otherwise just see a learner vanish. Told without a name: the point of deleting is that the name goes.
        $class = $learner->schoolClass;
        $teacherUserId = $class?->teacher?->user_id;

        DB::table('learners')->where('id', $id)->delete();

        if ($teacherUserId) {
            Notification::create([
                'recipient_user_id' => $teacherUserId,
                'learner_id' => null,
                'type' => Notification::TYPE_LEARNER_REMOVED,
                'message' => "A learner in {$class->name} was removed. Their parent deleted the profile.",
            ]);
        }
    }

    /** @return int how many children were deleted along with the account */
    private function eraseAccount(User $user): int
    {
        $children = 0;

        if ($parent = $user->parentProfile) {
            $children = $this->eraseParentSide($parent);
        }

        if ($teacher = $user->teacher) {
            $this->eraseTeacherSide($teacher);
        }

        $id = $user->id;
        $role = (string) $user->user_type;

        // The activity log keeps that something was done, not who it was done to.
        DB::table('admin_actions')->where('target_user_id', $id)->update(['target_name' => "a deleted {$role} account", 'detail' => null]);
        DB::table('notifications')->where('recipient_user_id', $id)->delete();
        DB::table('learner_pin_changes')->where('user_id', $id)->update(['user_id' => null]);
        DB::table('consent_records')->where('user_id', $id)->delete();
        DB::table('password_reset_tokens')->where('email', $user->email)->delete();
        $user->tokens()->delete();

        if (Schema::hasTable('sessions')) {
            DB::table('sessions')->where('user_id', $id)->delete();
        }

        $user->delete();

        return $children;
    }

    private function eraseParentSide(ParentAccount $parent): int
    {
        $erased = 0;

        foreach ($parent->learners()->get() as $learner) {
            $others = $learner->parents()->where('parents.id', '!=', $parent->id)->pluck('parents.id');

            if ($others->isEmpty()) {
                $this->eraseLearner($learner);
                $erased++;

                continue;
            }

            $this->leave($parent->user, $parent, $learner, $others->first());
        }

        // What is left that is theirs: ratings they gave, unlocks for children who were deleted, and the links.
        DB::table('repository_ratings')->where('parent_id', $parent->id)->delete();
        DB::table('repository_unlocks')->where('parent_id', $parent->id)->delete();
        DB::table('parent_learners')->where('parent_id', $parent->id)->delete();
        $parent->delete();

        return $erased;
    }

    private function eraseTeacherSide(Teacher $teacher): void
    {
        $id = $teacher->id;
        $classIds = SchoolClass::where('teacher_id', $id)->pluck('id');

        // The learners stay with their parents; they just no longer have a class.
        if ($classIds->isNotEmpty()) {
            DB::table('learners')->whereIn('class_id', $classIds)->update(['class_id' => null]);
        }

        // The activities stay (reading history points at them) but lose the person and the class details written into them.
        DB::table('activities')->where('created_by_teacher_id', $id)->update(['created_by_teacher_id' => null, 'teacher_notes' => null, 'generation_basis' => null]);

        $listingIds = DB::table('open_repository_listings')->where('teacher_id', $id)->pluck('id');
        if ($listingIds->isNotEmpty()) {
            DB::table('repository_ratings')->whereIn('listing_id', $listingIds)->delete();
            DB::table('repository_unlocks')->whereIn('listing_id', $listingIds)->delete();
            DB::table('open_repository_listings')->whereIn('id', $listingIds)->delete();
        }

        DB::table('activity_assignments')->where('assigned_by_teacher_id', $id)->delete();
        DB::table('activity_generations')->where('teacher_id', $id)->delete();
        DB::table('teacher_alert_actions')->where('teacher_id', $id)->delete();
        DB::table('promotion_records')->where('released_by_teacher_id', $id)->delete();
        DB::table('promotion_records')->where('claimed_by_teacher_id', $id)->update(['claimed_by_teacher_id' => null]);

        if ($classIds->isNotEmpty()) {
            DB::table('promotion_records')->whereIn('released_from_class_id', $classIds)->update(['released_from_class_id' => null]);
            DB::table('promotion_records')->whereIn('claimed_into_class_id', $classIds)->update(['claimed_into_class_id' => null]);
            DB::table('activity_assignments')->whereIn('class_id', $classIds)->delete();
            DB::table('classes')->whereIn('id', $classIds)->delete();
        }

        $teacher->delete();
    }

    /**
     * One guardian steps away from a child who stays. An unlock paid for the child is not lost with the parent who happened to press the
     * button, this parent keeps no alerts about the child, and the guardians who stay are told who left (first name and relationship only).
     */
    private function leave(User $user, ParentAccount $parent, Learner $learner, int $keeperParentId): void
    {
        $relationship = $parent->learners()->where('learners.id', $learner->id)->first()?->pivot?->relationship;

        DB::table('repository_unlocks')->where('learner_id', $learner->id)->where('parent_id', $parent->id)->update(['parent_id' => $keeperParentId]);
        DB::table('notifications')->where('recipient_user_id', $user->id)->where('learner_id', $learner->id)->delete();
        DB::table('consent_records')->where('user_id', $user->id)->where('learner_id', $learner->id)->delete();
        $parent->learners()->detach($learner->id);

        $who = $user->first_name.($relationship ? " ({$relationship})" : '');
        Notification::notifyForLearner($learner, Notification::TYPE_GUARDIAN_REMOVED, "{$who} is no longer a guardian of {$learner->first_name}.", includeTeacher: false);
    }

    /** "Ana", "Ana and Ben", "Ana, Ben and Cai". */
    private function nameList(array $names): string
    {
        $last = array_pop($names);

        return $names ? implode(', ', $names).' and '.$last : (string) $last;
    }
}
