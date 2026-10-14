import { useCallback, useEffect, useMemo, useState, type ReactNode } from 'react';
import { StatusBar } from 'expo-status-bar';
import { Platform, View } from 'react-native';
import { SafeAreaProvider } from 'react-native-safe-area-context';
import * as SplashScreen from 'expo-splash-screen';
import * as api from './src/api';
import * as storage from './src/storage';
import { ErrorBoundary } from './src/ErrorBoundary';
import { useAppFonts } from './src/fonts';
import { FirstCheck } from './src/FirstCheck';
import { Main } from './src/Main';
import { sweepRecordings } from './src/recordingFiles';
import { SessionContext, type Session } from './src/session';
import { SignIn } from './src/SignIn';
import { Animation, Btn, Busy, H2, Notice, P, Screen } from './src/ui';
import { colors } from './src/theme';
import { DEMO, startHash } from './src/config';

SplashScreen.preventAutoHideAsync().catch(() => {});

// Demo mode (hosted preview): answer from sample data. Never true in a phone build.
// (The platform check comes first on purpose: the bundler folds it away on a phone and then leaves the sample data out of the app.)
if (Platform.OS === 'web' && DEMO) require('./src/demoServer').installDemoServer();

const NAME_KEY = 'tarabasa_first_name';

type Boot =
  | { phase: 'boot' }
  | { phase: 'offline'; message: string }
  | { phase: 'out'; notice?: string }
  | { phase: 'in'; learner: api.LearnerPayload; firstCheck: boolean; home: api.Home | null };

// Developer only, browser only. On a wide browser window the app is drawn inside a phone (src/PhoneFrame.tsx) so the real
// screen size is obvious; ?frame=off switches that off. #gallery shows single screens with sample data (src/devGallery.tsx).
// Neither ever runs in a phone build.
/**
 * Hosted preview and the developer's gallery only: leaves the strip a real phone's status bar and its gesture bar take, so a
 * screen is judged against the area it really has (the preview paints the status bar over the top strip). Never on a phone.
 */
function SystemBars({ children }: { children: ReactNode }) {
  const on = Platform.OS === 'web' && (DEMO || (__DEV__ && startHash().startsWith('#gallery')));
  if (!on) return <>{children}</>;
  return <View style={{ flex: 1, backgroundColor: colors.page, paddingTop: 24, paddingBottom: 24 }}>{children}</View>;
}

export default function Root() {
  if (Platform.OS === 'web' && typeof window !== 'undefined') {
    const embedded = window.self !== window.top;
    const frameOff = /[?&]frame=off/.test(window.location.search);
    if (!embedded && !frameOff && window.innerWidth >= 600) {
      const { PhoneFrame } = require('./src/PhoneFrame');
      return <PhoneFrame />;
    }
    if ((__DEV__ || DEMO) && startHash().startsWith('#gallery')) {
      const { Gallery } = require('./src/devGallery');
      return (
        <SystemBars>
          <Gallery />
        </SystemBars>
      );
    }
  }
  return (
    <SystemBars>
      <ErrorBoundary>
        <App />
      </ErrorBoundary>
    </SystemBars>
  );
}

function App() {
  const fontsReady = useAppFonts();
  const [state, setState] = useState<Boot>({ phase: 'boot' });

  // A child who signed in before stays signed in: ask the server whether the saved sign in still works.
  const boot = useCallback(async () => {
    setState({ phase: 'boot' });
    if (!(await api.getToken())) {
      setState({ phase: 'out' });
      return;
    }
    try {
      const home = await api.home();
      setState({ phase: 'in', learner: home.learner, firstCheck: false, home });
    } catch (e) {
      if (e instanceof api.ApiError && e.status === 403) {
        // Signed in, but the first reading check is not done yet. The saved sign in is still good.
        const firstName = (await storage.getItem(NAME_KEY)) ?? 'friend';
        setState({ phase: 'in', learner: { firstName } as api.LearnerPayload, firstCheck: true, home: null });
      } else if (e instanceof api.ApiError && e.status === 401) {
        await api.clearToken();
        setState({ phase: 'out', notice: 'Please log in again.' });
      } else {
        setState({ phase: 'offline', message: e instanceof Error ? e.message : 'We could not reach TaraBasa.' });
      }
    }
  }, []);

  useEffect(() => {
    sweepRecordings(); // no recording of a child's voice is left over from the last visit
    boot();
  }, [boot]);

  const leave = useCallback(async (notice?: string) => {
    sweepRecordings();
    try {
      await api.logout();
    } catch {
      /* the saved sign in is removed either way */
    }
    await api.clearToken();
    await storage.removeItem(NAME_KEY);
    setState({ phase: 'out', notice });
  }, []);

  // If the server ever says the sign in is no longer good, go back to sign in instead of showing errors.
  useEffect(() => {
    api.setUnauthorizedHandler(() => {
      api.clearToken().finally(() => setState({ phase: 'out', notice: 'Please log in again.' }));
    });
    return () => api.setUnauthorizedHandler(null);
  }, []);

  // Hide the splash as soon as the fonts are ready. While the app checks the saved sign in (which can take a minute when
  // the free server is asleep) a waiting screen explains why, instead of a splash that looks frozen.
  useEffect(() => {
    if (fontsReady) SplashScreen.hideAsync().catch(() => {});
  }, [fontsReady]);

  if (!fontsReady) return null;

  return (
    <SafeAreaProvider>
      <StatusBar style="dark" />
      <View style={{ flex: 1, backgroundColor: colors.page }}>
        {state.phase === 'boot' ? (
          <Booting />
        ) : state.phase === 'offline' ? (
          <Offline message={state.message} onRetry={boot} />
        ) : state.phase === 'out' ? (
          <SignIn
            notice={state.notice}
            onSignedIn={async (result) => {
              await api.saveToken(result.token);
              await storage.setItem(NAME_KEY, result.learner.firstName);
              setState({ phase: 'in', learner: result.learner, firstCheck: result.needsDiagnostic, home: null });
            }}
          />
        ) : (
          <SignedIn key={state.learner.firstName} initial={state} onSignOut={() => leave()} />
        )}
      </View>
    </SafeAreaProvider>
  );
}

function Booting() {
  return (
    <Screen scroll={false}>
      <View style={{ flex: 1, alignItems: 'center', justifyContent: 'center', gap: 14 }}>
        <Animation name="head" px={140} />
        <H2 style={{ textAlign: 'center' }}>Getting TaraBasa ready</H2>
        <P style={{ textAlign: 'center' }}>The first time in a while it can take up to a minute. Thank you for waiting.</P>
        <Busy />
      </View>
    </Screen>
  );
}

function Offline({ message, onRetry }: { message: string; onRetry: () => void }) {
  return (
    <Screen footer={<Btn label="Try Again" onPress={onRetry} />}>
      <View style={{ flex: 1, alignItems: 'center', justifyContent: 'center', gap: 14 }}>
        <Animation name="head" px={140} />
        <H2 style={{ textAlign: 'center' }}>We could not reach TaraBasa</H2>
        <Notice text={message} />
        <P style={{ textAlign: 'center' }}>Check the internet, then tap Try Again.</P>
      </View>
    </Screen>
  );
}

/** Everything after sign in. Holds who is signed in and Home's numbers, and shares them with every screen. */
function SignedIn({ initial, onSignOut }: { initial: Extract<Boot, { phase: 'in' }>; onSignOut: () => void }) {
  const [learner, setLearner] = useState(initial.learner);
  const [home, setHome] = useState<api.Home | null>(initial.home);
  const [homeError, setHomeError] = useState<string | null>(null);
  const [firstCheck, setFirstCheck] = useState(initial.firstCheck);
  const [fontStep, setFontStepState] = useState(initial.learner.readingFontStep ?? 3);

  const reloadHome = useCallback(async () => {
    try {
      const h = await api.home();
      setHome(h);
      setLearner(h.learner);
      setFontStepState(h.learner.readingFontStep ?? 3);
      setHomeError(null);
    } catch (e) {
      if (e instanceof api.ApiError && e.status === 403) setFirstCheck(true);
      else setHomeError(e instanceof Error ? e.message : 'Something went wrong. Please try again.');
    }
  }, []);

  useEffect(() => {
    if (!firstCheck && !home) reloadHome();
  }, [firstCheck, home, reloadHome]);

  const session = useMemo<Session>(
    () => ({
      learner,
      home,
      homeError,
      reloadHome,
      fontStep,
      setFontStep: (step: number) => {
        const next = Math.min(5, Math.max(1, step));
        setFontStepState(next);
        api.setFontStep(next).catch(() => {}); // a failed save only means the choice is not kept for next time
      },
      setLearner,
      signOut: onSignOut,
    }),
    [learner, home, homeError, reloadHome, fontStep, onSignOut],
  );

  return (
    <SessionContext.Provider value={session}>
      {firstCheck ? (
        <FirstCheck
          firstName={learner.firstName}
          onDone={() => {
            setFirstCheck(false);
            setHome(null);
            reloadHome();
          }}
        />
      ) : (
        <Main />
      )}
    </SessionContext.Provider>
  );
}
