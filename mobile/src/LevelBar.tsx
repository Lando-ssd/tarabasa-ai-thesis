import { StyleSheet, View } from 'react-native';
import { colors } from './theme';

/**
 * A live voice meter: a row of small bars that light up as the microphone hears more. It lets a child SEE that the
 * phone is listening (the app says what it is doing). It is only a picture, so a screen reader skips it and the words
 * on the screen say what happened.
 */
export function LevelBar({ share, bars = 14 }: { share: number; bars?: number }) {
  const lit = Math.round(share * bars);
  return (
    <View style={styles.row} accessible={false} importantForAccessibility="no-hide-descendants">
      {Array.from({ length: bars }, (_, i) => (
        <View
          key={i}
          style={[
            styles.bar,
            { height: 10 + (i / (bars - 1)) * 22 },
            i < lit && { backgroundColor: i > bars * 0.75 ? colors.orange : colors.ok },
          ]}
        />
      ))}
    </View>
  );
}

const styles = StyleSheet.create({
  row: { flexDirection: 'row', alignItems: 'flex-end', justifyContent: 'center', gap: 4, height: 34 },
  bar: { width: 8, borderRadius: 4, backgroundColor: colors.sky2 },
});
