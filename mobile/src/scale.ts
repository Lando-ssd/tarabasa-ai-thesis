import { Dimensions } from 'react-native';

// One scale for the whole app. The approved phone preview was drawn on a 300 wide phone (a 282 by 592 screen), so its buttons,
// headings and spacing look bold and roomy there. A real phone is bigger, so the same plain numbers look small and sparse.
// Every size in the app goes through s(), which grows with the screen the way the preview does: by the screen's width (1.2
// times on a 360 wide phone, 1.3 times on a 390 wide one) but never by more than the screen's height allows, so a short phone
// (360 by 640) keeps the preview's proportions instead of pushing its content off the bottom. Never more than 1.3 (a tablet
// keeps a readable column instead).
const DESIGN_WIDTH = 300;
const DESIGN_HEIGHT = 592; // the preview's whole screen, status bar included
const MAX_SCALE = 1.3;

const window = Dimensions.get('window');
// The app is locked to portrait, but read the short side anyway so a landscape start can never inflate everything.
const width = Math.min(window.width, window.height);
const height = Math.max(window.width, window.height);

/** How much bigger than the 300 by 592 design this phone draws things. */
export const K = Math.max(1, Math.min(MAX_SCALE, width / DESIGN_WIDTH, height / DESIGN_HEIGHT));

/** A design size turned into a real size for this phone, rounded to a quarter so lines stay crisp. */
export const s = (n: number): number => Math.round(n * K * 4) / 4;

/** True on a short phone (such as 360 by 640), where a screen drops its decorations first so the main button stays in view. */
export const SHORT_HEIGHT = height < 720;

/**
 * True on most phones (under 950 tall, such as 390 by 844, 360 by 800 or 430 by 932). The biggest decorations (Tara's head, the microphone)
 * step down one size so a story, its text and the pinned microphone all fit one screen. Only the tall phones and tablets keep them big.
 */
export const COMPACT_HEIGHT = height < 950;
