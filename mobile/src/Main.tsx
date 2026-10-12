import { useCallback, useEffect, useState } from 'react';
import { BackHandler, Pressable, StyleSheet, Text, View } from 'react-native';
import { useSafeAreaInsets } from 'react-native-safe-area-context';
import type { ReadingOutcome } from './api';
import { Badges } from './Badges';
import { ComingSoon } from './ComingSoon';
import { Home } from './Home';
import { ReadList } from './ReadList';
import { Results } from './Results';
import { useSession } from './session';
import { Story } from './Story';
import { Icon } from './ui';
import { colors, fonts, size } from './theme';

type Tab = 'home' | 'read' | 'games' | 'badges' | 'shelf';
type ReadRoute =
  | { name: 'list' }
  | { name: 'story'; id: number }
  | { name: 'results'; title: string; outcome: Extract<ReadingOutcome, { status: 'scored' }> };

const TABS: { key: Tab; label: string; icon: string }[] = [
  { key: 'home', label: 'Home', icon: 'house' },
  { key: 'read', label: 'Read', icon: 'book-open' },
  { key: 'games', label: 'Games', icon: 'game-controller' },
  { key: 'badges', label: 'Badges', icon: 'medal' },
  { key: 'shelf', label: 'Shelf', icon: 'books' },
];

/**
 * The signed in app: five places in a bottom bar (Home, Read, Games, Badges, Shelf), the same places as the website. Games
 * and Shelf say "coming soon" in this first version. While a child is reading, the bar goes away so a stray tap cannot
 * leave the story. The phone's back button goes back one step, then to Home, then leaves the app.
 */
export function Main() {
  const { learner, setLearner, reloadHome } = useSession();
  const [tab, setTab] = useState<Tab>('home');
  const [route, setRoute] = useState<ReadRoute>({ name: 'list' });

  const reading = tab === 'read' && route.name !== 'list';

  const goTab = useCallback(
    (next: Tab) => {
      setTab(next);
      if (next !== 'read') setRoute({ name: 'list' });
      if (next === 'home' || next === 'badges') reloadHome();
    },
    [reloadHome],
  );

  // The phone's back button.
  useEffect(() => {
    const sub = BackHandler.addEventListener('hardwareBackPress', () => {
      if (tab === 'read' && route.name !== 'list') {
        if (route.name === 'results') goTab('home');
        else setRoute({ name: 'list' });
        return true;
      }
      if (tab !== 'home') {
        goTab('home');
        return true;
      }
      return false; // on Home: leave the app
    });
    return () => sub.remove();
  }, [tab, route, goTab]);

  let screen: React.ReactNode;
  if (tab === 'home') {
    screen = <Home onStartReading={() => goTab('read')} />;
  } else if (tab === 'read') {
    if (route.name === 'story') {
      screen = (
        <Story
          id={route.id}
          onExit={() => setRoute({ name: 'list' })}
          onScored={(title, outcome) => {
            setLearner(outcome.learner);
            setRoute({ name: 'results', title, outcome });
          }}
        />
      );
    } else if (route.name === 'results') {
      screen = <Results firstName={learner.firstName} title={route.title} outcome={route.outcome} onDone={() => goTab('home')} />;
    } else {
      screen = <ReadList onOpen={(id) => setRoute({ name: 'story', id })} />;
    }
  } else if (tab === 'games') {
    screen = <ComingSoon title="Games are coming soon" text="Word games with Tara are on the way. For now, read a story and earn points." />;
  } else if (tab === 'badges') {
    screen = <Badges />;
  } else {
    screen = <ComingSoon title="Your Shelf is coming soon" text="The stories you have read will live here, so you can read them again." />;
  }

  return (
    <View style={{ flex: 1, backgroundColor: colors.page }}>
      <View style={{ flex: 1 }}>{screen}</View>
      {reading ? null : <TabBar active={tab} onChange={goTab} />}
    </View>
  );
}

function TabBar({ active, onChange }: { active: Tab; onChange: (tab: Tab) => void }) {
  const insets = useSafeAreaInsets();
  return (
    <View style={[styles.bar, { paddingBottom: insets.bottom }]} accessibilityRole="tablist">
      {TABS.map((t) => {
        const on = t.key === active;
        return (
          <Pressable
            key={t.key}
            accessibilityRole="tab"
            accessibilityLabel={t.label}
            accessibilityState={{ selected: on }}
            onPress={() => onChange(t.key)}
            style={({ pressed }) => [styles.tab, pressed && { opacity: 0.6 }]}
          >
            <View style={[styles.mark, on && styles.markOn]} />
            <Icon name={t.icon} size={28} color={on ? colors.orange : colors.slate} />
            <Text maxFontSizeMultiplier={1.1} style={[styles.label, on && { color: colors.orangeDark }]}>
              {t.label}
            </Text>
          </Pressable>
        );
      })}
    </View>
  );
}

const styles = StyleSheet.create({
  bar: { flexDirection: 'row', borderTopWidth: 2, borderTopColor: colors.line, backgroundColor: colors.white },
  tab: { flex: 1, height: size.tabBar, alignItems: 'center', justifyContent: 'center', gap: 2 },
  mark: { position: 'absolute', top: 0, width: 28, height: 4, borderBottomLeftRadius: 4, borderBottomRightRadius: 4, backgroundColor: 'transparent' },
  markOn: { backgroundColor: colors.orange },
  label: { fontFamily: fonts.uiBold, fontSize: 13, color: colors.slate },
});
