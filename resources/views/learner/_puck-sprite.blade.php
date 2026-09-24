{{--
  Shared 3D "puck" sprite for the road map: a top face on a darker extruded
  side, a bevel highlight, an embossed star, and a gray halo ring. Defined once
  and reused by every step of every road (see _road.blade.php).
--}}
<svg width="0" height="0" style="position:absolute" aria-hidden="true" focusable="false">
  <defs>
    <path id="pkStar" d="M0,-12 L2.9,-3.8 L11.4,-3.8 L4.6,1.4 L7.1,9.7 L0,4.6 L-7.1,9.7 L-4.6,1.4 L-11.4,-3.8 L-2.9,-3.8 Z"/>

    <g id="pkTrophy">
      <path d="M-13,-15 H13 V-5 C13,6 7,11 0,11 C-7,11 -13,6 -13,-5 Z"/>
      <path d="M-13,-11 H-19 C-19,-2 -16,3 -11,3 M13,-11 H19 C19,-2 16,3 11,3" fill="none" stroke-width="3" stroke-linecap="round"/>
      <rect x="-2.5" y="11" width="5" height="6"/>
      <rect x="-9" y="17" width="18" height="4.5" rx="1.5"/>
    </g>

    <radialGradient id="pkTopOrange" cx="34%" cy="26%" r="88%">
      <stop offset="0%" stop-color="#ffc98a"/><stop offset="45%" stop-color="#f7a03d"/><stop offset="100%" stop-color="#e5801a"/>
    </radialGradient>
    <linearGradient id="pkSideOrange" x1="0" y1="0" x2="0" y2="1">
      <stop offset="0%" stop-color="#d4690d"/><stop offset="100%" stop-color="#ad500a"/>
    </linearGradient>
    <radialGradient id="pkTopGray" cx="34%" cy="26%" r="88%">
      <stop offset="0%" stop-color="#fbfcfd"/><stop offset="100%" stop-color="#dde3e8"/>
    </radialGradient>
    <linearGradient id="pkSideGray" x1="0" y1="0" x2="0" y2="1">
      <stop offset="0%" stop-color="#c2cbd3"/><stop offset="100%" stop-color="#aab5bf"/>
    </linearGradient>

    <g id="haloGray">
      <circle cy="5" r="48" fill="#e8ebee" stroke="#dde1e6" stroke-width="2"/>
    </g>
    <g id="haloCurrent">
      <circle cy="6" r="57" fill="#fff3e2" stroke="#ef8d2a" stroke-width="4"/>
    </g>

    <g id="puckDone">
      <rect x="-40" y="0" width="80" height="11" fill="url(#pkSideOrange)"/>
      <circle cy="11" r="40" fill="url(#pkSideOrange)"/>
      <circle r="40" fill="url(#pkTopOrange)"/>
      <path d="M-30,-17 A34.5,34.5 0 0 1 15.5,-31" stroke="rgba(255,255,255,.6)" stroke-width="4.5" stroke-linecap="round" fill="none"/>
      <use href="#pkStar" fill="#b0530a" opacity=".4" transform="translate(0,3.4) scale(1.7)"/>
      <use href="#pkStar" fill="#ffffff" transform="scale(1.7)"/>
    </g>

    <g id="puckLocked">
      <rect x="-40" y="0" width="80" height="11" fill="url(#pkSideGray)"/>
      <circle cy="11" r="40" fill="url(#pkSideGray)"/>
      <circle r="40" fill="url(#pkTopGray)"/>
      <use href="#pkStar" fill="#ffffff" opacity=".95" transform="translate(0,3) scale(1.7)"/>
      <use href="#pkStar" fill="#b9c3cc" transform="scale(1.7)"/>
    </g>

    <g id="puckTrophy">
      <rect x="-40" y="0" width="80" height="11" fill="url(#pkSideGray)"/>
      <circle cy="11" r="40" fill="url(#pkSideGray)"/>
      <circle r="40" fill="url(#pkTopGray)"/>
      <use href="#pkTrophy" fill="#ffffff" stroke="#ffffff" opacity=".95" transform="translate(0,3) scale(1.5)"/>
      <use href="#pkTrophy" fill="#b9c3cc" stroke="#b9c3cc" transform="scale(1.5)"/>
    </g>

    <g id="puckCurrent">
      <rect x="-46" y="0" width="92" height="12" fill="url(#pkSideOrange)"/>
      <circle cy="12" r="46" fill="url(#pkSideOrange)"/>
      <circle r="46" fill="url(#pkTopOrange)"/>
      <path d="M-35,-19 A39.5,39.5 0 0 1 18,-35.5" stroke="rgba(255,255,255,.6)" stroke-width="5" stroke-linecap="round" fill="none"/>
      <use href="#pkStar" fill="#b0530a" opacity=".4" transform="translate(0,3.6) scale(1.95)"/>
      <use href="#pkStar" fill="#ffffff" transform="scale(1.95)"/>
    </g>
  </defs>
</svg>
