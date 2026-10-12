import { useFonts } from 'expo-font';
import { Baloo2_700Bold, Baloo2_800ExtraBold } from '@expo-google-fonts/baloo-2';
import { BarlowSemiCondensed_500Medium, BarlowSemiCondensed_600SemiBold, BarlowSemiCondensed_700Bold } from '@expo-google-fonts/barlow-semi-condensed';
import { Lexend_400Regular, Lexend_500Medium } from '@expo-google-fonts/lexend';

/** The three font families the app uses, bundled inside the app so text shows even with no internet. */
export function useAppFonts() {
  const [ready] = useFonts({
    Baloo2_700Bold,
    Baloo2_800ExtraBold,
    BarlowSemiCondensed_500Medium,
    BarlowSemiCondensed_600SemiBold,
    BarlowSemiCondensed_700Bold,
    Lexend_400Regular,
    Lexend_500Medium,
  });
  return ready;
}
