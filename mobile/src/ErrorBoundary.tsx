import { Component, type ReactNode } from 'react';
import { Pressable, StyleSheet, Text, View } from 'react-native';
import { colors, fonts, space } from './theme';
import { s } from './scale';

/**
 * The last safety net. If any screen ever crashes, a child sees a calm page and one big button instead of a white screen
 * or the app closing. Pressing the button remounts the whole app, which signs in again from the saved login. It is drawn
 * with plain React Native parts on purpose, so it still works if the shared screen pieces are the thing that broke.
 * It shows no technical message to a child.
 */
export class ErrorBoundary extends Component<{ children: ReactNode; onRestart?: () => void }, { failed: boolean; attempt: number }> {
  state = { failed: false, attempt: 0 };

  static getDerivedStateFromError() {
    return { failed: true };
  }

  componentDidCatch(error: unknown) {
    // Kept in the developer log only. Nothing about the child is sent anywhere.
    if (__DEV__) console.warn('TaraBasa screen crashed:', error);
  }

  private restart = () => {
    this.props.onRestart?.();
    this.setState((s) => ({ failed: false, attempt: s.attempt + 1 }));
  };

  render() {
    if (this.state.failed) {
      return (
        <View style={styles.page}>
          <Text maxFontSizeMultiplier={1.2} style={styles.title}>
            {'Oops!\nTara got mixed up.'}
          </Text>
          <Text maxFontSizeMultiplier={1.2} style={styles.message}>
            Nothing is lost. Tap the button and we will start again.
          </Text>
          <Pressable accessibilityRole="button" accessibilityLabel="Start again" onPress={this.restart} style={({ pressed }) => [styles.button, pressed && { opacity: 0.85 }]}>
            <Text maxFontSizeMultiplier={1.2} style={styles.buttonText}>
              Start Again
            </Text>
          </Pressable>
        </View>
      );
    }
    // A new key after a crash gives the whole app a clean start.
    return <View key={this.state.attempt} style={{ flex: 1 }}>{this.props.children}</View>;
  }
}

const styles = StyleSheet.create({
  page: { flex: 1, alignItems: 'center', justifyContent: 'center', gap: space[16], padding: space[28], backgroundColor: colors.page },
  title: { fontFamily: fonts.display, fontSize: s(30), lineHeight: s(36), color: colors.orange, textAlign: 'center' },
  message: { fontFamily: fonts.ui, fontSize: s(19), lineHeight: s(27), color: colors.slate, textAlign: 'center', maxWidth: 360 },
  button: { minHeight: s(56), minWidth: s(220), borderRadius: s(18), alignItems: 'center', justifyContent: 'center', backgroundColor: colors.orange, borderBottomWidth: s(6), borderBottomColor: colors.orangeDark, paddingHorizontal: space[28], marginTop: space[8] },
  buttonText: { fontFamily: fonts.uiBold, fontSize: s(21), color: colors.white },
});
