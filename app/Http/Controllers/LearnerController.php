<?php

namespace App\Http\Controllers;

use App\Models\Learner;
use App\Services\LearnerPinService;
use App\Services\ParentChildService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class LearnerController extends Controller
{
    public function __construct(private readonly ParentChildService $children) {}

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
        $validated = $request->validate($this->children->createRules(), $this->children->createMessages());

        $learner = $this->children->create($request->user(), $validated, $request->file('avatar_photo'));

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

        try {
            $learner = $this->children->link($request->user(), $validated['learner_code'], $validated['pin'], $validated['relationship']);
        } catch (ValidationException $e) {
            // Only the code and the relationship come back into the form, never the PIN.
            return back()->withErrors($e->errors())->withInput($request->only('learner_code', 'relationship'));
        }

        return redirect()
            ->route('parent.children.index')
            ->with('status', "You're now linked to {$learner->first_name} as a guardian.");
    }

    /**
     * "New PIN" on My children: a guardian makes a new PIN for a child who forgot theirs. Every rule lives in LearnerPinService (a linked
     * guardian only, their password again, 3 changes a day, no weak PINs). A PIN the system made is flashed to the next page ONCE so it
     * can be read out; nothing keeps it.
     */
    public function resetPin(Request $request, Learner $learner, LearnerPinService $pins): RedirectResponse
    {
        $validated = $request->validate(LearnerPinService::formRules(), LearnerPinService::formMessages());

        $result = $pins->reset($request->user(), $learner, $validated['password'], $validated['mode'], $validated['pin'] ?? null);

        return redirect()->route('parent.children.index')
            ->with('status', $result['message'])
            ->with('new_pin', $result['pin'] === null ? null : ['learner_id' => $learner->id, 'pin' => $result['pin']]);
    }
}
