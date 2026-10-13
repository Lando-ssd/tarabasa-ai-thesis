// The phone app's look, taken from the website's Learner side (white page, blue panel, clay orange buttons) and sized
// for a phone. One colour set for every child: the website lets a parent pick blue or pink, the phone app does not.

import { s } from './scale';

export const colors = {
  page: '#ffffff',
  ink: '#17324d',
  slate: '#56697a',
  line: '#d7e3ef',
  orange: '#dd7014',
  orangeDark: '#b45a0c',
  orangeSoft: '#fff7ee',
  blue: '#2f7bd6',
  blueDark: '#1f5ba5',
  sky: '#eaf4ff',
  sky2: '#d3e8fb',
  mint: '#e4f5ec',
  gold: '#fff1c9',
  goldDeep: '#e99a12',
  ok: '#2e8b57',
  bad: '#b3261e',
  badSoft: '#fdecec',
  warn: '#a96a00',
  purple: '#5b2d9b',
  purpleSoft: '#eee3fb',
  unsure: '#6f7f8f',
  unsureSoft: '#eef2f6',
  white: '#ffffff',
};

// Font names are the ones registered in App.tsx (bundled in the app, no internet needed to show text).
export const fonts = {
  display: 'Baloo2_800ExtraBold', // big friendly headings
  displayMedium: 'Baloo2_700Bold',
  ui: 'BarlowSemiCondensed_600SemiBold', // buttons and labels
  uiBold: 'BarlowSemiCondensed_700Bold',
  uiRegular: 'BarlowSemiCondensed_500Medium',
  read: 'Lexend_500Medium', // the words a child reads aloud
  readRegular: 'Lexend_400Regular',
};

// Sizes follow Android's guidance for touch (48dp minimum) and the WCAG 2.2 target size rule, with room to spare
// because the users are six to nine years old.
export const size = {
  touch: s(40), // the smallest thing that can be tapped (about 52dp on a 390 wide phone, above Android's 48dp)
  button: s(52), // the main buttons
  field: s(50),
  tabBar: s(52),
  gutter: s(16), // the space at the left and right of every screen
  maxWidth: 560, // on a tablet the content stays a readable column
  radius: s(14),
};

// How much larger the reading text is at each of the five text size steps (the website's A minus and A plus).
export const readingSteps = [
  { label: 'Small', scale: 0.82 },
  { label: 'Medium small', scale: 0.91 },
  { label: 'Medium', scale: 1 },
  { label: 'Large', scale: 1.15 },
  { label: 'Extra large', scale: 1.32 },
];
export const READING_BASE = s(22); // the reading text size at "Medium", in sp (about 28 on a 390 wide phone)
