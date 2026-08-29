<?php

/**
 * DiagnosticStaircase — implements the algorithm from
 * TaraBasaAI_AdaptiveDiagnostic_Correction.txt Part 1, exactly.
 * Shared between GET /api/session/next-diagnostic-passage (which tier to
 * show next) and POST /api/session/submit (whether THIS submission is the
 * one that finalizes the Learner's real masteryLevel).
 */
class DiagnosticStaircase
{
    const TIER_TO_LEVEL = ['Easy' => 'Beginning', 'Medium' => 'Developing', 'Hard' => 'Proficient'];
    const LEVEL_TO_TIER = ['Beginning' => 'Easy', 'Developing' => 'Medium', 'Proficient' => 'Hard'];

    /**
     * PASSAGE 1 starting tier: neutral "Medium" default, UNLESS the Parent's
     * preliminary estimate was clearly Beginning or clearly Proficient, in
     * which case start one tier toward that guess.
     */
    public static function startingTier(?string $preliminaryMasteryLevel): string
    {
        return match ($preliminaryMasteryLevel) {
            'Beginning' => 'Easy',
            'Proficient' => 'Hard',
            default => 'Medium', // Developing, or no estimate at all
        };
    }

    /**
     * Given the tier and accuracy of the passage just read, and how many
     * diagnostic passages have been read so far (including this one),
     * decides whether the staircase stops here (and at what final tier)
     * or continues to a new tier.
     *
     * @return array{stop: bool, next_tier: ?string, final_tier: ?string}
     */
    public static function nextStep(string $lastTier, float $lastAccuracy, int $passagesSoFar): array
    {
        // Hard cap at 3 passages regardless of outcome.
        if ($passagesSoFar >= 3) {
            return ['stop' => true, 'next_tier' => null, 'final_tier' => $lastTier];
        }

        if ($lastAccuracy >= 90) {
            if ($lastTier === 'Hard') {
                // Already at Hard and still >=90% — Proficient confirmed, no higher tier to test.
                return ['stop' => true, 'next_tier' => null, 'final_tier' => 'Hard'];
            }
            $next = $lastTier === 'Easy' ? 'Medium' : 'Hard';
            return ['stop' => false, 'next_tier' => $next, 'final_tier' => null];
        }

        if ($lastAccuracy < 70) {
            if ($lastTier === 'Easy') {
                // Already at Easy and still <70% — Beginning confirmed, no lower tier to test.
                return ['stop' => true, 'next_tier' => null, 'final_tier' => 'Easy'];
            }
            $next = $lastTier === 'Hard' ? 'Medium' : 'Easy';
            return ['stop' => false, 'next_tier' => $next, 'final_tier' => null];
        }

        // 70-89%: this tier is likely their real level. Stop here (2 passages
        // total is enough) — the simpler of the two doc-allowed options.
        return ['stop' => true, 'next_tier' => null, 'final_tier' => $lastTier];
    }

    public static function tierToLevel(string $tier): string
    {
        return self::TIER_TO_LEVEL[$tier];
    }

    public static function levelToTier(string $level): string
    {
        return self::LEVEL_TO_TIER[$level];
    }
}
