import { StatusBar } from 'expo-status-bar';
import { useEffect, useState } from 'react';
import { ActivityIndicator, Pressable, ScrollView, StyleSheet, Text, TextInput, View } from 'react-native';
import * as api from './src/api';
import { API_URL } from './src/config';
import { PhoneCheck } from './src/PhoneCheck';

// Test build: a code and PIN sign in, then a plain checklist of what a real phone must do (reach the server,
// record, get scored, scan a card, speak, animate). It is not the real app design; that comes after approval.
export default function App() {
  const [code, setCode] = useState('');
  const [pin, setPin] = useState('');
  const [busy, setBusy] = useState(false);
  const [error, setError] = useState<string | null>(null);
  const [learner, setLearner] = useState<api.LearnerPayload | null>(null);
  const [needsFirstCheck, setNeedsFirstCheck] = useState(false);

  // A child who signed in before stays signed in: ask the server whether the saved token still works.
  useEffect(() => {
    (async () => {
      if (!(await api.getToken())) return;
      setBusy(true);
      try {
        const data = await api.home();
        setLearner(data.learner);
      } catch (e) {
        if (e instanceof api.ApiError && e.status === 403) {
          // Signed in, but the first reading check is not done yet. The token is still good.
          setNeedsFirstCheck(true);
          setLearner({ firstName: 'there' } as api.LearnerPayload);
        } else if (e instanceof api.ApiError && e.status === 401) {
          await api.clearToken();
        }
      } finally {
        setBusy(false);
      }
    })();
  }, []);

  async function signIn() {
    setBusy(true);
    setError(null);
    try {
      const result = await api.login(code.trim(), pin.trim());
      await api.saveToken(result.token);
      setLearner(result.learner);
      setNeedsFirstCheck(result.needsDiagnostic);
    } catch (e) {
      setError(e instanceof Error ? e.message : 'Something went wrong.');
    } finally {
      setBusy(false);
    }
  }

  async function signOut() {
    try {
      await api.logout();
    } catch {
      /* the saved token is removed either way */
    }
    await api.clearToken();
    setLearner(null);
    setNeedsFirstCheck(false);
    setPin('');
  }

  return (
    <ScrollView contentContainerStyle={styles.page} keyboardShouldPersistTaps="handled">
      <StatusBar style="dark" />
      <Text style={styles.title}>TaraBasa AI</Text>
      <Text style={styles.small}>Phone check build 0.1.1. Server: {API_URL}</Text>

      {learner ? (
        <View style={styles.wide}>
          <View style={styles.card}>
            <Text style={styles.big}>Hello, {learner.firstName}</Text>
            {learner.learnerCode ? (
              <>
                <Text>Code {learner.learnerCode}</Text>
                <Text>
                  Reading step: {learner.readingStep?.step ?? 'none yet'} {learner.readingStep?.name}. Level: {learner.masteryLevel}
                </Text>
                <Text>
                  Points {learner.points}. Days in a row {learner.dayStreak}.
                </Text>
              </>
            ) : null}
            <Pressable style={styles.button} onPress={signOut}>
              <Text style={styles.buttonText}>Log out</Text>
            </Pressable>
          </View>
          <PhoneCheck needsFirstCheck={needsFirstCheck} />
        </View>
      ) : (
        <View style={styles.card}>
          <Text style={styles.label}>Learner code</Text>
          <TextInput
            style={styles.input}
            value={code}
            onChangeText={setCode}
            placeholder="TB26-48293"
            autoCapitalize="characters"
            autoCorrect={false}
          />
          <Text style={styles.label}>Secret PIN</Text>
          <TextInput
            style={styles.input}
            value={pin}
            onChangeText={(t) => setPin(t.replace(/\D/g, '').slice(0, 4))}
            placeholder="4 numbers"
            keyboardType="number-pad"
            secureTextEntry
          />
          {error ? <Text style={styles.error}>{error}</Text> : null}
          <Pressable style={[styles.button, busy && styles.disabled]} onPress={signIn} disabled={busy || !code || pin.length !== 4}>
            {busy ? <ActivityIndicator color="#fff" /> : <Text style={styles.buttonText}>Sign in</Text>}
          </Pressable>
          {busy ? <Text style={styles.small}>The server may be waking up. This can take up to a minute.</Text> : null}
        </View>
      )}
    </ScrollView>
  );
}

const styles = StyleSheet.create({
  page: { flexGrow: 1, alignItems: 'center', padding: 20, paddingTop: 56, paddingBottom: 60, backgroundColor: '#fff' },
  wide: { width: '100%', maxWidth: 520, gap: 12 },
  title: { fontSize: 30, fontWeight: '700', color: '#dd7014', marginBottom: 4 },
  small: { fontSize: 12, color: '#56697a', marginBottom: 12, textAlign: 'center' },
  card: { width: '100%', maxWidth: 520, backgroundColor: '#eaf4ff', borderRadius: 20, padding: 18, gap: 8 },
  big: { fontSize: 24, fontWeight: '700' },
  label: { fontWeight: '600', marginTop: 4 },
  input: { backgroundColor: '#fff', borderRadius: 12, borderWidth: 1, borderColor: '#c5d6e8', padding: 12, fontSize: 18 },
  button: { backgroundColor: '#dd7014', borderRadius: 14, padding: 14, alignItems: 'center', marginTop: 8 },
  disabled: { opacity: 0.6 },
  buttonText: { color: '#fff', fontWeight: '700', fontSize: 18 },
  error: { color: '#b3261e', fontWeight: '600' },
});
