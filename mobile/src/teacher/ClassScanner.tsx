import { useEffect, useRef, useState } from 'react';
import { Linking, Modal, StyleSheet, Text, View } from 'react-native';
import { CameraView, useCameraPermissions } from 'expo-camera';
import { useSafeAreaInsets } from 'react-native-safe-area-context';
import * as api from '../api';
import { Btn, H2, Icon, IconBtn, P } from '../ui';
import { isGoodCard, normalizeCode } from '../learnerCode';
import { colors, fonts, size, space } from '../theme';
import { s } from '../scale';

type Props = {
  visible: boolean;
  classId: number;
  className: string;
  /** Closing the camera (Done, the X or the phone's back button). The class list is fetched again by the caller. */
  onClose: () => void;
};

type Result = { key: number; ok: boolean; text: string };

/**
 * Scan learner cards. The phone's camera reads the code on each card (the check digit is checked on the phone first, so a
 * scratched or foreign QR code is turned away kindly), then asks the server to put the child in this class, with exactly the
 * rules typing a code has: one grade per class, the reading level guard, the parent is told. It keeps scanning, so a whole
 * class is added in a row, and lists what happened to each card.
 */
export function ClassScanner({ visible, classId, className, onClose }: Props) {
  const insets = useSafeAreaInsets();
  const [permission, requestPermission] = useCameraPermissions();
  const [results, setResults] = useState<Result[]>([]);
  const [working, setWorking] = useState(false);
  const [flash, setFlash] = useState<'ok' | 'bad' | null>(null);
  const [hint, setHint] = useState<string | null>(null);
  const busy = useRef(false);
  const tried = useRef(new Set<string>());
  const quietUntil = useRef(0);
  const counter = useRef(0);

  useEffect(() => {
    if (!visible) return;
    setResults([]);
    setHint(null);
    setFlash(null);
    busy.current = false;
    tried.current = new Set();
    if (permission && !permission.granted && permission.canAskAgain) requestPermission();
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [visible]);

  function say(ok: boolean, text: string) {
    setResults((r) => [{ key: ++counter.current, ok, text }, ...r].slice(0, 5));
    setFlash(ok ? 'ok' : 'bad');
    setTimeout(() => setFlash(null), 1200);
  }

  async function onScanned(result: { data: string }) {
    if (busy.current || Date.now() < quietUntil.current) return;

    if (!isGoodCard(result.data)) {
      quietUntil.current = Date.now() + 2200;
      setHint('That is not a TaraBasa learner card.');
      setTimeout(() => setHint(null), 2200);
      return;
    }

    const code = normalizeCode(result.data);
    if (tried.current.has(code)) return; // the same card is still in front of the camera
    tried.current.add(code);

    busy.current = true;
    setWorking(true);
    try {
      const r = await api.joinLearner(classId, code);
      if (r.ok) say(true, `${r.learner.name} added to ${className}`);
      else say(false, r.message);
    } catch (e) {
      tried.current.delete(code); // a lost connection is not the card's fault: it can be scanned again
      say(false, e instanceof Error ? e.message : 'Something went wrong. Please try again.');
    } finally {
      busy.current = false;
      setWorking(false);
    }
  }

  const blocked = permission && !permission.granted && !permission.canAskAgain;

  return (
    <Modal visible={visible} animationType="slide" onRequestClose={onClose} statusBarTranslucent>
      <View style={styles.root}>
        {permission?.granted ? (
          <CameraView style={StyleSheet.absoluteFill} facing="back" barcodeScannerSettings={{ barcodeTypes: ['qr'] }} onBarcodeScanned={onScanned} />
        ) : null}

        <View style={[styles.top, { paddingTop: insets.top + 8 }]}>
          <IconBtn icon="x" label="Close the camera" onPress={onClose} />
          <Text style={styles.title}>Scan learner cards</Text>
        </View>

        {permission?.granted ? (
          <View style={styles.center} pointerEvents="none">
            <View style={[styles.frame, flash === 'ok' && styles.frameOk, flash === 'bad' && styles.frameBad]}>
              {working ? <Text style={styles.working}>Adding</Text> : flash === 'ok' ? <Icon name="check-circle" size={s(88)} color={colors.ok} /> : null}
            </View>
          </View>
        ) : (
          <View style={styles.center}>
            <View style={styles.sheet}>
              <H2 style={{ textAlign: 'center' }}>{blocked ? 'The camera is blocked' : 'TaraBasa needs the camera'}</H2>
              <P style={{ textAlign: 'center' }}>
                {blocked ? 'Allow the camera for TaraBasa in the phone settings, or type the code instead.' : 'The camera is only used to read the code on each learner card.'}
              </P>
              {blocked ? <Btn label="Open settings" onPress={() => Linking.openSettings()} /> : <Btn label="Allow the camera" onPress={() => requestPermission()} />}
              <Btn label="Type the code instead" tone="ghost" onPress={onClose} />
            </View>
          </View>
        )}

        {permission?.granted ? (
          <View style={[styles.bottom, { paddingBottom: Math.max(insets.bottom, 16) + 8 }]}>
            <Text style={[styles.hint, hint && { color: '#ffd2cf' }]} accessibilityLiveRegion="polite">
              {hint ?? 'Hold a card inside the box. Keep going: every card you scan is added.'}
            </Text>
            <View style={{ gap: space[6] }} accessibilityLiveRegion="polite">
              {results.map((r) => (
                <View key={r.key} style={styles.result}>
                  <Icon name={r.ok ? 'check-circle' : 'x-circle'} size={s(22)} color={r.ok ? '#8be3ae' : '#ff9e96'} />
                  <Text style={styles.resultText}>{r.text}</Text>
                </View>
              ))}
            </View>
            <Btn label="Done" onPress={onClose} />
          </View>
        ) : null}
      </View>
    </Modal>
  );
}

const FRAME = s(220);

const styles = StyleSheet.create({
  root: { flex: 1, backgroundColor: '#0c1722' },
  top: { position: 'absolute', top: 0, left: 0, right: 0, paddingHorizontal: size.gutter, zIndex: 2, flexDirection: 'row', alignItems: 'center', gap: space[12] },
  title: { fontFamily: fonts.uiBold, fontSize: s(20), color: colors.white },
  center: { ...StyleSheet.absoluteFill, alignItems: 'center', justifyContent: 'center', padding: size.gutter, paddingBottom: s(260) },
  frame: { width: FRAME, height: FRAME, borderRadius: s(28), borderWidth: 5, borderColor: colors.orange, alignItems: 'center', justifyContent: 'center', backgroundColor: 'rgba(255,255,255,0.06)' },
  frameOk: { borderColor: colors.ok, backgroundColor: 'rgba(255,255,255,0.85)' },
  frameBad: { borderColor: '#ff8a80' },
  working: { fontFamily: fonts.uiBold, fontSize: s(22), color: colors.white },
  sheet: { width: '100%', maxWidth: size.maxWidth, backgroundColor: colors.white, borderRadius: s(24), padding: space[20], gap: space[14] },
  bottom: { position: 'absolute', left: 0, right: 0, bottom: 0, paddingHorizontal: size.gutter, backgroundColor: 'rgba(12,23,34,0.86)', paddingTop: space[14], gap: space[10] },
  hint: { fontFamily: fonts.ui, fontSize: s(17), lineHeight: s(23), color: colors.white, textAlign: 'center' },
  result: { flexDirection: 'row', alignItems: 'flex-start', gap: space[8] },
  resultText: { flex: 1, fontFamily: fonts.uiRegular, fontSize: s(15), lineHeight: s(20), color: colors.white },
});
