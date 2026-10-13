import { useCallback, useEffect, useState } from 'react';
import { Pressable, StyleSheet, Text, View } from 'react-native';
import * as api from './api';
import { Animation, Btn, Busy, H1, Icon, Notice, P, Screen, Small } from './ui';
import { colors, fonts } from './theme';
import { s } from './scale';

// A soft cover colour for each book, so a shelf of many books is easy to tell apart at a glance.
const COVERS = [
  { bg: colors.sky2, fg: colors.blue },
  { bg: colors.mint, fg: colors.ok },
  { bg: colors.gold, fg: colors.warn },
  { bg: colors.purpleSoft, fg: colors.purple },
  { bg: '#ffe4cf', fg: colors.orangeDark },
];

function when(iso: string | null) {
  if (!iso) return null;
  const date = new Date(iso);
  return Number.isNaN(date.getTime()) ? null : date.toLocaleDateString(undefined, { day: 'numeric', month: 'short' });
}

/**
 * My Shelf: every story the child has really read, newest first, with how many times and when. Tap one to read it again
 * for fun. A re-read is free: nothing is saved, so it never changes points, streak or level.
 */
export function Shelf({ onOpen, onFindStory }: { onOpen: (id: number) => void; onFindStory: () => void }) {
  const [books, setBooks] = useState<api.Book[] | null>(null);
  const [error, setError] = useState<string | null>(null);

  const load = useCallback(async () => {
    setError(null);
    try {
      setBooks((await api.bookshelf()).books);
    } catch (e) {
      setError(e instanceof Error ? e.message : 'Something went wrong. Please try again.');
    }
  }, []);

  useEffect(() => {
    load();
  }, [load]);

  return (
    <Screen withTabBar>
      <H1>My Shelf</H1>
      {books && books.length > 0 ? <Small>{books.length === 1 ? '1 story you have read' : `${books.length} stories you have read`}</Small> : null}

      {error ? (
        <View style={{ gap: s(12), marginTop: s(16) }}>
          <Notice text={error} />
          <Btn label="Try Again" onPress={load} />
        </View>
      ) : !books ? (
        <Busy text="Getting your shelf" style={{ marginTop: s(20) }} />
      ) : books.length === 0 ? (
        <View style={styles.empty}>
          <Animation name="head" px={s(130)} />
          <P style={{ textAlign: 'center' }}>Stories you read will live here. Read your first story and it will land on your shelf.</P>
          <Btn label="Find a Story" icon="book-open" onPress={onFindStory} style={{ alignSelf: 'stretch' }} />
        </View>
      ) : (
        <View style={{ gap: s(12), marginTop: s(14) }}>
          {books.map((book, i) => (
            <Book key={book.id} book={book} cover={COVERS[i % COVERS.length]} onPress={() => onOpen(book.id)} />
          ))}
        </View>
      )}
    </Screen>
  );
}

function Book({ book, cover, onPress }: { book: api.Book; cover: (typeof COVERS)[number]; onPress: () => void }) {
  const last = when(book.lastRead);
  const times = book.timesRead === 1 ? 'Read 1 time' : `Read ${book.timesRead} times`;
  return (
    <Pressable
      accessibilityRole="button"
      accessibilityLabel={`${book.title}. ${times}${last ? `, last on ${last}` : ''}. Tap to read it again.`}
      onPress={onPress}
      style={({ pressed }) => [styles.card, pressed && { transform: [{ translateY: 2 }] }]}
    >
      <View style={[styles.cover, { backgroundColor: cover.bg }]}>
        <Icon name="book" size={s(30)} color={cover.fg} />
      </View>
      <View style={{ flex: 1, gap: s(2) }}>
        <Text maxFontSizeMultiplier={1.2} style={styles.title}>
          {book.title}
        </Text>
        <Small>
          {times}
          {last ? `. Last read ${last}` : ''}
        </Small>
      </View>
      <View style={styles.again}>
        <Icon name="arrows-clockwise" size={s(22)} color={colors.orangeDark} />
      </View>
    </Pressable>
  );
}

const styles = StyleSheet.create({
  empty: { alignItems: 'center', gap: s(14), marginTop: s(24) },
  card: { flexDirection: 'row', alignItems: 'center', gap: s(12), minHeight: s(84), backgroundColor: colors.white, borderWidth: 2, borderColor: colors.line, borderRadius: s(18), padding: s(12) },
  cover: { width: s(52), height: s(60), borderRadius: s(12), alignItems: 'center', justifyContent: 'center' },
  title: { fontFamily: fonts.uiBold, fontSize: s(21), lineHeight: s(24), color: colors.ink },
  again: { width: s(44), height: s(44), borderRadius: s(22), backgroundColor: colors.orangeSoft, alignItems: 'center', justifyContent: 'center' },
});
