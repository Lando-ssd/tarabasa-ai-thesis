<?php

namespace App\Http\Controllers;

use App\Models\Learner;
use App\Models\ParentAccount;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class LearnerController extends Controller
{
    /**
     * Grade-specific placement questions (Parent Actor Prompt Step 4.4,
     * expanded per grade to match the design reference). Deliberately
     * Yes/No only — the reference's third "Not Sure" option has no
     * defined scoring rule in the actor prompt, so it's dropped rather
     * than inventing behavior for it.
     */
    public const PLACEMENT_QUESTIONS = [
        'Grade 1' => [
            'Can your child identify and name most letters of the alphabet?',
            'Can your child say the sound each letter makes?',
            'Can your child blend letter sounds together to form simple words (e.g. c-a-t = cat)?',
        ],
        'Grade 2' => [
            'Can your child read short sentences on their own without help?',
            'Can your child recognize common sight words by sight (e.g. "the", "was", "said")?',
            'Can your child answer a simple question about something they just read?',
        ],
        'Grade 3' => [
            'Can your child read a short paragraph smoothly, without sounding out most words?',
            'Can your child explain the main idea of a short story in their own words?',
            "Can your child figure out an unfamiliar word's meaning from context?",
        ],
    ];

    public const AVATARS = ['🦁', '🐰', '🦊', '🐻', '🐼', '🐯', '🐨', '🐸'];

    /**
     * The language spoken at home (the manuscript's Create Child Profile figure). Context for the
     * teacher only: the curriculum starts from the child's first language, so it helps to know it.
     * Age and school are left out on purpose (the grade gives the level, the class gives the school).
     */
    public const HOME_LANGUAGES = ['Filipino', 'Cebuano', 'Hiligaynon', 'Ilocano', 'Waray', 'Bikol', 'Kapampangan', 'Pangasinan', 'English', 'Other'];

    /** What helps the child most. These switch on supports; they never decide the activities. */
    public const SUPPORTS = [
        'read_aloud' => 'Hearing the words read aloud',
        'pictures' => 'Pictures with the words',
        'games' => 'Short games',
        'reading_with' => 'Someone reading with them',
        'praise' => 'Praise and rewards',
    ];

    /** Topics the child likes; they feed activity suggestions. */
    public const INTERESTS = ['animals' => 'Animals', 'food' => 'Food', 'family' => 'Family', 'vehicles' => 'Vehicles', 'nature' => 'Nature'];

    /**
     * Letters (any language/script), spaces, hyphens, and apostrophes only
     * — so "Anne-Marie" and "O'Brien" still work, but a name can never
     * contain a digit. \p{L} rather than a plain a-z so Filipino names
     * with accented characters aren't rejected as "invalid".
     */
    private const NAME_REGEX = '/^[\p{L}\s\'\-]+$/u';

    /**
     * Parent Dashboard / Child Selector — Parent Actor Prompt Step 3.
     */
    public function index(Request $request): View
    {
        $parent = $request->user()->parentProfile;

        $learners = $parent->learners()
            ->with('schoolClass')
            ->orderBy('first_name')
            ->get();

        return view('parent.children.index', [
            'parent' => $parent,
            'learners' => $learners,
        ]);
    }

    /**
     * The Add-a-Learner wizard (Parent Actor Prompt Step 4). Single page,
     * client-side steps — the server only ever sees one final submission.
     */
    public function create(Request $request): View
    {
        return view('parent.children.create', [
            'placementQuestions' => self::PLACEMENT_QUESTIONS,
            'avatars' => self::AVATARS,
        ]);
    }

    public function store(Request $request): View
    {
        $validated = $request->validate([
            'first_name' => ['required', 'string', 'max:100', 'regex:' . self::NAME_REGEX],
            'middle_name' => ['nullable', 'string', 'max:100'],
            'last_name' => ['required', 'string', 'max:100', 'regex:' . self::NAME_REGEX],
            'grade_level' => ['required', Rule::in(['Grade 1', 'Grade 2', 'Grade 3'])],
            'avatar_id' => ['required', Rule::in(self::AVATARS)],
            'theme_color' => ['nullable', Rule::in(['blue', 'pink'])],
            // The cropped photo (if the Parent chose "Upload a photo" instead
            // of a preset) arrives as a real file — Cropper.js writes the
            // cropped result back into this same file input client-side, so
            // it's a normal multipart upload, not a base64 field to trust.
            // Laravel's 'image' rule verifies actual image content via
            // getimagesize(), not the filename extension.
            'avatar_photo' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
            'reading_stage' => ['required', Rule::in(['starting', 'letters', 'blending', 'sentences', 'independent', 'unsure'])],
            // Learning style is no longer asked: the research does not support teaching to a style
            // (Pashler et al., 2008). Supports and topics are asked instead.
            'home_language' => ['nullable', Rule::in(self::HOME_LANGUAGES)],
            'supports' => ['nullable', 'array'],
            'supports.*' => ['string', Rule::in(array_keys(self::SUPPORTS))],
            'interests' => ['nullable', 'array'],
            'interests.*' => ['string', Rule::in(array_keys(self::INTERESTS))],
            // "Not sure" is 'unsure': no signal, never counted as a wrong answer.
            'q1' => ['required', Rule::in(['yes', 'no', 'unsure'])],
            'q2' => ['required', Rule::in(['yes', 'no', 'unsure'])],
            'q3' => ['required', Rule::in(['yes', 'no', 'unsure'])],
            'pin' => ['required', 'digits:4'],
            'pin_confirmation' => ['required', 'same:pin'],
        ], [
            'first_name.regex' => 'First name may only contain letters, spaces, hyphens, and apostrophes — no numbers.',
            'last_name.regex' => 'Last name may only contain letters, spaces, hyphens, and apostrophes — no numbers.',
            'avatar_photo.image' => 'That file doesn\'t look like a real image.',
            'avatar_photo.mimes' => 'Photos must be a JPG, PNG, or WEBP file.',
            'avatar_photo.max' => 'Photos must be 2MB or smaller.',
        ]);

        // The placement score — not the self-report — is what actually sets
        // the starting level (Parent Actor Prompt Step 4.4). Computed here,
        // server-side, never trusted from the client.
        $answers = collect([$validated['q1'], $validated['q2'], $validated['q3']]);
        $yesCount = $answers->filter(fn ($answer) => $answer === 'yes')->count();
        $answeredCount = $answers->filter(fn ($answer) => $answer !== 'unsure')->count();

        // Only the answered questions count. If every answer was "Not sure" there is no signal,
        // so the starting level is the neutral one and the first reading check decides.
        $masteryLevel = match (true) {
            $answeredCount === 0 => 'Developing',
            $yesCount >= 3 => 'Proficient',
            $yesCount === 2 => 'Developing',
            default => 'Beginning',
        };

        $parent = $request->user()->parentProfile;

        // Stored under a random, unguessable filename (not the child's
        // learner_code or name) so the path itself doesn't leak identifying
        // info if it's ever shared or logged.
        $photoPath = $request->hasFile('avatar_photo')
            ? $request->file('avatar_photo')->store('avatars', 'public')
            : null;

        // Kept as given, so the first-login reading check can start where the
        // Parent said the child is instead of at a neutral default.
        $placementAnswers = [
            'q1' => $validated['q1'],
            'q2' => $validated['q2'],
            'q3' => $validated['q3'],
        ];

        $learner = $this->withFreshCode(function () use ($validated, $masteryLevel, $parent, $photoPath, $placementAnswers) {
            $learner = Learner::create([
                'learner_code' => Learner::generateUniqueCode(),
                'class_id' => null,
                'first_name' => $validated['first_name'],
                'middle_name' => $validated['middle_name'] ?? null,
                'last_name' => $validated['last_name'],
                'grade_level' => $validated['grade_level'],
                'pin' => Hash::make($validated['pin']),
                'avatar_id' => $validated['avatar_id'],
                'theme_color' => $validated['theme_color'] ?? 'blue',
                'avatar_photo_path' => $photoPath,
                'mastery_level' => $masteryLevel,
                'home_language' => $validated['home_language'] ?? null,
                'supports' => array_values($validated['supports'] ?? []),
                'interests' => array_values($validated['interests'] ?? []),
                'reading_stage' => $validated['reading_stage'],
                'placement_answers' => $placementAnswers,
                'status' => 'Active',
            ]);

            $parent->learners()->attach($learner->id, [
                'relationship' => null,
                'is_creator' => true,
                'linked_at' => now(),
            ]);

            return $learner;
        });

        return view('parent.children.created', [
            'learner' => $learner,
        ]);
    }

    /**
     * Runs the creation of a child, drawing a new learner code again if two parents happened to draw
     * the same one at the same moment (the database refuses the second). Only that clash is retried.
     */
    private function withFreshCode(callable $create): Learner
    {
        for ($try = 1; ; $try++) {
            try {
                return DB::transaction($create);
            } catch (\Illuminate\Database\UniqueConstraintViolationException $e) {
                if ($try >= 3 || ! str_contains($e->getMessage(), 'learner_code')) {
                    throw $e;
                }
            }
        }
    }

    /**
     * Link an Existing Child's Account — Parent Actor Prompt Step 5, a
     * second guardian joining a Learner someone else already created.
     *
     * The "Find" step is a real safety check (confirm you've got the right
     * child before joining someone else's Learner record to your account),
     * not just decoration copied from the reference — implemented as a
     * plain GET lookup rather than a new JSON/AJAX endpoint, consistent
     * with how the rest of this app is built.
     */
    public function showLink(Request $request): View
    {
        // Nothing about a child is shown from a code alone. This used to look the code up and show
        // the child's name and photo to anyone who typed it, which let a parent walk through the
        // codes and read children's names. Linking now needs the child's PIN as well (see storeLink).
        return view('parent.children.link');
    }

    /**
     * The child's card: their name, Learner Code and QR code on one printable page. The QR holds the code only
     * (never the PIN). Only a Parent linked to the child can open it.
     */
    public function card(Request $request, Learner $learner): View
    {
        $linked = $request->user()->parentProfile?->learners()->whereKey($learner->id)->exists();

        abort_unless($linked, 403, 'That child is not linked to your account.');

        return view('parent.children.card', ['learner' => $learner]);
    }

    public function storeLink(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'learner_code' => ['required', 'string', 'max:20'],
            'pin' => ['required', 'digits:4'],
            'relationship' => ['required', Rule::in(['Mother', 'Father', 'Guardian'])],
        ], [
            'pin.required' => "Type the child's 4 digit PIN.",
            'pin.digits' => "The PIN is 4 digits.",
        ]);

        $parent = $request->user()->parentProfile;
        $code = \App\Support\LearnerCode::normalize($validated['learner_code']);

        // Linking is the way someone gains access to a child's name, photo, progress and alerts, so
        // it takes BOTH the code and the PIN (which the child's other guardian can give), and a wrong
        // guess is limited per parent and per code. A learner code has only about ten thousand
        // possibilities a year, which is nothing to someone trying them all.
        $parentKey = 'link-child|parent:'.$parent->id;
        $codeKey = 'link-child|code:'.$code;
        if (\Illuminate\Support\Facades\RateLimiter::tooManyAttempts($parentKey, 5) || \Illuminate\Support\Facades\RateLimiter::tooManyAttempts($codeKey, 5)) {
            $minutes = (int) ceil(max(\Illuminate\Support\Facades\RateLimiter::availableIn($parentKey), \Illuminate\Support\Facades\RateLimiter::availableIn($codeKey)) / 60);

            return back()->withErrors(['learner_code' => "Too many tries. Please wait {$minutes} ".($minutes === 1 ? 'minute' : 'minutes').' and try again.'])->withInput($request->only('learner_code', 'relationship'));
        }

        $learner = Learner::where('learner_code', $code)->first();

        // One message whether the code is unknown or the PIN is wrong, so it never says which.
        if (! $learner || ! \Illuminate\Support\Facades\Hash::check($validated['pin'], $learner->pin)) {
            \Illuminate\Support\Facades\RateLimiter::hit($parentKey, 900);
            \Illuminate\Support\Facades\RateLimiter::hit($codeKey, 900);

            return back()->withErrors(['learner_code' => 'That learner code and PIN do not match. Ask the child\'s other guardian for both.'])->withInput($request->only('learner_code', 'relationship'));
        }

        if ($parent->learners()->where('learners.id', $learner->id)->exists()) {
            return back()->withErrors(['learner_code' => 'This child is already linked to your account.'])->withInput($request->only('learner_code', 'relationship'));
        }

        \Illuminate\Support\Facades\RateLimiter::clear($parentKey);
        \Illuminate\Support\Facades\RateLimiter::clear($codeKey);

        // Tell the guardians who are already linked, so nobody can be added without them knowing.
        \App\Models\Notification::notifyForLearner(
            $learner,
            \App\Models\Notification::TYPE_GUARDIAN_LINKED,
            "{$request->user()->first_name} {$request->user()->last_name} was linked to {$learner->first_name} as {$validated['relationship']}.",
            includeTeacher: false
        );

        $parent->learners()->attach($learner->id, [
            'relationship' => $validated['relationship'],
            'is_creator' => false,
            'linked_at' => now(),
        ]);

        return redirect()
            ->route('parent.children.index')
            ->with('status', "You're now linked to {$learner->first_name} as a guardian.");
    }
}
