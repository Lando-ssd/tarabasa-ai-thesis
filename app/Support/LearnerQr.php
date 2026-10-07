<?php

namespace App\Support;

use BaconQrCode\Common\ErrorCorrectionLevel;
use BaconQrCode\Encoder\Encoder;
use BaconQrCode\Renderer\Image\SvgImageBackEnd;
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Writer;

/**
 * The QR code of a learner. It holds ONLY the Learner Code (TB26-48293), exactly what a person would type, and
 * nothing else: never the PIN, never a name. It is made here on the server as an SVG (no outside QR service ever
 * sees a child's code), black on white with the full quiet zone so a phone or a classroom tablet reads it
 * reliably even when printed small or a little smudged (error correction level Q).
 *
 * The code on its own does not sign anyone in (the child still needs the PIN) and a Teacher who scans it joins the
 * child to their class exactly as if they had typed it, with the same limits and the same notice to the parents.
 */
class LearnerQr
{
    /** What the QR holds: the code, in the form it is stored. */
    public static function payload(string $code): string
    {
        return LearnerCode::normalize($code);
    }

    /**
     * An inline SVG, $size pixels square (it scales: the viewBox is kept). Safe to print into a page with {!! !!}:
     * it is made by the library from the code alone, and the code is checked to be a code first.
     */
    public static function svg(string $code, int $size = 240): string
    {
        $payload = self::payload($code);

        if (! LearnerCode::looksLikeCode($payload)) {
            throw new \InvalidArgumentException('A QR code can only be made for a Learner Code.');
        }

        $writer = new Writer(new ImageRenderer(new RendererStyle($size, 4), new SvgImageBackEnd()));
        $svg = $writer->writeString($payload, Encoder::DEFAULT_BYTE_MODE_ECODING, ErrorCorrectionLevel::Q());

        // Inline use: no XML header, a name for screen readers, and it scales to its box.
        $svg = preg_replace('/^<\?xml[^>]*\?>\s*/', '', $svg);
        $label = 'QR code for learner code '.$payload;

        return preg_replace('/<svg /', '<svg role="img" aria-label="'.e($label).'" focusable="false" preserveAspectRatio="xMidYMid meet" ', $svg, 1);
    }
}
