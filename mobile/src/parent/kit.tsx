import type { ReactNode } from 'react';
import { Pressable, ScrollView, StyleSheet, Text, View, type StyleProp, type ViewStyle } from 'react-native';
import type { ParentChild, ParentLevel } from '../api';
import { Avatar } from '../ui';
import { TonePill, type Tone } from '../teacher/kit';
import { colors, fonts, size, space } from '../theme';
import { s } from '../scale';

// Small pieces the Parent screens share, on top of the Teacher kit (rows, tags, loading), so a child looks the same everywhere.

/** The colour of a Phil-IRI band. The words carry the meaning; the colour only helps. */
export const BAND_TONE: Record<string, Tone> = { independent: 'green', instructional: 'blue', frustration: 'amber', non: 'blue', unchecked: 'grey' };

/**
 * The colours of the child's four steps. A step is a place on a road, not a grade, so none of them looks like a warning.
 * Plain grey is for a child who has not done the first reading check.
 */
const STEP_PALETTE: Record<number, { bg: string; fg: string }> = {
  1: { bg: '#e6f4ff', fg: '#0f5fae' },
  2: { bg: '#e3f6ef', fg: '#137a63' },
  3: { bg: '#fff0dd', fg: '#a4510a' },
  4: { bg: '#f1e8ff', fg: '#6b3fa0' },
};

/** The tag that says where a child is: the step name the child sees on their own Home ("Word Builder"). */
export function LevelPill({ level }: { level: ParentLevel }) {
  // A server from before the four steps sends the school's words in label and short, drawn the old way.
  if (level.stepNumber === undefined) {
    return <TonePill text={level.short === 'Not checked' ? 'First check not done' : level.short} tone={BAND_TONE[level.band] ?? 'grey'} />;
  }
  if (!level.stepNumber) return <TonePill text="First check not done" tone="grey" />;
  return <TonePill text={level.name ?? level.short} tone="grey" palette={STEP_PALETTE[level.stepNumber]} />;
}

/** A white card with a lip, for a block that holds coloured tags (a blue Panel would swallow a blue tag). */
export function Card({ children, style }: { children: ReactNode; style?: StyleProp<ViewStyle> }) {
  return <View style={[st.card, style]}>{children}</View>;
}

/** The picture the parent chose for the child, or the first letter if there is none. */
export function ChildFace({ child, px = s(48) }: { child: Pick<ParentChild, 'avatar' | 'firstName'>; px?: number }) {
  if (!child.avatar) return <Avatar name={child.firstName} px={px} />;
  return (
    <View style={[st.face, { width: px, height: px, borderRadius: px / 2 }]} accessible={false}>
      <Text style={{ fontSize: px * 0.55, lineHeight: px * 0.7 }}>{child.avatar}</Text>
    </View>
  );
}

/**
 * One chip for each child, in a single row that slides sideways, so a family of four never takes three rows of the screen.
 * A small red dot marks a child whose latest reading was flagged for extra support.
 */
export function ChildChips({ children, selected, onPick }: { children: ParentChild[]; selected: number | null; onPick: (id: number) => void }) {
  if (children.length < 2) return null;
  return (
    <ScrollView horizontal showsHorizontalScrollIndicator={false} accessibilityRole="tablist" style={st.scroll} contentContainerStyle={st.row}>
      {children.map((c) => {
        const on = c.id === selected;
        return (
          <Pressable
            key={c.id}
            accessibilityRole="tab"
            accessibilityState={{ selected: on }}
            accessibilityLabel={`${c.firstName}${c.flagged ? ', needs a look' : ''}`}
            onPress={() => onPick(c.id)}
            style={[st.chip, on && st.chipOn]}
          >
            <ChildFace child={c} px={s(28)} />
            <Text maxFontSizeMultiplier={1.25} style={[st.chipText, on && { color: colors.white }]}>
              {c.firstName}
            </Text>
            {c.flagged ? <View style={st.dot} /> : null}
          </Pressable>
        );
      })}
    </ScrollView>
  );
}

/** A bar that fills as far as a number has come (this week's readings toward the goal, for example). */
export function Meter({ value, max, tone = 'blue' }: { value: number; max: number; tone?: 'blue' | 'green' }) {
  const pct = max > 0 ? Math.max(0, Math.min(1, value / max)) : 0;
  return (
    <View style={st.track} accessible accessibilityRole="progressbar" accessibilityValue={{ min: 0, max, now: Math.min(value, max) }}>
      <View style={[st.fill, { width: `${pct * 100}%`, backgroundColor: tone === 'green' ? colors.ok : colors.blue }]} />
    </View>
  );
}

const st = StyleSheet.create({
  card: { backgroundColor: colors.white, borderRadius: s(18), borderWidth: 2, borderColor: colors.line, borderBottomWidth: s(5), padding: space[14], gap: space[8] },
  face: { backgroundColor: colors.sky2, alignItems: 'center', justifyContent: 'center' },
  scroll: { flexGrow: 0, marginHorizontal: -size.gutter },
  row: { gap: space[8], paddingHorizontal: size.gutter },
  chip: { flexDirection: 'row', alignItems: 'center', gap: space[6], minHeight: size.touch, paddingLeft: space[8], paddingRight: space[16], borderRadius: s(26), backgroundColor: colors.sky },
  chipOn: { backgroundColor: colors.ink },
  chipText: { fontFamily: fonts.uiBold, fontSize: s(16), color: colors.blueDark },
  dot: { width: s(10), height: s(10), borderRadius: s(5), backgroundColor: colors.bad, marginLeft: space[2] },
  track: { height: s(14), borderRadius: s(7), backgroundColor: colors.unsureSoft, overflow: 'hidden' },
  fill: { height: '100%', borderRadius: s(7) },
});
