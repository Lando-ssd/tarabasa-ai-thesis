import { useCallback, useEffect, useState } from 'react';
import { StyleSheet, Text, View } from 'react-native';
import * as api from './api';
import { Btn, Busy, H1, Icon, Notice, Screen, Small } from './ui';
import { colors, fonts } from './theme';
import { s } from './scale';

export type GameKey = api.GameName;

type Card = { key: GameKey; title: string; text: string; chips: string[]; icon: string; bg: string; fg: string; isNew?: boolean };

// The same three games and the same words as the website's Games page. Every game is a read-aloud game.
const CARDS: Card[] = [
  {
    key: 'balloon-pop',
    title: 'Balloon Pop',
    text: 'A balloon shows a word. Say the word out loud to pop it!',
    chips: ['Listen', 'Read aloud', '3 levels'],
    icon: 'balloon',
    bg: colors.gold,
    fg: colors.orangeDark,
  },
  {
    key: 'word-train',
    title: 'Word Train',
    text: 'Read the words on the train cars out loud to make the train roll. Letter Explorers read letters.',
    chips: ['Listen', 'Read aloud', '3 levels'],
    icon: 'train',
    bg: colors.sky,
    fg: colors.blue,
    isNew: true,
  },
  {
    key: 'sentence-stars',
    title: 'Sentence Stars',
    text: 'Read a sentence out loud to catch a star. Catch three!',
    chips: ['Listen', 'Read aloud', '3 levels'],
    icon: 'star',
    bg: colors.mint,
    fg: colors.ok,
    isNew: true,
  },
];

const TIPS: Record<string, string> = {
  letters: 'Your reading path starts with letters. Word Train has letters on its cars, just for you!',
  'own-words': 'You have some words that are still tricky. Balloon Pop uses your own tricky words!',
  'first-words': 'You are reading your first words. Word Train is a great place to practise them!',
  sentences: 'You are reading sentences. Try Sentence Stars!',
};

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
  const tip = info?.reason ? TIPS[info.reason] ?? null : null;

  return (
    <Screen withTabBar>
      <H1>Practice Games</H1>
      <Small>Free play. No points, just for fun!</Small>

      {error ? (
        <View style={{ gap: s(12), marginTop: s(14) }}>
          <Notice text={error} />
          <Btn label="Try Again" onPress={load} />
        </View>
      ) : null}

      {tip ? (
        <View style={styles.tip} accessibilityLiveRegion="polite">
          <Icon name="lightbulb" size={s(22)} color={colors.orangeDark} />
          <Text maxFontSizeMultiplier={1.2} style={styles.tipText}>
            {tip}
          </Text>
        </View>
      ) : !info && !error ? (
        <Busy style={{ marginTop: s(10) }} />
      ) : null}

      <View style={{ gap: s(14), marginTop: s(14) }}>
        {CARDS.map((card) => (
          <View key={card.key} style={[styles.card, { backgroundColor: card.bg }]}>
            <View style={styles.head}>
              <View style={styles.iconTile}>
                <Icon name={card.icon} size={s(30)} color={card.fg} />
              </View>
              {pick === card.key ? (
                <View style={styles.ribbon}>
                  <Icon name="star" size={s(16)} color={colors.white} />
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
            <Btn label="Play" tone={card.key === 'word-train' ? 'blue' : 'orange'} icon="game-controller" onPress={() => onPlay(card.key)} />
          </View>
        ))}
      </View>

      <Small style={{ marginTop: s(14) }}>Every game is reading out loud. What you read comes first from the words you missed in your readings, then from the list for your reading step.</Small>
      <Small style={{ marginTop: s(6) }}>Games are practice. They never change your points, your streak or your level, and what you read in a game is not graded.</Small>
    </Screen>
  );
}

const styles = StyleSheet.create({
  tip: { flexDirection: 'row', alignItems: 'center', gap: s(10), backgroundColor: colors.orangeSoft, borderRadius: s(14), padding: s(12), marginTop: s(12), borderWidth: 1, borderColor: '#f3d6bb' },
  tipText: { flex: 1, fontFamily: fonts.ui, fontSize: s(16), lineHeight: s(22), color: colors.ink },
  card: { borderRadius: s(22), padding: s(16), gap: s(8), borderBottomWidth: s(5), borderBottomColor: 'rgba(23,50,77,0.08)' },
  head: { flexDirection: 'row', alignItems: 'center', justifyContent: 'space-between' },
  iconTile: { width: s(52), height: s(52), borderRadius: s(16), backgroundColor: colors.white, alignItems: 'center', justifyContent: 'center' },
  ribbon: { flexDirection: 'row', alignItems: 'center', gap: s(4), backgroundColor: colors.orange, borderRadius: s(99), paddingHorizontal: s(10), minHeight: s(28) },
  ribbonText: { fontFamily: fonts.uiBold, fontSize: s(13), color: colors.white },
  title: { fontFamily: fonts.displayMedium, fontSize: s(24), lineHeight: s(30), color: colors.ink },
  text: { fontFamily: fonts.uiRegular, fontSize: s(17), lineHeight: s(23), color: colors.ink },
  chips: { flexDirection: 'row', flexWrap: 'wrap', gap: s(6), marginBottom: s(4) },
  chip: { backgroundColor: 'rgba(255,255,255,0.8)', borderRadius: s(99), paddingHorizontal: s(10), minHeight: s(26), justifyContent: 'center' },
  chipText: { fontFamily: fonts.uiBold, fontSize: s(13), color: colors.slate },
});
