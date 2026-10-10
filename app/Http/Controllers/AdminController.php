<?php

namespace App\Http\Controllers;

use App\Console\Commands\SyncAdminAccount;
use App\Models\AdminAction;
use App\Models\Teacher;
use App\Models\User;
use App\Services\ServiceCheck;
use App\Support\AdminHealth;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Illuminate\View\View;

/**
 * The Admin screens: Overview, Approvals, Accounts, System health and Activity log (Admin Actor Prompt, Section 3).
 *
 * The Admin surface stays small and auditable on purpose (Section 1): accounts, never learners or content. Every
 * rule that matters is checked here on the server, not only hidden in the page: only a Pending teacher can be
 * approved or rejected, only a Rejected one can be reopened, approval needs a verified email, an Admin account can
 * never be the target of anything, and every change is written to the Activity log with the Admin's name.
 */
class AdminController extends Controller
{
    /** What the Admin picks when rejecting a teacher. The teacher sees the reason the next time they try to sign in. */
    public const REJECT_REASONS = [
        'Could not verify the employee ID',
        'Not a teacher at this school',
        'Duplicate account',
        'Other',
    ];

    private const PER_PAGE = 25;

    /** Overview: what needs the Admin today, then the numbers, then the latest Admin actions. */
    public function dashboard(Request $request): View
    {
        $pending = Teacher::with('user')->where('status', 'Pending')->get();
        $unverified = $pending->filter(fn (Teacher $t) => $t->user && $t->user->email_verified_at === null)->count();
        $cards = collect(AdminHealth::cards());
        $mail = $cards->firstWhere('key', 'mail');
        $others = $cards->where('state', 'problem')->where('key', '!=', 'mail');

        $needs = [];

        if ($pending->isNotEmpty()) {
            $n = $pending->count();
            $needs[] = [
                'tone' => 'blue', 'icon' => 'user-circle', 'href' => route('admin.approvals'),
                'title' => $n.' teacher'.($n === 1 ? '' : 's').' waiting for approval',
                'text' => 'Check the employee ID and school, then approve or reject.'.($unverified ? " {$unverified} of them ".($unverified === 1 ? 'has' : 'have').' not verified their email yet.' : ''),
            ];
        }

        if ($mail && $mail['state'] === 'problem') {
            $needs[] = [
                'tone' => 'red', 'icon' => 'envelope-simple', 'href' => route('admin.health'),
                'title' => 'Verification emails may not be going out',
                'text' => $mail['headline'].' New teachers and parents cannot verify their email until this is fixed.',
            ];
        }

        if ($others->isNotEmpty()) {
            $names = $others->pluck('title')->implode(', ');
            $needs[] = [
                'tone' => 'red', 'icon' => 'shield-check', 'href' => route('admin.health'),
                'title' => $others->count() === 1 ? 'One service has a problem' : $others->count().' services have a problem',
                'text' => $names.'. Open System health to see what it said.',
            ];
        }

        // The Admin password this code used to be created with is public (it was in the repository). While it is
        // still in use the Overview says so, loudly.
        $publishedPassword = Hash::check(SyncAdminAccount::PUBLISHED_PASSWORD, (string) $request->user()->password);

        if ($publishedPassword) {
            $needs[] = [
                'tone' => 'amber', 'icon' => 'shield-check', 'href' => null,
                'title' => 'This Admin account still uses the password that was written in the code',
                'text' => 'Anyone who has read the code can sign in as Admin. Set ADMIN_PASSWORD (12 or more characters) in the host settings and redeploy.',
            ];
        }

        $people = User::where('user_type', '!=', 'Admin');

        return view('admin.overview', [
            'needs' => $needs,
            'waitingCount' => $pending->count(),
            'activeTeacherCount' => Teacher::where('status', 'Active')->count(),
            'parentCount' => (clone $people)->where('user_type', 'Parent')->count(),
            'totalAccountCount' => (clone $people)->count(),
            'latest' => AdminAction::orderByDesc('id')->limit(3)->get(),
        ]);
    }

    /** Approvals: teachers waiting for the school check, and the ones already rejected (who can be reopened). */
    public function approvals(Request $request): View
    {
        $tab = $request->query('tab') === 'rejected' ? 'rejected' : 'waiting';
        $status = $tab === 'rejected' ? 'Rejected' : 'Pending';

        return view('admin.approvals', [
            'tab' => $tab,
            'teachers' => Teacher::with('user')->where('status', $status)->orderBy('id')->limit(100)->get(),
            'waitingCount' => Teacher::where('status', 'Pending')->count(),
            'rejectedCount' => Teacher::where('status', 'Rejected')->count(),
            'reasons' => self::REJECT_REASONS,
        ]);
    }

    /** Accounts: every teacher and parent, one search, one filter, a page at a time. */
    public function accounts(Request $request): View
    {
        $filter = in_array($request->query('filter'), ['teachers', 'parents', 'inactive'], true) ? $request->query('filter') : 'all';
        $q = is_string($request->query('q')) ? trim($request->query('q')) : '';

        // Admin never appears in its own list (Rule 1): structurally excluded by this query, not just hidden in the view.
        $base = User::where('user_type', '!=', 'Admin');

        $accounts = (clone $base)
            ->with('teacher')
            ->when($filter === 'teachers', fn ($query) => $query->where('user_type', 'Teacher'))
            ->when($filter === 'parents', fn ($query) => $query->where('user_type', 'Parent'))
            ->when($filter === 'inactive', fn ($query) => $query->where('status', 'Inactive'))
            ->when($q !== '', fn ($query) => $query->where(function ($w) use ($q) {
                $like = '%'.str_replace(['%', '_'], '', $q).'%'; // wildcards typed by the user mean nothing
                $w->where('first_name', 'like', $like)->orWhere('last_name', 'like', $like)->orWhere('email', 'like', $like);
            }))
            ->orderByDesc('created_at')->orderByDesc('id')
            ->paginate(self::PER_PAGE)
            ->withQueryString();

        return view('admin.accounts', [
            'accounts' => $accounts,
            'filter' => $filter,
            'q' => $q,
            'counts' => [
                'all' => (clone $base)->count(),
                'teachers' => (clone $base)->where('user_type', 'Teacher')->count(),
                'parents' => (clone $base)->where('user_type', 'Parent')->count(),
                'inactive' => (clone $base)->where('status', 'Inactive')->count(),
            ],
        ]);
    }

    /** System health: a card per service, what went wrong in the last 14 days, and the technical details. */
    public function health(): View
    {
        $check = AdminHealth::latestCheck();

        return view('admin.health', [
            'cards' => AdminHealth::cards(),
            'checkedAt' => $check?->created_at,
            'checkSummary' => $check?->what,
            'technical' => $check?->body,
            'problems' => AdminHealth::problems(),
            'wakeUrls' => AdminHealth::wakeUrls(),
            'gmailConfigured' => config('services.gmail_send.client_id') && config('services.gmail_send.client_secret'),
        ]);
    }

    /** Activity log: everything the Admin did to an account, newest first. */
    public function log(): View
    {
        return view('admin.log', ['actions' => AdminAction::orderByDesc('id')->paginate(30)]);
    }

    /**
     * "Check the services now": asks the activity generator, the reading checker and the adaptive recommender from
     * this very server whether they answer, and writes the result to the diary shown on System health. The page wakes
     * the sleeping services from the Admin's own browser first (a request from this server does not wake them).
     */
    public function checkServices(ServiceCheck $check): RedirectResponse
    {
        // A sleeping free service can take a minute to answer its first request.
        set_time_limit(300);

        // One check at a time, and not again within a minute (it asks three outside services and writes a row).
        if (! Cache::add('service-check-running', true, 60)) {
            return redirect()->route('admin.health')->with('status', 'A check ran a moment ago. Please wait a minute, then look at the cards below.');
        }

        $result = $check->runAndRecord();

        return redirect()->route('admin.health')->with('status', $result['summary']);
    }

    /**
     * Approve a teacher: gives them access to real class rosters. Only a waiting teacher can be approved, and only
     * once their email is verified (it proves the address works before they get rosters). Takes effect on their next
     * request: nothing is cached in the session, every gated action re-checks the database.
     */
    public function activateTeacher(Request $request, Teacher $teacher): RedirectResponse
    {
        $teacher->loadMissing('user');
        $name = trim($teacher->user->first_name.' '.$teacher->user->last_name);

        if ($teacher->status !== 'Pending') {
            return back()->withErrors(['admin' => "{$name} is not waiting for approval (the account is {$teacher->status}). Nothing was changed."]);
        }

        if ($teacher->user->email_verified_at === null) {
            return back()->withErrors(['admin' => "{$name} has not verified their email yet. Ask them to open the link we sent, or send it again, then approve."]);
        }

        $teacher->update(['status' => 'Active', 'rejection_reason' => null]);
        AdminAction::record($request->user(), 'approved', $teacher->user, $teacher->school_name);

        return back()->with('status', "{$name} approved.");
    }

    /**
     * Reject a teacher, with a reason the teacher sees the next time they try to sign in. Only a waiting teacher can be
     * rejected, so a wrong address typed into the URL cannot lock out an Active teacher.
     */
    public function rejectTeacher(Request $request, Teacher $teacher): RedirectResponse
    {
        $data = $request->validate([
            'reason' => ['required', 'string', 'in:'.implode(',', self::REJECT_REASONS)],
            'note' => [($request->input('reason') === 'Other' ? 'required' : 'nullable'), 'string', 'max:140'],
        ], [
            'reason.required' => 'Pick a reason. The teacher will see it.',
            'reason.in' => 'Pick one of the listed reasons.',
            'note.required' => 'Add a short note when the reason is "Other".',
        ]);

        $teacher->loadMissing('user');
        $name = trim($teacher->user->first_name.' '.$teacher->user->last_name);

        if ($teacher->status !== 'Pending') {
            return back()->withErrors(['admin' => "{$name} is not waiting for approval (the account is {$teacher->status}). Nothing was changed."]);
        }

        $note = trim((string) ($data['note'] ?? ''));
        $reason = $data['reason'] === 'Other' ? $note : $data['reason'].($note !== '' ? ': '.$note : '');

        $teacher->update(['status' => 'Rejected', 'rejection_reason' => mb_substr($reason, 0, 200)]);
        AdminAction::record($request->user(), 'rejected', $teacher->user, $teacher->school_name, $reason);

        return back()->with('status', "{$name} rejected.");
    }

    /** Bring a rejected teacher back to the waiting list (a wrong click, or new proof from the teacher). */
    public function reopenTeacher(Request $request, Teacher $teacher): RedirectResponse
    {
        $teacher->loadMissing('user');
        $name = trim($teacher->user->first_name.' '.$teacher->user->last_name);

        if ($teacher->status !== 'Rejected') {
            return back()->withErrors(['admin' => "{$name} was not rejected, so there is nothing to reopen."]);
        }

        $teacher->update(['status' => 'Pending', 'rejection_reason' => null]);
        AdminAction::record($request->user(), 'reopened', $teacher->user, $teacher->school_name);

        return back()->with('status', "{$name} is back in the waiting list.");
    }

    /**
     * Flips a User's account status Active <-> Inactive. Applies to Teachers and Parents alike. Structurally cannot
     * target an Admin (Rule 1): guarded here too, not just by the query that built the list. An optional note is kept
     * in the Activity log.
     */
    public function toggleUserStatus(Request $request, User $user): RedirectResponse
    {
        abort_if($user->user_type === 'Admin', 403, 'Admin accounts cannot be toggled.');

        $data = $request->validate(['note' => ['nullable', 'string', 'max:140']]);

        $user->update([
            'status' => $user->status === 'Active' ? 'Inactive' : 'Active',
        ]);

        AdminAction::record($request->user(), $user->status === 'Inactive' ? 'deactivated' : 'activated', $user, null, $data['note'] ?? null);

        return back()->with('status', "{$user->first_name} {$user->last_name} is now {$user->status}.");
    }

    /**
     * Sends the verification email again, for a person who says it never arrived (it matters most when email was
     * broken for a while). Never for an Admin, and never for someone who is already verified.
     */
    public function resendVerification(Request $request, User $user): RedirectResponse
    {
        abort_if($user->user_type === 'Admin', 403);

        $name = trim($user->first_name.' '.$user->last_name);

        if ($user->email_verified_at !== null) {
            return back()->withErrors(['admin' => "{$name} has already verified their email."]);
        }

        // Once a minute per person: the button cannot be used to fill someone's inbox.
        if (! Cache::add('admin-resend-verification:'.$user->id, true, 60)) {
            return back()->withErrors(['admin' => "An email was just sent to {$name}. Please wait a minute before sending it again."]);
        }

        try {
            $user->sendEmailVerificationNotification();
        } catch (\Throwable $e) {
            return back()->withErrors(['admin' => "The email could not be sent ({$name}). Open System health to see if email sending is working."]);
        }

        AdminAction::record($request->user(), 'resent', $user);

        return back()->with('status', "Verification email sent to {$name}.");
    }
}
