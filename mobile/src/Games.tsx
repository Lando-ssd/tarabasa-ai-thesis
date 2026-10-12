import { useCallback, useEffect, useState } from 'react';
import { StyleSheet, Text, View } from 'react-native';
import * as api from './api';
import { Btn, Busy, H1, Icon, Notice, Screen, Small } from './ui';
import { colors, fonts } from './theme';

export type GameKey = 'word-builder' | 'letter-match' | 'balloon-pop';

type Card = { key: GameKey; title: string; text: string; chips: string[]; icon: string; bg: string; fg: string; isNew?: boolean };

// The same three games and the same words as the website's Games page.
const CARDS: Card[] = [
  {
    key: 'word-builder',
    title: 'Word Builder',
    text: 'Put the letters in order to spell a word. Hear the word first, and say it when you finish.',
    chips: ['Listen', 'Speak', '3 levels'],
    icon: 'text-aa',
    bg: colors.sky,
    fg: colors.blue,
  },
  {
    key: 'letter-match',
    title: 'Letter Match',
    text: 'Hear a letter, then find it and its small twin.',
    chips: ['Listen', '3 levels'],
    icon: 'puzzle-piece',
    bg: colors.mint,
    fg: colors.ok,
  },
  {
    key: 'balloon-pop',
    title: 'Balloon Pop',
    text: 'A balloon shows a word. Say the word out loud to pop it!',
    chips: ['Listen', 'Speak', '3 levels'],
    icon: 'balloon',
    bg: colors.gold,
    fg: colors.orangeDark,
    isNew: true,
  },
];

/**
 * Practice Games: free play, no points, no streak, no level. A game the child's own reading says would help right now gets a
 * "Recommended" ribbon, but it is never a gate: every game is always playable.
 */
export function Games({ onPlay }: { onPlay: (game: GameKey) => void }) {
  const [info, setInfo] = useState<api.GamesInfo | null>(null);
  const [error, setError] = useState<string | null>(null);

  const load = useCallback(async () => {
    setError(null);
    try {
      setInfo(await api.games());
    } catch (e) {
      setError(e instanceof Error ? e.message : 'Something went wrong. Please try again.');
    }
  }, []);

  useEffect(() => {
    load();
  }, [load]);

  const pick = info?.recommended ?? null;
  const tip =
    pick === 'word-builder'
      ? 'Your reading shows spelling practice would help right now. Word Builder uses your own tricky words!'
      : pick === 'letter-match'
        ? 'Your reading shows letter practice would help right now. Try Letter Match!'
        : info?.hasCompetencyData
          ? 'Your word skills are looking strong. Keep sharpening them here anytime you want!'
          : null;

  return (
    <Screen withTabBar>
      <H1>Practice Games</H1>
      <Small>Free play. No points, just for fun!</Small>

      {error ? (
        <View style={{ gap: 12, marginTop: 14 }}>
          <Notice text={error} />
          <Btn label="Try Again" onPress={load} />
        </View>
      ) : null}

      {tip ? (
        <View style={styles.tip} accessibilityLiveRegion="polite">
          <Icon name="lightbulb" size={22} color={colors.orangeDark} />
          <Text maxFontSizeMultiplier={1.2} style={styles.tipText}>
            {tip}
          </Text>
        </View>
      ) : !info && !error ? (
        <Busy style={{ marginTop: 10 }} />
      ) : null}

      <View style={{ gap: 14, marginTop: 14 }}>
        {CARDS.map((card) => (
          <View key={card.key} style={[styles.card, { backgroundColor: card.bg }]}>
            <View style={styles.head}>
              <View style={styles.iconTile}>
                <Icon name={card.icon} size={30} color={card.fg} />
              </View>
              {pick === card.key ? (
                <View style={styles.ribbon}>
                  <Icon name="star" size={16} color={colors.white} />
                  <Text style={styles.ribbonText}>Recommended</Text>
                </View>
              ) : card.isNew ? (
                <View style={[styles.ribbon, { backgroundColor: colors.ok }]}>
                  <Text style={styles.ribbonText}>New</Text>
                </View>
              ) : null}
            </View>
            <Text maxFontSizeMultiplier={1.2} style={styles.title}>
              {card.title}
            </Text>
            <Text maxFontSizeMultiplier={1.2} style={styles.text}>
              {card.text}
            </Text>
            <View style={styles.chips}>
              {card.chips.map((chip) => (
                <View key={chip} style={styles.chip}>
                  <Text maxFontSizeMultiplier={1.15} style={styles.chipText}>
                    {chip}
                  </Text>
                </View>
              ))}
            </View>
            <Btn label="Play" tone={card.key === 'letter-match' ? 'blue' : 'orange'} icon="game-controller" onPress={() => onPlay(card.key)} />
          </View>
        ))}
      </View>

      <Small style={{ marginTop: 14 }}>Where the words come from: words you missed in your readings, then the word list for your grade.</Small>
      <Small style={{ marginTop: 6 }}>Games are practice. They never change your points, your streak or your level, and what you say in a game is not graded.</Small>
    </Screen>
  );
}

const styles = StyleSheet.create({
  tip: { flexDirection: 'row', alignItems: 'center', gap: 10, backgroundColor: colors.orangeSoft, borderRadius: 14, padding: 12, marginTop: 12, borderWidth: 1, borderColor: '#f3d6bb' },
  tipText: { flex: 1, fontFamily: fonts.ui, fontSize: 16, lineHeight: 22, color: colors.ink },
  card: { borderRadius: 22, padding: 16, gap: 8, borderBottomWidth: 5, borderBottomColor: 'rgba(23,50,77,0.08)' },
  head: { flexDirection: 'row', alignItems: 'center', justifyContent: 'space-between' },
  iconTile: { width: 52, height: 52, borderRadius: 16, backgroundColor: colors.white, alignItems: 'center', justifyContent: 'center' },
  ribbon: { flexDirection: 'row', alignItems: 'center', gap: 4, backgroundColor: colors.orange, borderRadius: 99, paddingHorizontal: 10, minHeight: 28 },
  ribbonText: { fontFamily: fonts.uiBold, fontSize: 13, color: colors.white },
  title: { fontFamily: fonts.displayMedium, fontSize: 24, lineHeight: 30, color: colors.ink },
  text: { fontFamily: fonts.uiRegular, fontSize: 17, lineHeight: 23, color: colors.ink },
  chips: { flexDirection: 'row', flexWrap: 'wrap', gap: 6, marginBottom: 4 },
  chip: { backgroundColor: 'rgba(255,255,255,0.8)', borderRadius: 99, paddingHorizontal: 10, minHeight: 26, justifyContent: 'center' },
  chipText: { fontFamily: fonts.uiBold, fontSize: 13, color: colors.slate },
});
