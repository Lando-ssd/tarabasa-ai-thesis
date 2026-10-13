import { useEffect, useRef, useState } from 'react';
import { AccessibilityInfo, Animated, Easing, Pressable, StyleSheet, Text, View, type LayoutChangeEvent } from 'react-native';
import Svg, { Circle, Path } from 'react-native-svg';
import type { Home as HomeData } from './api';
import { Animation, Icon } from './ui';
import { colors, fonts } from './theme';
import { s } from './scale';

// The reading path: a winding trail of round buttons, in the style children already know from language apps, drawn with
// TaraBasa's own colours and faces. Each button is one of the four reading steps. Steps already left behind are green with
// a check, the child's own step is the orange star with a ring that fills as great readings are counted, and the steps
// still ahead are grey; the last one is a trophy. Under the child's own step a card names the skill and starts reading.
// Everything shown is real: the step comes from the first check and later readings, the ring from the same count the
// teacher sees (config/progression.php). It never shows a score, only "great readings so far".

const NODE = s(76);
const RING = s(96);
const OWL = s(124); // the flying owl, drawn on a 1080 square with room round it
// How far each step sits to the left (minus) or right (plus) of the middle, so the trail winds like an S.
const OFFSETS = [0, s(52), 0, -s(52)];

const DONE = { bg: colors.ok, lip: '#1f6a41', icon: colors.white };
const NOW = { bg: colors.orange, lip: colors.orangeDark, icon: colors.white };
const AHEAD = { bg: '#e3eaf1', lip: '#c9d6e2', icon: '#9aa9b8' };

type Progress = { have: number; need: number; atTop: boolean };

export function ReadingPath({
  path,
  progress,
  onStart,
  onActionBottom,
}: {
  path: HomeData['readingPath'];
  progress?: Progress;
  onStart: () => void;
  /** Called once the layout is known, with how far down (from the top of the path) the Start button ends. */
  onActionBottom?: (y: number) => void;
}) {
  const marked = path.find((p) => p.isCurrent)?.step;
  const current = marked ?? 1;
  const total = path.length;
  const here = path.find((p) => p.step === current) ?? path[0];
  const calm = useCalmMotion();
  const seen = useRef({ rows: -1, row: -1, card: -1, cardHeight: -1 });
  const report = () => {
    const v = seen.current;
    if (v.rows >= 0 && v.row >= 0 && v.card >= 0 && v.cardHeight >= 0) onActionBottom?.(v.rows + v.row + v.card + v.cardHeight);
  };

  return (
    <View>
      <View style={styles.banner} accessible accessibilityLabel={`Step ${current} of ${total}, ${here?.name}. ${here?.blurb ?? ''}`}>
        <Text maxFontSizeMultiplier={1.15} style={styles.bannerLabel}>
          STEP {current} OF {total}
        </Text>
        <Text maxFontSizeMultiplier={1.15} style={styles.bannerTitle}>
          {here?.name}
        </Text>
        {here?.blurb ? (
          <Text maxFontSizeMultiplier={1.2} style={styles.bannerBlurb}>
            {here.blurb}
          </Text>
        ) : null}
      </View>

      <View style={{ paddingTop: s(18) }} onLayout={(e) => { seen.current.rows = e.nativeEvent.layout.y; report(); }}>
        {path.map((node, i) => {
          const done = node.step < current;
          const now = node.step === current;
          const last = i === total - 1;
          const offset = OFFSETS[i % OFFSETS.length];
          const next = OFFSETS[(i + 1) % OFFSETS.length];
          return (
            <View
              key={node.step}
              style={styles.row}
              onLayout={now ? (e) => { seen.current.row = e.nativeEvent.layout.y; report(); } : undefined}
            >
              <View style={{ alignItems: 'center', transform: [{ translateX: offset }] }}>
                <Node state={done ? 'done' : now ? 'now' : 'ahead'} trophy={last} progress={now ? progress : undefined} calm={calm} onPress={onStart} label={labelFor(node, done, now, progress)} />
                <Text maxFontSizeMultiplier={1.2} style={[styles.name, now && { color: colors.orangeDark }, !done && !now && { color: colors.slate }]}>
                  {node.name}
                </Text>
              </View>

              {now ? <FlyingOwl side={offset > 0 ? 'left' : 'right'} calm={calm} /> : null}

              {now ? (
                <StartCard
                  skill={node.skill}
                  progress={progress}
                  offset={offset}
                  onStart={onStart}
                  onLayout={(e) => {
                    seen.current.card = e.nativeEvent.layout.y;
                    seen.current.cardHeight = e.nativeEvent.layout.height;
                    report();
                  }}
                />
              ) : null}

              {!last ? <Dots from={offset} to={next} lit={done} /> : null}
            </View>
          );
        })}
      </View>
    </View>
  );
}

function labelFor(node: { step: number; name: string }, done: boolean, now: boolean, progress?: Progress) {
  if (done) return `Step ${node.step}, ${node.name}, done`;
  if (!now) return `Step ${node.step}, ${node.name}, still ahead`;
  const count = progress && !progress.atTop ? ` ${progress.have} of ${progress.need} great readings so far.` : '';
  return `Step ${node.step}, ${node.name}, you are here.${count} Tap to start reading.`;
}

/**
 * Tara the owl, flying beside the child's own step: the same flapping owl the website's road uses, hovering up and down a
 * little. She hangs on the outer side, level with the button. Nothing moves for children who asked for less motion.
 */
function FlyingOwl({ side, calm }: { side: 'left' | 'right'; calm: boolean }) {
  const hover = useRef(new Animated.Value(0)).current;
  useEffect(() => {
    if (calm) return;
    const loop = Animated.loop(
      Animated.sequence([
        Animated.timing(hover, { toValue: 1, duration: 1500, easing: Easing.inOut(Easing.sin), useNativeDriver: true }),
        Animated.timing(hover, { toValue: 0, duration: 1500, easing: Easing.inOut(Easing.sin), useNativeDriver: true }),
      ]),
    );
    loop.start();
    return () => loop.stop();
  }, [calm, hover]);
  return (
    <Animated.View
      style={[styles.owl, side === 'left' ? { left: s(-16) } : { right: s(-16) }, { transform: [{ translateY: hover.interpolate({ inputRange: [0, 1], outputRange: [-5, 5] }) }] }]}
      pointerEvents="none"
    >
      <Animation name="bird" px={OWL} />
    </Animated.View>
  );
}

/** One round button on the trail. */
function Node({ state, trophy, progress, calm, onPress, label }: { state: 'done' | 'now' | 'ahead'; trophy: boolean; progress?: Progress; calm: boolean; onPress: () => void; label: string }) {
  const palette = state === 'done' ? DONE : state === 'now' ? NOW : AHEAD;
  const icon = state === 'done' ? 'check' : state === 'now' ? 'star' : trophy ? 'trophy' : 'lock-simple';
  const pulse = useRef(new Animated.Value(0)).current;

  // The child's own step breathes very gently, so the eye lands on it. Nothing moves for children who asked for less motion.
  useEffect(() => {
    if (state !== 'now' || calm) return;
    const loop = Animated.loop(
      Animated.sequence([
        Animated.timing(pulse, { toValue: 1, duration: 1100, easing: Easing.inOut(Easing.sin), useNativeDriver: true }),
        Animated.timing(pulse, { toValue: 0, duration: 1100, easing: Easing.inOut(Easing.sin), useNativeDriver: true }),
      ]),
    );
    loop.start();
    return () => loop.stop();
  }, [state, calm, pulse]);

  const body = (pressed: boolean) => {
    const transform: ({ scale: Animated.AnimatedInterpolation<number> } | { translateY: number })[] = [];
    if (state === 'now' && !calm) transform.push({ scale: pulse.interpolate({ inputRange: [0, 1], outputRange: [1, 1.05] }) });
    if (pressed) transform.push({ translateY: 4 });
    return (
      <Animated.View style={[styles.node, { backgroundColor: palette.bg, borderBottomColor: palette.lip }, pressed && { borderBottomWidth: s(3) }, transform.length > 0 && { transform }]}>
        {state === 'done' ? <CheckMark /> : <Icon name={icon} size={state === 'ahead' ? 32 : 38} color={palette.icon} />}
      </Animated.View>
    );
  };

  return (
    <View style={styles.nodeBox}>
      {state === 'now' && progress ? <Ring progress={progress} /> : null}
      {state === 'now' ? (
        <Pressable accessibilityRole="button" accessibilityLabel={label} onPress={onPress} hitSlop={8}>
          {({ pressed }) => body(pressed)}
        </Pressable>
      ) : (
        <View accessible accessibilityLabel={label}>
          {body(false)}
        </View>
      )}
    </View>
  );
}

/** A plain, bold check mark (the icon set only has a checkbox and a circle), so a finished step reads at a glance. */
function CheckMark() {
  return (
    <Svg width={s(40)} height={s(40)} viewBox="0 0 40 40">
      <Path d="M8 21 L17 30 L33 11" stroke={colors.white} strokeWidth={7} strokeLinecap="round" strokeLinejoin="round" fill="none" />
    </Svg>
  );
}

/** The ring round the child's own step: one segment for every great reading needed, filled as they are counted. */
function Ring({ progress }: { progress: Progress }) {
  const r = 44;
  const c = 2 * Math.PI * r;
  const count = Math.max(1, progress.need);
  const gap = 12;
  const seg = c / count - gap;
  return (
    <Svg width={RING} height={RING} viewBox="0 0 96 96" style={{ position: 'absolute', transform: [{ rotate: '-90deg' }] }} pointerEvents="none">
      {progress.atTop ? (
        <Circle cx={48} cy={48} r={r} stroke={colors.goldDeep} strokeWidth={7} fill="none" />
      ) : (
        Array.from({ length: count }, (_, i) => (
          <Circle
            key={i}
            cx={48}
            cy={48}
            r={r}
            stroke={i < progress.have ? colors.orange : colors.sky2}
            strokeWidth={7}
            fill="none"
            strokeLinecap="round"
            strokeDasharray={`${seg} ${c - seg}`}
            strokeDashoffset={-(i * (c / count) + gap / 2)}
          />
        ))
      )}
    </Svg>
  );
}

/** The card under the child's own step: what the step practises, how far along, and the one big button. */
function StartCard({ skill, progress, offset, onStart, onLayout }: { skill: string; progress?: Progress; offset: number; onStart: () => void; onLayout?: (e: LayoutChangeEvent) => void }) {
  const left = progress ? Math.max(0, progress.need - progress.have) : 0;
  const sub = !progress
    ? 'Ready when you are.'
    : progress.atTop
      ? 'You are a Story Reader. Keep reading to stay sharp.'
      : `${progress.have} of ${progress.need} great readings so far`;
  const hint = progress && !progress.atTop && left > 0 ? `Read ${left} more really well to grow.` : null;
  return (
    <View style={styles.card} onLayout={onLayout}>
      <View style={[styles.pointer, { marginLeft: offset - 9 }]} />
      <Text maxFontSizeMultiplier={1.15} style={styles.cardTitle}>
        {skill}
      </Text>
      <Text maxFontSizeMultiplier={1.2} style={styles.cardSub}>
        {sub}
      </Text>
      {hint ? (
        <Text maxFontSizeMultiplier={1.2} style={styles.cardHint}>
          {hint}
        </Text>
      ) : null}
      <Pressable
        accessibilityRole="button"
        accessibilityLabel="Start reading"
        onPress={onStart}
        style={({ pressed }) => [styles.start, pressed && { transform: [{ translateY: 3 }], borderBottomWidth: s(2) }]}
      >
        <Icon name="book-open" size={s(24)} color={colors.orange} />
        <Text maxFontSizeMultiplier={1.15} style={styles.startText}>
          START READING
        </Text>
      </Pressable>
    </View>
  );
}

/** A few small dots that join one step to the next. They turn green once the step before has been left behind. */
function Dots({ from, to, lit }: { from: number; to: number; lit: boolean }) {
  return (
    <View style={styles.dots} pointerEvents="none">
      {[1, 2, 3].map((k) => {
        const t = k / 4;
        return <View key={k} style={[styles.dot, { top: k * 8 + 2, marginLeft: from + (to - from) * t - 4, backgroundColor: lit ? colors.ok : colors.sky2 }]} />;
      })}
    </View>
  );
}

/** True when the phone is set to reduce motion, so the gentle pulse is left out. */
function useCalmMotion() {
  const [calm, setCalm] = useState(false);
  useEffect(() => {
    let alive = true;
    AccessibilityInfo.isReduceMotionEnabled()
      .then((on) => alive && setCalm(on))
      .catch(() => {});
    return () => {
      alive = false;
    };
  }, []);
  return calm;
}

const styles = StyleSheet.create({
  banner: { backgroundColor: colors.blue, borderBottomWidth: s(5), borderBottomColor: colors.blueDark, borderRadius: s(18), paddingHorizontal: s(16), paddingVertical: s(12), gap: s(1) },
  bannerLabel: { fontFamily: fonts.uiBold, fontSize: s(13), letterSpacing: s(1.2), color: 'rgba(255,255,255,0.82)' },
  bannerTitle: { fontFamily: fonts.display, fontSize: s(26), lineHeight: s(32), color: colors.white },
  bannerBlurb: { fontFamily: fonts.uiRegular, fontSize: s(16), lineHeight: s(20), color: 'rgba(255,255,255,0.94)' },
  row: { alignItems: 'center' },
  nodeBox: { width: RING, height: RING, alignItems: 'center', justifyContent: 'center' },
  node: { width: NODE, height: NODE, borderRadius: NODE / 2, borderBottomWidth: s(7), alignItems: 'center', justifyContent: 'center' },
  name: { fontFamily: fonts.uiBold, fontSize: s(17), lineHeight: s(22), color: colors.ink, textAlign: 'center', marginTop: s(2) },
  owl: { position: 'absolute', top: s(-14) },
  card: { alignSelf: 'stretch', backgroundColor: colors.orange, borderBottomWidth: s(6), borderBottomColor: colors.orangeDark, borderRadius: s(18), padding: s(16), gap: s(4), marginTop: s(14), marginBottom: s(4) },
  pointer: { position: 'absolute', top: s(-9), left: '50%', width: s(18), height: s(18), borderRadius: s(4), backgroundColor: colors.orange, transform: [{ rotate: '45deg' }] },
  cardTitle: { fontFamily: fonts.display, fontSize: s(22), lineHeight: s(28), color: colors.white },
  cardSub: { fontFamily: fonts.uiBold, fontSize: s(17), lineHeight: s(22), color: colors.white },
  cardHint: { fontFamily: fonts.uiRegular, fontSize: s(16), lineHeight: s(21), color: 'rgba(255,255,255,0.94)' },
  start: { marginTop: s(10), minHeight: s(54), borderRadius: s(14), backgroundColor: colors.white, borderBottomWidth: s(5), borderBottomColor: '#f0d6bd', flexDirection: 'row', alignItems: 'center', justifyContent: 'center', gap: s(10) },
  startText: { fontFamily: fonts.uiBold, fontSize: s(19), letterSpacing: s(1), color: colors.orange },
  dots: { alignSelf: 'stretch', height: s(38) },
  dot: { position: 'absolute', left: '50%', width: s(8), height: s(8), borderRadius: s(4) },
});
