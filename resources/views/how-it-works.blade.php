{{--
  How TaraBasa decides (public, no sign in). Every rule the app uses to decide something about a child, a class or an
  activity, with the numbers and where they come from, for the adviser and panel, teachers and parents.

  The numbers are NOT typed here: they come from HowItWorksController, which reads the same constants and config files
  the rules run on, so this page cannot drift from the app (HowItWorksTest pins it). If a rule changes, change it where
  it lives and this page follows. If a NEW rule is added, add a section here in the same commit.

  Copy rules for the whole app: no emoji, no dashes, plain words. It says plainly that none of this is a trained model.
--}}
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>How TaraBasa decides | TaraBasa AI</title>
<meta name="description" content="Every rule TaraBasa AI uses to decide where a child starts, when they move up, what to suggest and when to tell a teacher, with its numbers and sources.">
<link rel="stylesheet" href="{{ asset('css/fonts.css') }}?v={{ substr(md5_file(public_path('css/fonts.css')), 0, 10) }}">
<style>
  :root{ --ink:#15283a; --muted:#587086; --line:#dbe7f3; --bg:#f5f9ff; --blue:#1c7ed6; --orange:#ee8a26; --orange-lip:#b4560b; --soft:#eef6ff; }
  *{ box-sizing:border-box; } html,body{ margin:0; }
  body{ min-height:100vh; padding:28px 20px 56px; background:var(--bg); color:var(--ink); font:16px/1.65 'Inter',system-ui,sans-serif; }
  .page{ width:100%; max-width:920px; margin:0 auto; background:#fff; border:2px solid var(--line); border-radius:28px; box-shadow:0 6px 0 #e3ebf3; padding:34px 36px 32px; }
  .brand{ font:800 28px/1 'Baloo 2',sans-serif; margin-bottom:14px; } .brand span{ color:var(--orange); }
  h1{ margin:0 0 6px; font:700 36px/1.15 'Baloo 2',sans-serif; }
  .updated{ margin:0 0 20px; color:var(--muted); font-size:14px; }
  h2{ margin:34px 0 4px; font:700 24px/1.2 'Baloo 2',sans-serif; scroll-margin-top:16px; }
  h3{ margin:18px 0 4px; font:700 17px/1.3 'Inter',sans-serif; }
  p{ margin:0 0 12px; } ul,ol{ margin:0 0 12px; padding-left:22px; } li{ margin:0 0 6px; }
  .lead{ font-size:18px; }
  .box{ margin:16px 0 8px; padding:14px 18px; border-left:5px solid var(--blue); background:var(--soft); border-radius:0 14px 14px 0; }
  .box b.t{ display:block; margin-bottom:4px; font:700 18px/1.2 'Baloo 2',sans-serif; }
  .sub{ margin:0 0 10px; color:var(--muted); font-size:15px; }
  .rule{ margin:10px 0 0; padding:14px 18px; border:2px solid var(--line); border-radius:18px; }
  .rule .k{ display:block; font:700 12px/1 'Inter',sans-serif; letter-spacing:.08em; text-transform:uppercase; color:var(--muted); margin:10px 0 3px; } .rule .k:first-child{ margin-top:0; }
  .rule p:last-child, .rule ul:last-child{ margin-bottom:0; }
  .num{ display:inline-block; padding:1px 9px; border-radius:999px; background:var(--soft); border:1.5px solid #cfe3f8; font-weight:700; white-space:nowrap; }
  table{ width:100%; border-collapse:collapse; margin:8px 0 12px; font-size:15px; } th,td{ text-align:left; padding:8px 10px; border-bottom:1.5px solid var(--line); vertical-align:top; } th{ font-size:12px; letter-spacing:.06em; text-transform:uppercase; color:var(--muted); }
  .tablewrap{ overflow-x:auto; }
  nav.toc{ margin:18px 0 6px; padding:12px 18px; background:#fafcff; border:2px solid var(--line); border-radius:16px; } nav.toc ol{ columns:2; column-gap:30px; margin:6px 0 0; } nav.toc li{ break-inside:avoid; }
  a{ color:var(--blue); }
  a.btn{ display:inline-block; margin-top:22px; text-decoration:none; padding:13px 24px 12px; border-radius:16px; font:700 18px/1.1 'Baloo 2',sans-serif; letter-spacing:.03em; color:#fff; background:linear-gradient(180deg,#f7a13a,var(--orange)); box-shadow:0 5px 0 var(--orange-lip); }
  a:focus-visible{ outline:3px solid var(--blue); outline-offset:3px; }
  @media (max-width:640px){ .page{ padding:26px 18px 24px; border-radius:22px; } h1{ font-size:29px; } nav.toc ol{ columns:1; } }
</style>
</head>
<body>
<main class="page" role="main">
  <div class="brand">TaraBasa<span>AI</span></div>
  <h1>How TaraBasa decides</h1>
  <p class="updated">Every number on this page is read from the app's own settings, so it matches what the app really does.</p>

  <p class="lead">TaraBasa AI decides a few things about a child, a class or a teacher: where a child starts, when they move up a step, what to suggest, when a teacher should be told, and what to write for a class. This page lists each rule, its numbers, and where they come from.</p>

  <div class="box">
    <b class="t">The short answer</b>
    These are written rules, not a trained model. There is no collection of children's readings to train a model on yet, so every number here is the team's own choice, taken from DepEd documents and reading research, and kept in one settings file so a teacher or the adviser can question it and change it. A few outside services do use speech recognition or an AI language model. They are listed at the end.
  </div>

  <nav class="toc" aria-label="Sections">
    <b>On this page</b>
    <ol>
      <li><a href="#start">Where a child starts</a></li>
      <li><a href="#moves">Moving up or down a step</a></li>
      <li><a href="#levels">What the level names mean</a></li>
      <li><a href="#scoring">How a reading is scored</a></li>
      <li><a href="#patterns">Mistake patterns</a></li>
      <li><a href="#alerts">When a teacher is told</a></li>
      <li><a href="#fit">Can this child read it?</a></li>
      <li><a href="#offered">What a child is offered</a></li>
      <li><a href="#writing">Writing activities for a class</a></li>
      <li><a href="#classes">Class and grade rules</a></li>
      <li><a href="#syllables">The syllable method</a></li>
      <li><a href="#games">Practice games and the word bank</a></li>
      <li><a href="#never">What it does not do</a></li>
      <li><a href="#checking">Checking the numbers</a></li>
      <li><a href="#services">The outside services</a></li>
      <li><a href="#sources">Sources</a></li>
    </ol>
  </nav>

  <h2 id="start">1. Where a child starts</h2>
  <p class="sub">The first reading check, after a parent adds the child.</p>
  <div class="rule">
    <span class="k">What it decides</span>
    <p>Which step of reading a child is on, so the first activities fit them.</p>
    <span class="k">The rule</span>
    <p>The check uses one ladder of seven steps for every child, the same for Grade 1, 2 and 3: @foreach (\App\Support\ReadingLevel::RUNG_LABELS as $r => $label){{ $label }}{{ $loop->last ? '.' : ', ' }}@endforeach It begins on the lowest step that the parent's description of the child and the parent's three yes or no answers suggest, so a Grade 3 child who cannot read yet starts on letters and a Grade 2 child who reads well starts on a story.</p>
    <p>It shows at most <span class="num">{{ $check['maxItems'] }}</span> texts. A score of <span class="num">{{ $check['upAt'] }} percent or more</span> moves the check up one step, <span class="num">under {{ $check['downBelow'] }} percent</span> moves it down one step, and <span class="num">{{ $check['downBelow'] }} to {{ $check['stopTo'] }} percent</span> stops it. Where it stops is the child's step. The child is not shown a score.</p>
    <p><b>Letters are the exception.</b> On the letters step the line for moving up is <span class="num">{{ $check['lettersUpAt'] }} percent</span>, so naming <span class="num">{{ $check['lettersNeeded'] }} of the {{ $check['lettersPerItem'] }}</span> letters moves the child on to the first words. Speech recognition is weakest at single letter names, and with {{ $check['lettersPerItem'] }} letters one name heard differently is already {{ round(($check['lettersPerItem'] - 1) / max(1, $check['lettersPerItem']) * 100) }} percent, which on the usual line would have ended the check on the lowest step. If the first words turn out too hard, the check steps back down by itself, so this costs at most one more text. These numbers are the team's own choice, not the curriculum's.</p>
    <p>The check can stop after any text, so the child's screen never promises a number of texts: the first shows no dots, and from the second on there is one dot for each text reached so far.</p>
    <p>The step is stored as one of three levels:
      @foreach ($tiers as $name => [$from, $to]){{ $name }} for steps {{ $from }} to {{ $to }}{{ $loop->last ? '.' : ', ' }}@endforeach
    </p>
    <span class="k">Where it comes from</span>
    <p>The texts are curriculum coded (MATATAG codes such as EN2PWS-I-2 for letter names) and were written by the team from the English and Reading and Literacy curriculum guides. A Grade 1 and a Grade 2 teacher should review them.</p>
  </div>

  <h2 id="moves">2. Moving up or down a step</h2>
  <p class="sub">After each practice reading.</p>
  <div class="rule">
    <span class="k">What it decides</span>
    <p>Whether a child stays on their step, moves up one, or moves down one.</p>
    <span class="k">The rule</span>
    <ul>
      <li><b>Up:</b> at least <span class="num">{{ $progression['up_readings'] }}</span> readings of <span class="num">{{ $progression['up_accuracy'] }} percent or more</span>, from at least <span class="num">{{ $progression['up_distinct_activities'] }}</span> different activities, among the last <span class="num">{{ $progression['window'] }}</span> readings since the child's last move. The child moves up <b>one</b> step.</li>
      <li><b>Long enough to count:</b> a reading only counts as evidence if it has at least this many words for the step the child is leaving:
        @foreach ($minWords as $m){{ $m['step'] }} {{ $m['words'] }}{{ $loop->last ? '.' : ', ' }}@endforeach
      </li>
      <li><b>Down:</b> <span class="num">{{ $progression['down_readings'] }}</span> readings in a row under <span class="num">{{ $progression['down_accuracy'] }} percent</span>, of texts that were not too long for the child. The child moves down <b>one</b> step. One weak reading never moves anyone.</li>
    </ul>
    <span class="k">Where it comes from</span>
    <p>Reading programmes decide on repeated evidence, not one reading (the Phil-IRI measures a whole passage, and the National Reading Panel, 2000, found repeated oral reading builds fluency). A single perfect reading of six words shows the child read those words, not that they have mastered a step, so one reading cannot move a whole level. The numbers are the team's own, set in <code>config/progression.php</code>.</p>
  </div>

  <h2 id="levels">3. What the level names mean</h2>
  <p class="sub">The words a teacher sees, and the steps a child and a parent see.</p>
  <div class="rule">
    <span class="k">What it decides</span>
    <p>The name shown for how a child reads now.</p>
    <span class="k">The rule</span>
    <p>Teachers see the Department of Education's Phil-IRI words. For one reading of a passage: <span class="num">Independent</span> is 97 percent or more, <span class="num">Instructional</span> is 90 to 96 percent, and <span class="num">Frustration</span> is under 90 percent. A <span class="num">Non-reader</span> is a child whose first check did not get past letters. A child never sees these words: a child sees four friendly steps, Letter Explorer, Word Builder, Sentence Reader and Story Reader. A parent sees the same four steps as their child, with a plain sentence about what that step means, and never the school's words or the system's own level names.</p>
    <span class="k">Where it comes from</span>
    <p>DepEd Order No. 14, s. 2018 (Phil-IRI). The three bands were confirmed on DepEd schools' own reports that cite the order; the order itself was not opened. One source says the 2018 manual is written for Grades 3 to 6, so using the bands for a single passage read by a Grade 1 or Grade 2 child is the team's own extension. The app's own moving up and down rule (section 2) is separate from these bands, and the two are shown side by side, never claimed to be the same thing.</p>
  </div>

  <h2 id="scoring">4. How a reading is scored</h2>
  <p class="sub">When a child reads aloud.</p>
  <div class="rule">
    <span class="k">What it decides</span>
    <p>Which words were read right, which were skipped, and which were said as a different word.</p>
    <span class="k">The rule</span>
    <p>The recording goes to a speech recognition service (Vosk), which writes down the words it heard. TaraBasa lines those words up against the activity's words. Each word is right, skipped or a different word, and extra words are noted. Four fairness rules are applied before the score:</p>
    <ul>
      <li>Words that sound the same (sun and son), spelling variants, numbers (2 and two), a missing apostrophe, and one word written as two (sand and castle) are accepted.</li>
      <li><b>Letter names.</b> When a child names letters aloud (the first check's letters step, and a row of letters in Word Train), the recognizer often writes a letter name as another word ("are" for R, "see" for C) or writes two letters as one word ("hr" for H then R, "cb" for C then B). TaraBasa keeps the service's own matching and repairs only the letters it marked as a different word or as skipped. A heard word is accepted for a letter when it is a spelling of that letter's name (listed for <span class="num">{{ $letterNameCount }}</span> letters), or when it is made of the names of that letter and the letters next to it that the service marked as skipped, in that order. A word that is not the name of the asked letter is still a mistake, a letter that was not asked for never earns anything, nothing is moved to another place in the row (so a row said in the wrong order gets no extra credit), and this step can only add credit: a reading never scores lower because of it. The service's own score is kept next to it.</li>
      <li>A word the recognizer heard as a different word, but only faintly (confidence under <span class="num">{{ $unsureConfidence }}</span>), is marked <b>Not sure</b>. It is left out of the score, the word bank and the alerts. At most <span class="num">{{ $unsureShare }} percent</span> of the words can be Not sure, least confident first, so this can never hide a reading that is mostly wrong. A skipped word is never Not sure.</li>
      <li>Near misses, such as ripe and right, or hit and hat, stay mistakes.</li>
    </ul>
    <span class="k">Honest limits</span>
    <p>Names and rare same-sound words can still be marked wrong. The Not sure numbers were first set with synthesized voices and have to be checked with children's recordings before they are called final. They are in <code>config/reading.php</code>. The letter names were measured the same way: what the live speech service wrote for each letter said alone and for rows of letters, with two synthesized voices (23 October 2026). A child's voice will make the recognizer write other words, so the list (<code>config/reading_words.php</code>) has to be extended from real recordings. This is a written rule, not a trained model.</p>
  </div>

  <h2 id="patterns">5. Mistake patterns</h2>
  <p class="sub">What a child's missed words have in common.</p>
  <div class="rule">
    <span class="k">What it decides</span>
    <p>Whether a child's mistakes show one kind of difficulty, so the suggestion can practise it.</p>
    <span class="k">The rule</span>
    <p>Each missed word is sorted into one of {{ $patterns->count() }} kinds by comparing it with the word the child said. A pattern is reported only after at least <span class="num">{{ $patternMinReadings }}</span> readings and <span class="num">{{ $patternMinMisses }}</span> missed words, and only when it covers at least <span class="num">25 percent</span> of the misses. It is called "early" until <span class="num">{{ $patternSettled }}</span> readings. Each pattern comes with one thing to try and the curriculum code it practises.</p>
    <div class="tablewrap"><table>
      <thead><tr><th>Kind</th><th>What it looks like</th><th>Code</th></tr></thead>
      <tbody>@foreach ($patterns as $p)<tr><td><b>{{ $p['label'] }}</b></td><td>The child {{ $p['teacher'] }}.</td><td>{{ $p['code'] }}</td></tr>@endforeach</tbody>
    </table></div>
    <span class="k">Where it comes from</span>
    <p>The codes are MATATAG competencies from the Reading and Literacy and English guides. Nothing is trained: the sorting is plain comparison of letters and sounds, and it is only reported when there is enough evidence.</p>
  </div>

  <h2 id="alerts">6. When a teacher is told</h2>
  <p class="sub">Alerts on the teacher's screens.</p>
  <div class="rule">
    <span class="k">What it decides</span>
    <p>Which children a teacher should look at today.</p>
    <span class="k">The rules</span>
    <ul>
      <li><b>Needs support:</b> the child's latest reading is under <span class="num">{{ $alerts['below'] }} percent</span> and less than <span class="num">{{ $alerts['fresh'] }} days</span> old. If the last <span class="num">{{ $alerts['inARow'] }}</span> readings were all under that, it says so as stronger evidence. A later good reading clears it.</li>
      <li><b>Ready to move up:</b> the last <span class="num">{{ $alerts['inARow'] }}</span> readings were all <span class="num">{{ $alerts['up'] }} percent or more</span>, not counting readings the app already used to move the child.</li>
      <li><b>Moved up a level:</b> the child's reading level name changed upward in the last 14 days. They stay in the same class and appear in the next reading group.</li>
      <li><b>Gone quiet:</b> the child has read before, but not for <span class="num">{{ $alerts['quiet'] }} days</span> or more.</li>
      <li><b>Just starting to read:</b> the child's measured step is Letters. This is shown kindly, as where reading instruction begins.</li>
    </ul>
    <span class="k">What an alert never does</span>
    <p>An alert shows its evidence and one suggested activity from the teacher's own approved activities. It never gives anything to a child by itself. The teacher presses the button, or marks it handled.</p>
  </div>

  <h2 id="fit">7. Can this child read it?</h2>
  <p class="sub">Before a teacher gives an activity.</p>
  <div class="rule">
    <span class="k">What it decides</span>
    <p>Whether an activity is too long for a child, a group or a class.</p>
    <span class="k">The rule</span>
    <p>An activity's level (Easy, Medium, Hard) only says how hard it is for its grade, not whether this child can read it. So the child's step sets how many words they can read in one activity:</p>
    <div class="tablewrap"><table>
      <thead><tr><th>Step</th><th>Comfortable</th><th>A stretch (up to)</th></tr></thead>
      <tbody>@foreach ($limits as $l)<tr><td>{{ $l['step'] }}</td><td>{{ $l['comfortable'] }} words</td><td>{{ $l['stretch'] }} words</td></tr>@endforeach</tbody>
    </table></div>
    <p>More than the stretch is too long and cannot be given. A stretch can be given with a note. For the first two steps, sentences and stories count <span class="num">{{ $connectedWeight }}</span> times their length, because connected text is harder than a list of single words. An activity for a whole class or group is refused when at least <span class="num">{{ $blockShare }} percent</span> of the children whose level is known could not read it, so it can be given to the reading group that can.</p>
    <span class="k">Where it comes from</span>
    <p>The numbers are anchored on the first check's own texts and checked against the activity writer's own length bands. They are the team's figures, not DepEd's, because the curriculum guides give no word counts. They are in <code>config/activity_fit.php</code>.</p>
  </div>

  <h2 id="offered">8. What a child is offered to read</h2>
  <p class="sub">The child's Read screen.</p>
  <div class="rule">
    <span class="k">What it decides</span>
    <p>The order of the activities a child sees.</p>
    <span class="k">The rule</span>
    <p>A child sees everything a teacher gave them (to the whole class, to their reading group, or to them alone) and everything a parent unlocked. Only the order changes: the recommender's pick for the child comes first, marked "Picked just for you", and, if a clear mistake pattern exists, a story that practises it comes next with a kind tag such as "Practice word endings". A text far too long for the child goes last, marked "A bigger one for later".</p>
    <span class="k">What it never does</span>
    <p>It never hides an activity.</p>
  </div>

  <h2 id="writing">9. Writing activities for a class</h2>
  <p class="sub">The "For a class" option when a teacher writes new activities.</p>
  <div class="rule">
    <span class="k">What it decides</span>
    <p>What to tell the activity writer about a class, so the texts fit the children.</p>
    <span class="k">The rule</span>
    <p>The activity writer is an AI service that knows nothing about the children. When a teacher picks a class, TaraBasa sends it a short note built from the class's own readings: the step most of the class is on and how many words suit it, the main kind of mistake and the words that several children missed, and the topics parents said their children like. A thing is only said about the class when at least <span class="num">{{ $basisShared }}</span> children show it, so one child's habit is never presented as the class's. The teacher reads those facts before pressing Write, and they stay on each activity ("Written for Sampaguita because...").</p>
    <span class="k">Did it work?</span>
    <p>Teacher Analytics then compares, for the same children, readings of the activities written for their class against the teacher's general activities. Each child is compared with themselves. A number is given only when at least <span class="num">{{ $outcomePaired }}</span> children read both kinds, and a difference under <span class="num">{{ $outcomeSame }} points</span> is called about the same. It is a signal to watch and not proof, because a text can be easier or more interesting and the order they were read in matters.</p>
  </div>

  <h2 id="classes">10. Class and grade rules</h2>
  <p class="sub">Who can be in a class, and who can open one.</p>
  <div class="rule">
    <span class="k">The rules</span>
    <ul>
      <li><b>One grade per class.</b> A Grade 1 class is only for Grade 1 learners, Grade 2 for Grade 2, Grade 3 for Grade 3, even when a teacher handles more than one grade. The code is checked as it is typed, so the Add button stays off with the reason until the child can be added.</li>
      <li><b>Grades are confirmed by the school.</b> Which grade a teacher teaches is decided by the school. Taking a grade away saves at once. Adding a grade is a request that the Admin confirms with the school before the teacher can open a class for it.</li>
      <li><b>A class is taught at one general level.</b> A learner who already reads at the Independent level cannot be added to a class taught at a lower level. A class's general level is the middle level of its learners once at least <span class="num">{{ $classLevelMin }}</span> have a level. Before that it is the usual level for its grade:
        @foreach ($classLevelDefaults as $grade => $band){{ $grade }} {{ ucfirst($band) }}{{ $loop->last ? '.' : ', ' }}@endforeach
      </li>
    </ul>
    <span class="k">Where it comes from</span>
    <p>The curriculum is written grade by grade (MATATAG, DepEd Order No. 10, s. 2024). In a DepEd school the school head assigns who teaches which grade. A DepEd multigrade class (two or more grades under one teacher) is not modelled: a teacher who handles several grades opens one class for each.</p>
  </div>

  <h2 id="syllables">11. The syllable method</h2>
  <p class="sub">Splitting a longer word into its parts and joining it again.</p>
  <div class="rule">
    <span class="k">What it decides</span>
    <p>How longer words are practised, in what order, and how the app tells that a child reads a longer word in pieces.</p>
    <span class="k">The rule</span>
    <p>The syllable method (in Filipino, <i>{{ $syllable['filipinoName'] }}</i>, the syllabic method) is syllable segmenting and blending, taught through repeated drill, inside structured (systematic) phonics. It has four steps, in this order:</p>
    <ol>
      @foreach ($syllable['steps'] as $step)
        <li><b>{{ $step['name'] }}.</b> {{ $step['learner'] }} <i>({{ $step['standard'] }})</i></li>
      @endforeach
    </ol>
    <p>Each part is said <span class="num">{{ $syllable['repeat'] }}</span> times in the drill. Only the last step is scored. The parts of a word are often not words themselves (pic, nic, bas, ket), and a speech recognizer is unreliable on a single piece that is not a word, so a part is never scored: the first three steps are listening, tapping and joining, and the recognizer hears the whole word read aloud in a sentence.</p>
    <p>The word level rises with the child's step on the reading path (Beginning, Developing, Proficient). A child on the Letters step has not met words yet, so the game is not offered to them, and the teacher's notes suggest only clapping the parts of spoken words, which needs no letters.</p>
    <div class="tablewrap"><table>
      <thead><tr><th>Level</th><th>Word shape</th><th>For the steps</th><th>Examples</th></tr></thead>
      <tbody>@foreach ($syllable['levels'] as $l)<tr><td>{{ $l['level'] }}</td><td>{{ $l['pattern'] }}: {{ $l['name'] }}</td><td>{{ $l['steps'] }}</td><td>{{ $l['examples'] }}</td></tr>@endforeach</tbody>
    </table></div>
    <p>Two rules came from correcting the first draft of the method:</p>
    <ul>@foreach ($syllable['corrections'] as $c)<li>{{ $c }}</li>@endforeach</ul>
    <p>The method is used in five places. The activity writer is asked for two-syllable words that split cleanly when a teacher writes a word list for a class, or when several children in the class read longer words in pieces (section 9). The teacher's learner page has a syllable routine for the child's step. The mistake pattern <b>Longer words in pieces</b> (section 5) is reported when a child reads only a part of a longer word, or reads it as two words the recognizer writes separately (basket heard as "bass" and "kit"). The other Practice Games offer the method's words and sentences first to a child whose main pattern it is. And the game <b>Syllable Steps</b> plays the four steps.</p>
    <span class="k">The game, Syllable Steps</span>
    <p>Syllable Steps is a Practice Game for children from the Words step up. For each longer word the child taps a part and hears Tara say it (<span class="num">{{ $syllable['repeat'] }}</span> times for each part), taps once for each part, taps the parts in order so that they glide together, and then reads the word inside a sentence. Only that sentence goes to the speech checker. As in every other game the answer is only "heard" or "again", never "wrong", and nothing the child says is kept. Three words make a round, two or more read right the first time climb a level (the word levels in the table above), and there are up to three rounds. It is free play: it never changes points, streak or level. It leads the Games page for a child whose main kind of mistake is reading longer words in pieces.</p>
    <p>It was designed for children of this age in three ways:</p>
    <ul>
      <li><b>Joining is by tapping, not sliding.</b> In one small study of 90 children aged 2 to 8, among the 7 to 8 year olds 83 percent could tap, 40 percent could swipe and 30 percent could drag and drop. The sample is small, so it is a reason to prefer one finger taps, not proof.</li>
      <li><b>Every instruction comes three ways.</b> Tara says it, one short line of text shows it, and the next thing to tap glows. A speaker button says it again. The same study advises against relying on written prompts for children 8 and under.</li>
      <li><b>The taps are not the learning by themselves.</b> Research on children's apps finds that children learn best when they are thinking, not only tapping. The child splits the word, joins it and reads it; only the reading is scored.</li>
    </ul>
    <span class="k">Where it comes from</span>
    <p>The MATATAG Grade 1 Reading and Literacy guide: {{ $syllable['codes']['segment']['code'] }}, "{{ $syllable['codes']['segment']['text'] }}", and {{ $syllable['codes']['blend']['code'] }}, "{{ $syllable['codes']['blend']['text'] }}" The same guide asks for explicit and systematic instruction and for the Gradual Release of Responsibility (the teacher says it, then it is said together, then the child alone), which is the order of the four steps. The Filipino examples ({{ $syllable['filipino'] }}) are reference only: the speech checker is English only, and Grade 1 reading in the curriculum is in the mother tongue.</p>
    <span class="k">Honest limits</span>
    <ul>
      <li>The word list ({{ $syllable['wordCount'] }} words) and the sentences were written by the team and checked with synthesized voices only. A Grade 1 and a Grade 2 teacher should review them.</li>
      <li>Closed syllables first is common practice in structured literacy programmes. No study was found that proves the order has to be this way, so it is the team's choice.</li>
      <li>A recognizer cannot tell a child who pauses between parts from a child it simply hears as two words, so reading in pieces is only reported when the pieces do not join into the word, and it is a hint for the teacher, not a score.</li>
      <li>Syllable Steps has been played in a browser and with synthesized voices, not by a real child yet. The browser's own voice may say a part such as bas or ket a little oddly. It reached the website first. The phone app has it from the test version 0.5.4, which has not been tried on a real phone yet.</li>
      <li>{{ $syllable['data'] }}</li>
    </ul>
  </div>

  <h2 id="games">12. Practice games and the word bank</h2>
  <p class="sub">Free reading play, and the list of words a child keeps missing.</p>
  <div class="rule">
    <span class="k">What it decides</span>
    <p>Which Practice Game leads the Games page, what level a game starts at, what words or sentences a game offers first, when a spoken answer counts as read, and how a word enters and leaves the child's word bank.</p>
    <span class="k">The rule</span>
    <p>There are four Practice Games: Balloon Pop (read one word to pop its balloon), Word Train (read a whole row to make the train roll; a child still on the Letters step gets rows of letters), Sentence Stars (read a sentence to catch a star) and Syllable Steps (section 11). All four end with the child reading out loud. They are free play: they never change points, streak or level, and nothing the child says is kept.</p>
    <p>A round has <span class="num">{{ $games['perRound'] }}</span> things to read. Each one gets up to <span class="num">{{ $games['tries'] }}</span> tries: read it, then once more, then the game moves on. "Tara is not sure" is shown instead of "wrong". If <span class="num">{{ $games['cleanHits'] }}</span> or more were read right the first time, the child climbs one level. A sitting is up to <span class="num">{{ $games['rounds'] }}</span> rounds, or a single round for a child who starts on the top level (level 3).</p>
    <p>The level a game starts at follows the child's step on the reading path (section 2): level 1 for the first {{ $games['levelTwoFromStep'] }} steps ({{ $games['levelOneSteps'] }}), level 2 from {{ $games['levelTwoSteps'] }}, and level 3 from {{ $games['levelThreeSteps'] }}. Syllable Steps uses the syllable method's own word levels instead.</p>
    <p>A row or a sentence counts as read when at least <span class="num">{{ $games['heardPercent'] }} percent</span> of its words are heard, not counting words the speech checker was unsure of (the Not sure line in section 4). The game is told only whether each word was heard (and so "heard" or "try again" for the whole), never what the child said instead.</p>
    <p>What a game offers comes in this order: first the words the child has been missing in their own readings (the word bank, below), then words or sentences that practise the child's main kind of mistake (section 5), then the level's own list. The Games page marks one game as recommended: Word Train for a child on the Letters step, Syllable Steps when the main kind of mistake is reading longer words in pieces, Balloon Pop when the word bank has words the child still misses, Word Train for the first words step, and otherwise Sentence Stars. Every game stays playable whichever is recommended.</p>
    <span class="k">The word bank</span>
    <p>After each scored reading, every word the child missed goes into their word bank once, as <b>Struggling</b> (a word already in the bank goes back to Struggling and its count starts again). A word in the bank that the child now reads correctly moves to <b>Improving</b> after the first correct reading and to <b>Mastered</b> after <span class="num">{{ $games['masteredAfter'] }}</span> correct readings, so a word is practised soon after a miss and then less and less (spaced review). One reading adds at most <span class="num">{{ $games['maxNewWords'] }}</span> new words, so one hard passage cannot flood the bank. A word the checker was only "not sure" about is never added, and a word that is not in the bank is not added just for being right.</p>
    <span class="k">Where it comes from</span>
    <p>Reading single words, rows and sentences aloud is word reading and sounding out words in the MATATAG curriculum: when a game asks the speech checker, it sends the code of the child's own grade (RL1PWS-I-5 "Sound out words accurately" for Grade 1, EN2PWS-I-3 for Grade 2, EN3PWS-I-2 for Grade 3). The word bank's "come back to a missed word soon, then less and less often" is the team's own design of spaced review; no study was checked for these exact numbers. The numbers in this section (three things to a round, two tries, two to climb, the "heard" share) were chosen with synthesized voices, not with children. They are in <code>config/practice_games.php</code> and <code>app/Services/WordBank.php</code>.</p>
    <span class="k">Honest limits</span>
    <ul>
      <li>No real child has played the games yet. The rounds, tries and the "heard" share are the first numbers to check once children do (section 14).</li>
      <li>A row of single short words is the hardest thing for a speech recognizer, so a row read right can be turned away. The second try and the kind line "Good try! On we go." soften that.</li>
      <li>The phone app has its own copy of the round numbers; a test fails if they ever differ from <code>config/practice_games.php</code>.</li>
    </ul>
  </div>

  <h2 id="never">13. What it does not do</h2>
  <ul>
    <li>It does not decide for a teacher or a parent. Alerts and suggestions come with their reasons, and a person acts.</li>
    <li>It does not hide an activity from a child, and it does not show a child a score on the first reading check.</li>
    <li>It does not keep a child's recording after it has been scored (see the <a href="{{ route('privacy') }}">Privacy page</a>).</li>
    <li>It does not learn by itself. No number on this page changes unless a person changes it.</li>
  </ul>

  <h2 id="checking">14. Checking the numbers</h2>
  <p>Nobody has tested the app with real children yet, and a model cannot honestly be trained without that data. Once real children use it, these are the first things the team will check, and each number lives in one settings file, so a change after the evidence needs no new code:</p>
  <ol>
    <li>Whether children read activities written for their class better than general ones (the "Did it work?" card in section 9).</li>
    <li>Whether the Not sure line and the "heard" share used in the games (section 12) fit children's voices (this needs recordings of real children and their parents' consent).</li>
    <li>Whether moving up after 3 strong readings and down after 2 weak ones moves children too slowly or too fast, by counting how many readings each move takes.</li>
    <li>Whether the word limits for each step match how long children really take to read.</li>
  </ol>

  <h2 id="services">15. The outside services</h2>
  <ul>
    <li><b>Reading scoring service:</b> Vosk, an open speech recognition engine, run by the team. It turns a recording into words and returns the word by word result. Section 4 describes what TaraBasa does with it.</li>
    <li><b>Activity writer:</b> a service the team runs that asks an AI language model to write the texts. It receives a grade, a skill, a topic and a short note. When a teacher picks a class, the note holds a summary of the class (section 9), and never a name, a learner code or any one child's details.</li>
    <li><b>Activity recommender:</b> a formula based service the team runs. It keeps a score for each skill with a smoothing formula and names the next skill to practise. It uses no machine learning.</li>
  </ul>
  <p>The <a href="{{ route('privacy') }}">Privacy page</a> says what each one receives.</p>

  <h2 id="sources">16. Sources</h2>
  <ul>
    <li>DepEd Order No. 14, s. 2018: Philippine Informal Reading Inventory (Phil-IRI), the reading level names and accuracy bands.</li>
    <li>DepEd Order No. 10, s. 2024: the MATATAG Curriculum, written grade by grade, with the competency codes used in the checks, activities and patterns.</li>
    <li>DepEd Memorandum No. 173, s. 2019: Hamon, Bawat Bata Bumabasa (every learner a reader at their grade level), and its six elements of reading.</li>
    <li>National Reading Panel (2000): Teaching Children to Read (NICHD). Its findings pages say that systematic phonics instruction helps children from kindergarten to Grade 6, and that guided repeated oral reading improves word recognition, fluency and comprehension. The full report was not read.</li>
    <li>Ehri (2005): the phases children go through in learning to read words, from knowing letter sounds, to sounding a word out, to reading it by sight. It is behind the idea that letter knowledge and sounding out come before fluent reading. It does not set the order of letters, words, sentences and stories, which is the team's own use of the curriculum.</li>
    <li>Pearson and Gallagher (1983): gradual release of responsibility, behind the teaching notes shown to teachers.</li>
    <li>Republic Act No. 10173, the Data Privacy Act of 2012, behind the privacy and deletion rules.</li>
  </ul>
  <h3>For the syllable method (section 11)</h3>
  <ul>
    @foreach ($syllable['sources'] as $src)<li>{{ $src['text'] }} <i>({{ $src['read'] ? 'wording read in full by the team' : 'found by search, not read in full: check the original before quoting' }})</i></li>@endforeach
  </ul>

  <a class="btn" href="{{ url('/') }}">Back to the home page</a>
</main>
</body>
</html>
