import { useCallback, useEffect, useState } from 'react';
import { BackHandler, Pressable, StyleSheet, Text, View } from 'react-native';
import { useSafeAreaInsets } from 'react-native-safe-area-context';
import type { ReadingOutcome } from './api';
import { Badges } from './Badges';
import { BalloonPop } from './BalloonPop';
import { ComingSoon } from './ComingSoon';
import { Games, type GameKey } from './Games';
import { Home } from './Home';
import { ReadList } from './ReadList';
import { Reread } from './Reread';
import { Results } from './Results';
import { SentenceStars } from './SentenceStars';
import { WordTrain } from './WordTrain';
import { useSession } from './session';
import { Shelf } from './Shelf';
import { Story } from './Story';
import { Icon } from './ui';
import { colors, fonts, size } from './theme';
import { s } from './scale';

type Tab = 'home' | 'read' | 'games' | 'badges' | 'shelf';
type ReadRoute =
  | { name: 'list' }
  | { name: 'story'; id: number }
  | { name: 'results'; title: string; outcome: Extract<ReadingOutcome, { status: 'scored' }> };

type GamesRoute = { name: 'hub' } | { name: 'play'; game: GameKey };
type ShelfRoute = { name: 'list' } | { name: 'reread'; id: number };

const TABS: { key: Tab; label: string; icon: string }[] = [
  { key: 'home', label: 'Home', icon: 'house' },
  { key: 'read', label: 'Read', icon: 'book-open' },
  { key: 'games', label: 'Games', icon: 'game-controller' },
  { key: 'badges', label: 'Badges', icon: 'medal' },
  { key: 'shelf', label: 'Shelf', icon: 'books' },
];

/**
 * The signed in app: five places in a bottom bar (Home, Read, Games, Badges, Shelf), the same places as the website. Games
 * says "coming soon" until it is built. While a child is reading, the bar goes away so a stray tap cannot
 * leave the story. The phone's back button goes back one step, then to Home, then leaves the app.
 */
export function Main() {
  const { learner, setLearner, reloadHome } = useSession();
  const [tab, setTab] = useState<Tab>('home');
  const [route, setRoute] = useState<ReadRoute>({ name: 'list' });
  const [shelfRoute, setShelfRoute] = useState<ShelfRoute>({ name: 'list' });
  const [gamesRoute, setGamesRoute] = useState<GamesRoute>({ name: 'hub' });

  const reading =
    (tab === 'read' && route.name !== 'list') || (tab === 'shelf' && shelfRoute.name !== 'list') || (tab === 'games' && gamesRoute.name !== 'hub');

  const goTab = useCallback(
    (next: Tab) => {
      setTab(next);
      if (next !== 'read') setRoute({ name: 'list' });
      if (next !== 'shelf') setShelfRoute({ name: 'list' });
      if (next !== 'games') setGamesRoute({ name: 'hub' });
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
      if (tab === 'games' && gamesRoute.name !== 'hub') {
        setGamesRoute({ name: 'hub' });
        return true;
      }
      if (tab === 'shelf' && shelfRoute.name !== 'list') {
        setShelfRoute({ name: 'list' });
        return true;
      }
      if (tab !== 'home') {
        goTab('home');
        return true;
      }
      return false; // on Home: leave the app
    });
    return () => sub.remove();
  }, [tab, route, shelfRoute, gamesRoute, goTab]);

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
    const back = () => setGamesRoute({ name: 'hub' });
    if (gamesRoute.name === 'play' && gamesRoute.game === 'word-train') {
      screen = <WordTrain onExit={back} />;
    } else if (gamesRoute.name === 'play' && gamesRoute.game === 'sentence-stars') {
      screen = <SentenceStars onExit={back} />;
    } else if (gamesRoute.name === 'play') {
      screen = <BalloonPop onExit={back} />;
    } else {
      screen = <Games onPlay={(game) => setGamesRoute({ name: 'play', game })} />;
    }
  } else if (tab === 'badges') {
    screen = <Badges />;
  } else if (shelfRoute.name === 'reread') {
    screen = <Reread id={shelfRoute.id} onExit={() => setShelfRoute({ name: 'list' })} />;
  } else {
    screen = <Shelf onOpen={(id) => setShelfRoute({ name: 'reread', id })} onFindStory={() => goTab('read')} />;
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
            <Icon name={t.icon} size={s(28)} color={on ? colors.orange : colors.slate} />
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
  tab: { flex: 1, height: size.tabBar, alignItems: 'center', justifyContent: 'center', gap: s(2) },
  mark: { position: 'absolute', top: 0, width: s(28), height: s(4), borderBottomLeftRadius: s(4), borderBottomRightRadius: s(4), backgroundColor: 'transparent' },
  markOn: { backgroundColor: colors.orange },
  label: { fontFamily: fonts.uiBold, fontSize: s(13), color: colors.slate },
});
