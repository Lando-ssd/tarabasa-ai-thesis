{{--
  Shared "Tara the owl" mascot SVG — used by the Games hub, Word Builder,
  and Letter Match, so the same hand-drawn character appears consistently
  everywhere instead of the plain 🦉 emoji this app uses elsewhere. Sized
  to fill its parent .mascot chip (the chip itself keeps supplying the
  existing gradient background/shadow — this partial only replaces the
  glyph inside it), so no caller needs to change its own container CSS.

  Two visual states, toggled by adding/removing "is-celebrating" on the
  wrapping element with that id (id="{{ $id }}"):
  - idle (default): plain round eyes, calm.
  - celebrating: happy scrunched-arc eyes + a burst of small sparkles —
    meant for "word/round complete."
  A third behavior, "encourage" (a gentle reassuring tilt on a wrong tap),
  is a CSS animation triggered the same way via "is-encouraging" — kept
  separate from "celebrating" since a wrong tap isn't a sad moment, just
  a brief "try again" nudge, not a different facial expression.

  $id (string, required): must be unique per instance on the page (a page
  can include this partial more than once, e.g. Games hub shows it twice).
--}}
<style>
  .owl-mascot{ width:100%; height:100%; display:block; }
  .owl-mascot .owl-eye-normal{ display:block; }
  .owl-mascot .owl-eye-happy{ display:none; }
  .owl-mascot .owl-sparkles{ opacity:0; }
  .owl-mascot.is-celebrating .owl-eye-normal{ display:none; }
  .owl-mascot.is-celebrating .owl-eye-happy{ display:block; }
  .owl-mascot.is-celebrating .owl-sparkles .spark{ animation:owlSparkPop .7s ease-out forwards; }
  .owl-mascot.is-celebrating .owl-sparkles .spark:nth-child(2){ animation-delay:.08s; }
  .owl-mascot.is-celebrating .owl-sparkles .spark:nth-child(3){ animation-delay:.16s; }
  .owl-mascot.is-celebrating .owl-sparkles .spark:nth-child(4){ animation-delay:.05s; }
  @keyframes owlSparkPop{
    0%{ opacity:0; transform:scale(0.2); }
    45%{ opacity:1; transform:scale(1.15); }
    100%{ opacity:0; transform:scale(0.85) translateY(-4px); }
  }
  .owl-mascot.is-encouraging .owl-body-group{ animation:owlTilt .6s ease-in-out 2; transform-origin:50px 62px; }
  @keyframes owlTilt{
    0%,100%{ transform:rotate(0deg); }
    30%{ transform:rotate(-6deg); }
    70%{ transform:rotate(5deg); }
  }
  @media (prefers-reduced-motion: reduce){
    .owl-mascot .owl-sparkles .spark, .owl-mascot.is-encouraging .owl-body-group{ animation:none !important; }
  }
</style>
<svg id="{{ $id }}" class="owl-mascot" viewBox="0 0 100 100" xmlns="http://www.w3.org/2000/svg">
  <g class="owl-body-group">
    <!-- ear tufts -->
    <path d="M23 32 L30 13 L39 29 Z" fill="#fff8ef"/>
    <path d="M77 32 L70 13 L61 29 Z" fill="#fff8ef"/>
    <!-- wing hints -->
    <ellipse cx="17" cy="63" rx="7" ry="15" fill="#fff8ef" transform="rotate(-12 17 63)"/>
    <ellipse cx="83" cy="63" rx="7" ry="15" fill="#fff8ef" transform="rotate(12 83 63)"/>
    <!-- body -->
    <ellipse cx="50" cy="58" rx="34" ry="32" fill="#fff8ef"/>
    <!-- belly patch -->
    <ellipse cx="50" cy="67" rx="18" ry="15" fill="#ffe4c2"/>
    <!-- eyes: normal (idle) -->
    <g class="owl-eye-normal">
      <circle cx="36" cy="53" r="13" fill="#ffffff" stroke="#131f2b" stroke-width="2"/>
      <circle cx="64" cy="53" r="13" fill="#ffffff" stroke="#131f2b" stroke-width="2"/>
      <circle cx="37" cy="54" r="6.5" fill="#131f2b"/>
      <circle cx="65" cy="54" r="6.5" fill="#131f2b"/>
      <circle cx="34.5" cy="51.5" r="2" fill="#ffffff"/>
      <circle cx="62.5" cy="51.5" r="2" fill="#ffffff"/>
    </g>
    <!-- eyes: happy (celebrating) -->
    <g class="owl-eye-happy">
      <circle cx="36" cy="53" r="13" fill="#ffffff" stroke="#131f2b" stroke-width="2"/>
      <circle cx="64" cy="53" r="13" fill="#ffffff" stroke="#131f2b" stroke-width="2"/>
      <path d="M29 53 Q36 45 43 53" fill="none" stroke="#131f2b" stroke-width="4" stroke-linecap="round"/>
      <path d="M57 53 Q64 45 71 53" fill="none" stroke="#131f2b" stroke-width="4" stroke-linecap="round"/>
    </g>
    <!-- beak -->
    <path d="M44 63 L56 63 L50 73 Z" fill="#dd7014"/>
  </g>
  <!-- sparkles, celebrating only -->
  <g class="owl-sparkles">
    <path class="spark" d="M14 20 L16.5 26 L22.5 28.5 L16.5 31 L14 37 L11.5 31 L5.5 28.5 L11.5 26 Z" fill="#ffcf6e"/>
    <path class="spark" d="M86 16 L88 21 L93 23 L88 25 L86 30 L84 25 L79 23 L84 21 Z" fill="#2bb89c"/>
    <path class="spark" d="M90 62 L91.5 65.5 L95 67 L91.5 68.5 L90 72 L88.5 68.5 L85 67 L88.5 65.5 Z" fill="#ef8d2a"/>
    <path class="spark" d="M8 68 L9.5 71.5 L13 73 L9.5 74.5 L8 78 L6.5 74.5 L3 73 L6.5 71.5 Z" fill="#1c7ed6"/>
  </g>
</svg>
