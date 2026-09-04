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
            // The cropped photo (if the Parent chose "Upload a photo" instead
            // of a preset) arrives as a real file — Cropper.js writes the
            // cropped result back into this same file input client-side, so
            // it's a normal multipart upload, not a base64 field to trust.
            // Laravel's 'image' rule verifies actual image content via
            // getimagesize(), not the filename extension.
            'avatar_photo' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
            'reading_stage' => ['required', Rule::in(['starting', 'letters', 'blending', 'sentences', 'independent', 'unsure'])],
            'learning_style' => ['required', Rule::in(['Visual', 'Listening', 'Hands-on'])],
            'q1' => ['required', Rule::in(['yes', 'no'])],
            'q2' => ['required', Rule::in(['yes', 'no'])],
            'q3' => ['required', Rule::in(['yes', 'no'])],
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
        $yesCount = collect([$validated['q1'], $validated['q2'], $validated['q3']])
            ->filter(fn ($answer) => $answer === 'yes')
            ->count();

        $masteryLevel = match (true) {
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

        $learner = DB::transaction(function () use ($validated, $masteryLevel, $parent, $photoPath) {
            $learner = Learner::create([
                'learner_code' => Learner::generateUniqueCode(),
                'class_id' => null,
                'first_name' => $validated['first_name'],
                'middle_name' => $validated['middle_name'] ?? null,
                'last_name' => $validated['last_name'],
                'grade_level' => $validated['grade_level'],
                'pin' => Hash::make($validated['pin']),
                'avatar_id' => $validated['avatar_id'],
                'avatar_photo_path' => $photoPath,
                'mastery_level' => $masteryLevel,
                'learning_style' => $validated['learning_style'],
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
        $code = $request->query('code');
        $foundLearner = null;
        $codeError = null;

        if ($code !== null && trim($code) !== '') {
            $parent = $request->user()->parentProfile;
            $normalizedCode = strtoupper(trim($code));
            $foundLearner = Learner::where('learner_code', $normalizedCode)->first();

            if (! $foundLearner) {
                $codeError = 'No learner found with that code — double check and try again.';
            } elseif ($parent->learners()->where('learners.id', $foundLearner->id)->exists()) {
                $codeError = 'This child is already linked to your account.';
                $foundLearner = null;
            }
        }

        return view('parent.children.link', [
            'searchedCode' => $code,
            'foundLearner' => $foundLearner,
            'codeError' => $codeError,
        ]);
    }

    public function storeLink(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'learner_code' => ['required', 'string'],
            'relationship' => ['required', Rule::in(['Mother', 'Father', 'Guardian'])],
        ]);

        $parent = $request->user()->parentProfile;
        $code = strtoupper(trim($validated['learner_code']));

        $learner = Learner::where('learner_code', $code)->first();

        if (! $learner) {
            return back()->withErrors(['learner_code' => 'No learner found with that code — double check and try again.'])->withInput();
        }

        if ($parent->learners()->where('learners.id', $learner->id)->exists()) {
            return back()->withErrors(['learner_code' => 'This child is already linked to your account.'])->withInput();
        }

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
