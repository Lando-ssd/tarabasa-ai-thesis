import { useEffect, useRef, useState } from 'react';
import { Linking, Modal, StyleSheet, Text, View } from 'react-native';
import { CameraView, useCameraPermissions } from 'expo-camera';
import { useSafeAreaInsets } from 'react-native-safe-area-context';
import { Btn, H2, Icon, IconBtn, P } from './ui';
import { isGoodCard, normalizeCode } from './learnerCode';
import { colors, fonts, size, space } from './theme';
import { s } from './scale';

type Props = {
  visible: boolean;
  onClose: () => void;
  /** Called with a good learner code (the card holds the code only, never the PIN). */
  onCard: (code: string) => void;
};

/**
 * Scan my card. A full screen camera with a frame to aim at. Only a real TaraBasa card is accepted: the code must look
 * right and its check digit must add up, so a scratched or foreign QR code is turned away on the phone, kindly.
 */
export function CardScanner({ visible, onClose, onCard }: Props) {
  const insets = useSafeAreaInsets();
  const [permission, requestPermission] = useCameraPermissions();
  const [problem, setProblem] = useState<string | null>(null);
  const [found, setFound] = useState<string | null>(null);
  const locked = useRef(false);

  useEffect(() => {
    if (!visible) return;
    setProblem(null);
    setFound(null);
    locked.current = false;
    if (permission && !permission.granted && permission.canAskAgain) requestPermission();
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [visible]);

  function onScanned(result: { data: string }) {
    if (locked.current) return;
    locked.current = true;
    if (isGoodCard(result.data)) {
      const code = normalizeCode(result.data);
      setFound(code);
      setTimeout(() => onCard(code), 800); // long enough to see the green tick
    } else {
      setProblem('That is not a TaraBasa learner card. Try your card again.');
      setTimeout(() => {
        setProblem(null);
        locked.current = false;
      }, 2400);
    }
  }

  const blocked = permission && !permission.granted && !permission.canAskAgain;

  return (
    <Modal visible={visible} animationType="slide" onRequestClose={onClose} statusBarTranslucent>
      <View style={styles.root}>
        {permission?.granted ? (
          <CameraView
            style={StyleSheet.absoluteFill}
            facing="back"
            barcodeScannerSettings={{ barcodeTypes: ['qr'] }}
            onBarcodeScanned={onScanned}
          />
        ) : null}

        <View style={[styles.top, { paddingTop: insets.top + 8 }]}>
          <IconBtn icon="x" label="Close the camera" onPress={onClose} />
        </View>

        {permission?.granted ? (
          <View style={styles.center} pointerEvents="none">
            <View style={[styles.frame, found ? styles.frameOk : problem ? styles.frameBad : null]}>
              {found ? <Icon name="check-circle" size={s(96)} color={colors.ok} /> : null}
            </View>
          </View>
        ) : (
          <View style={styles.center}>
            <View style={styles.sheet}>
              <H2 style={{ textAlign: 'center' }}>{blocked ? 'The camera is blocked' : 'TaraBasa needs the camera'}</H2>
              <P style={{ textAlign: 'center' }}>
                {blocked
                  ? 'Allow the camera for TaraBasa in the phone settings, or type your code instead.'
                  : 'The camera is only used to read the code on your learner card.'}
              </P>
              {blocked ? (
                <Btn label="Open settings" onPress={() => Linking.openSettings()} />
              ) : (
                <Btn label="Allow the camera" onPress={() => requestPermission()} />
              )}
              <Btn label="Type my code instead" tone="ghost" onPress={onClose} />
            </View>
          </View>
        )}

        {permission?.granted ? (
          <View style={[styles.bottom, { paddingBottom: Math.max(insets.bottom, 16) + 8 }]}>
            <Text style={[styles.hint, problem && { color: '#ffd2cf' }]} accessibilityLiveRegion="polite">
              {found ? `Got it: ${found}` : (problem ?? 'Hold your card inside the box. Keep still for a moment.')}
            </Text>
          </View>
        ) : null}
      </View>
    </Modal>
  );
}

const FRAME = s(240);

const styles = StyleSheet.create({
  root: { flex: 1, backgroundColor: '#0c1722' },
  top: { position: 'absolute', top: 0, left: 0, right: 0, paddingHorizontal: size.gutter, zIndex: 2, alignItems: 'flex-start' },
  center: { ...StyleSheet.absoluteFill, alignItems: 'center', justifyContent: 'center', padding: size.gutter },
  frame: {
    width: FRAME,
    height: FRAME,
    borderRadius: s(28),
    borderWidth: 5,
    borderColor: colors.orange,
    alignItems: 'center',
    justifyContent: 'center',
    backgroundColor: 'rgba(255,255,255,0.06)',
  },
  frameOk: { borderColor: colors.ok, backgroundColor: 'rgba(255,255,255,0.85)' },
  frameBad: { borderColor: '#ff8a80' },
  sheet: { width: '100%', maxWidth: size.maxWidth, backgroundColor: colors.white, borderRadius: s(24), padding: space[20], gap: space[14] },
  bottom: { position: 'absolute', left: 0, right: 0, bottom: 0, paddingHorizontal: size.gutter, backgroundColor: 'rgba(12,23,34,0.78)', paddingTop: space[16] },
  hint: { fontFamily: fonts.ui, fontSize: s(19), lineHeight: s(26), color: colors.white, textAlign: 'center' },
});
