<?php

namespace Tests\Feature;

use App\Services\ClassActivityOutcomes;
use App\Services\TeacherAlerts;
use App\Support\ClassLevel;
use App\Support\ErrorPatterns;
use App\Support\GenerationBasis;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The public "How TaraBasa decides" page. Its job is to be TRUE: every number on it is read from the constant or the
 * config file the rule runs on, so these tests change a setting and look for the page to follow it. A page that
 * quoted a number the app no longer uses would mislead the adviser and the panel.
 */
class HowItWorksTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_is_public_and_says_plainly_it_is_not_a_trained_model(): void
    {
        $html = $this->get('/how-it-works')->assertOk()->getContent();

        $this->assertStringContainsString('How TaraBasa decides', $html);
        $this->assertStringContainsString('written rules, not a trained model', $html);
        $this->assertStringContainsString('It does not learn by itself', $html);

        // No sign in is needed and nothing private is on it: no name, email or code of anyone.
        $this->assertStringNotContainsString('@example.com', $html);
    }

    public function test_the_numbers_come_from_the_rules_themselves(): void
    {
        $html = $this->get('/how-it-works')->assertOk()->getContent();

        // Moving up or down (config/progression.php).
        $p = config('progression');
        $this->assertStringContainsString('at least <span class="num">'.$p['up_readings'].'</span> readings of <span class="num">'.$p['up_accuracy'].' percent or more</span>', $html);
        $this->assertStringContainsString('<span class="num">'.$p['down_readings'].'</span> readings in a row under <span class="num">'.$p['down_accuracy'].' percent</span>', $html);

        // Alerts, patterns, class level, writing for a class and the "did it work?" check.
        $this->assertStringContainsString('under <span class="num">'.TeacherAlerts::SUPPORT_BELOW.' percent</span>', $html);
        $this->assertStringContainsString('<span class="num">'.TeacherAlerts::QUIET_DAYS.' days</span>', $html);
        $this->assertStringContainsString('at least <span class="num">'.ErrorPatterns::MIN_READINGS.'</span> readings and <span class="num">'.ErrorPatterns::MIN_MISSES.'</span> missed words', $html);
        $this->assertStringContainsString('at least <span class="num">'.ClassLevel::MIN_LEARNERS.'</span> have a level', $html);
        $this->assertStringContainsString('at least <span class="num">'.GenerationBasis::MIN_SHARED.'</span> children show it', $html);
        $this->assertStringContainsString('at least <span class="num">'.ClassActivityOutcomes::MIN_PAIRED.'</span> children read both kinds', $html);

        // Every word limit in the table is the live one.
        foreach (config('activity_fit.limits') as $limits) {
            $this->assertStringContainsString("<td>{$limits[0]} words</td><td>{$limits[1]} words</td>", $html);
        }
        $this->assertStringContainsString('confidence under <span class="num">'.config('reading.unsure_confidence').'</span>', $html);
    }

    public function test_changing_a_setting_changes_the_page(): void
    {
        config(['progression.up_readings' => 7, 'reading.unsure_confidence' => 0.33]);

        $html = $this->get('/how-it-works')->assertOk()->getContent();

        $this->assertStringContainsString('at least <span class="num">7</span> readings', $html);
        $this->assertStringContainsString('confidence under <span class="num">0.33</span>', $html);
    }

    public function test_every_mistake_pattern_is_listed_with_its_code(): void
    {
        $html = $this->get('/how-it-works')->getContent();

        foreach (ErrorPatterns::CATEGORIES as $c) {
            $this->assertStringContainsString($c['label'], $html);
            $this->assertStringContainsString($c['code'], $html);
        }
    }

    public function test_the_syllable_method_section_follows_its_config_and_is_honest_about_its_sources(): void
    {
        config(['syllable_method.repeat' => 7]);

        $html = $this->get('/how-it-works')->assertOk()->getContent();

        $this->assertStringContainsString('id="syllables"', $html);
        $this->assertStringContainsString('href="#syllables"', $html);
        $this->assertStringContainsString('<span class="num">7</span> times in the drill', $html);

        foreach (config('syllable_method.codes') as $c) {
            $this->assertStringContainsString($c['code'], $html);
        }
        foreach (config('syllable_method.levels') as $level) {
            $this->assertStringContainsString($level['pattern'], $html);
        }
        foreach (config('syllable_method.steps') as $step) {
            $this->assertStringContainsString($step['name'], $html);
        }
        foreach (config('syllable_method.corrections') as $correction) {
            $this->assertStringContainsString(e($correction), $html);
        }

        // Only the last step is scored, and the page says why.
        $this->assertStringContainsString('Only the last step is scored', $html);
        // The sources say which were read and which were only found by search, and no dataset is claimed.
        $this->assertStringContainsString('wording read in full by the team', $html);
        $this->assertStringContainsString('found by search, not read in full', $html);
        $this->assertStringContainsString('No recording dataset is used by the app', $html);
        // The pattern it adds is counted in the list of kinds.
        $this->assertStringContainsString('one of '.count(ErrorPatterns::CATEGORIES).' kinds', $html);
    }

    public function test_the_page_describes_the_syllable_steps_game_with_its_basis_and_its_limits(): void
    {
        config(['syllable_method.repeat' => 6]);

        $html = $this->get('/how-it-works')->assertOk()->getContent();

        $this->assertStringContainsString('The game, Syllable Steps', $html);
        // The drill count in the game is the method's own number.
        $this->assertStringContainsString('(<span class="num">6</span> times for each part)', $html);
        // What the game scores, and what it never does.
        $this->assertStringContainsString('Only that sentence goes to the speech checker', $html);
        $this->assertStringContainsString('never "wrong"', $html);
        $this->assertStringContainsString('never changes points, streak or level', $html);
        // The design choices for children, with the evidence said to be small and the sources marked as found by search.
        $this->assertStringContainsString('Joining is by tapping, not sliding', $html);
        $this->assertStringContainsString('Every instruction comes three ways', $html);
        $this->assertStringContainsString('The sample is small', $html);
        $this->assertStringContainsString('PMC7303424', $html);
        $this->assertStringContainsString('Hirsh-Pasek and others (2015)', $html);
        // Honest about how far it has been tried, and where it is.
        $this->assertStringContainsString('not by a real child yet', $html);
        $this->assertStringContainsString('has not been tried on a real phone yet', $html);
        // The Ehri source says what it is about and what it is not.
        $this->assertStringContainsString('It does not set the order of letters, words, sentences and stories', $html);
    }

    public function test_the_copy_follows_the_app_rules_no_emoji_and_no_dashes(): void
    {
        $html = $this->get('/how-it-works')->getContent();
        $text = strip_tags(preg_replace('/<(style|script)\b.*?<\/\1>/is', '', $html));

        $this->assertStringNotContainsString("\u{2014}", $text, 'no em dash in the copy');
        $this->assertStringNotContainsString("\u{2013}", $text, 'no en dash in the copy');
        $this->assertSame(0, preg_match('/[\x{1F300}-\x{1FAFF}\x{2600}-\x{27BF}]/u', $text), 'no emoji in the copy');
    }

    public function test_it_is_linked_from_the_home_page_and_the_teacher_alerts(): void
    {
        $this->get('/')->assertOk()->assertSee('How TaraBasa decides')->assertSee(route('how-it-works'));
    }

    public function test_the_privacy_page_tells_the_truth_about_the_class_summary_sent_to_the_activity_writer(): void
    {
        $html = $this->get('/privacy')->assertOk()->getContent();

        $this->assertStringContainsString('"For a class"', html_entity_decode($html));
        $this->assertStringContainsString("never receives a name, a learner code or any one child's details", html_entity_decode($html));
    }
}
