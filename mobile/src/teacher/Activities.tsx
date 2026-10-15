import { useEffect, useRef, useState } from 'react';
import { Pressable, StyleSheet, Text, View } from 'react-native';
import * as api from '../api';
import { Field, Segmented } from '../fields';
import { Btn, Notice, P, Screen, Small } from '../ui';
import { Banner, ListRow, Loading, TonePill, TopBar, useLoad, type Tone } from './kit';
import { colors, fonts, size, space } from '../theme';
import { s } from '../scale';

export const LEVEL_TONE: Record<string, Tone> = { Easy: 'green', Medium: 'amber', Hard: 'red' };

type Props = {
  /** Only an Active teacher may place, assign or share. A Pending one can look and ask for new activities with the free credits. */
  active: boolean;
  /** Bumped by the parent after something changed elsewhere, so the list fetches again. */
  refreshKey: number;
  onOpen: (id: number) => void;
  onGenerate: () => void;
  /** Tells the parent how many credits are left, for the Generate button. */
  onLoaded?: (list: api.ActivityList) => void;
};

/**
 * Activities: the review list (drafts wait here until you put each one in Easy, Medium or Hard, or reject it), the approved
 * ones, and the rejected ones. The same moves as the website's board, with buttons instead of dragging, and the same Undo.
 */
export function Activities({ active, refreshKey, onOpen, onGenerate, onLoaded }: Props) {
  const [status, setStatus] = useState<api.ActivityStatus | undefined>(undefined);
  const [tier, setTier] = useState<api.Level | null>(null);
  const [typed, setTyped] = useState('');
  const [q, setQ] = useState('');
  // What the last move said. `undo` is true only for a reject (a placement in a level is moved again with the level buttons, as on the website).
  const [undo, setUndo] = useState<{ id: number; message: string; canUndo: boolean } | null>(null);
  const [busyId, setBusyId] = useState<number | null>(null);
  const [error, setError] = useState<string | null>(null);

  // The search waits for a short pause in typing, so it does not ask the server for every letter.
  useEffect(() => {
    const t = setTimeout(() => setQ(typed), 350);
    return () => clearTimeout(t);
  }, [typed]);

  const { data, error: loadError, reload } = useLoad(() => api.teacherActivities({ status, tier, q }), [status, tier, q, refreshKey]);

  useEffect(() => {
    if (data) onLoaded?.(data);
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [data]);

  // The Undo bar goes away by itself.
  useEffect(() => {
    if (!undo) return;
    const t = setTimeout(() => setUndo(null), 12000);
    return () => clearTimeout(t);
  }, [undo]);

  const current = status ?? data?.status ?? 'Draft';
  const total = data ? data.counts.Draft + data.counts.Approved + data.counts.Rejected : 0;

  async function place(row: api.ActivityRow, level: api.Level | 'reject') {
    if (busyId !== null) return;
    setBusyId(row.id);
    setError(null);
    try {
      const done = await api.placeActivity(row.id, level);
      setUndo({ id: row.id, message: done.message, canUndo: done.undo });
      reload();
    } catch (e) {
      setError(e instanceof Error ? e.message : 'Something went wrong. Please try again.');
    } finally {
      setBusyId(null);
    }
  }

  async function takeBack() {
    if (!undo) return;
    const id = undo.id;
    setUndo(null);
    try {
      await api.restoreActivity(id);
      reload();
    } catch (e) {
      setError(e instanceof Error ? e.message : 'Something went wrong. Please try again.');
    }
  }

  return (
    <Screen withTabBar>
      <TopBar
        title="Activities"
        sub={data ? `${data.credits} free ${data.credits === 1 ? 'credit' : 'credits'} left for new activities` : undefined}
      />

      <View style={{ gap: space[12] }}>
        {data?.generation ? <GenerationNotice generation={data.generation} onChanged={reload} /> : null}

        <Btn label="Write new activities" icon="sparkle" onPress={onGenerate} />

        {!data ? (
          <Loading error={loadError} onRetry={reload} text="Getting your activities" />
        ) : (
          <>
            <Segmented
              options={[
                { key: 'Draft', label: `Review ${data.counts.Draft}` },
                { key: 'Approved', label: 'Approved' },
                { key: 'Rejected', label: 'Rejected' },
              ]}
              value={current}
              onChange={(k) => {
                setStatus(k);
                setUndo(null);
              }}
            />

            {total > 5 ? (
              <View style={{ gap: space[8] }}>
                <Field label="Search" value={typed} onChangeText={setTyped} placeholder="Title, grade or type" returnKeyType="search" />
                <Segmented
                  options={[
                    { key: 'All', label: 'All' },
                    { key: 'Easy', label: 'Easy' },
                    { key: 'Medium', label: 'Medium' },
                    { key: 'Hard', label: 'Hard' },
                  ]}
                  value={tier ?? 'All'}
                  onChange={(o) => setTier(o === 'All' ? null : (o as api.Level))}
                />
              </View>
            ) : null}

            {data.locked ? (
              <Banner tone="amber" icon="info" title="Placing and giving out open once you are approved" text="You can read your activities and write new ones with your free credits now." />
            ) : null}

            {undo ? (
              <Banner
                tone="blue"
                icon="check-circle"
                title={undo.message}
                action={
                  undo.canUndo ? (
                    <Pressable accessibilityRole="button" accessibilityLabel="Undo" onPress={takeBack} style={st.undo}>
                      <Text style={st.undoText}>Undo</Text>
                    </Pressable>
                  ) : undefined
                }
              />
            ) : null}
            {error ? <Notice text={error} /> : null}

            {data.activities.length === 0 ? (
              <Empty status={current} filtered={!!tier || q.trim() !== ''} />
            ) : (
              data.activities.map((a) =>
                current === 'Draft' ? (
                  <DraftCard key={a.id} row={a} locked={data.locked} busy={busyId === a.id} onOpen={() => onOpen(a.id)} onPlace={(level) => place(a, level)} />
                ) : (
                  <ListRow
                    key={a.id}
                    title={a.title}
                    sub={[a.grade, a.type, a.words ? `${a.words} words` : null, a.assigned ? `given ${a.assigned}` : null, a.shared ? 'shared' : null].filter(Boolean).join(' · ')}
                    right={a.level ? <TonePill text={a.level} tone={LEVEL_TONE[a.level]} /> : undefined}
                    onPress={() => onOpen(a.id)}
                  />
                ),
              )
            )}
          </>
        )}
      </View>
    </Screen>
  );
}

function Empty({ status, filtered }: { status: api.ActivityStatus; filtered: boolean }) {
  const text = filtered
    ? 'No activities match. Clear the search or the level.'
    : status === 'Draft'
      ? 'Nothing to review. Write new activities and they will wait here.'
      : status === 'Approved'
        ? 'No approved activities yet. Place a draft in a level to approve it.'
        : 'Nothing has been rejected.';
  return <P style={{ textAlign: 'center', paddingVertical: space[16] }}>{text}</P>;
}

/** A draft waiting for you: the words, then one tap to put it in a level. The level the AI suggested is marked, but it is your call. */
function DraftCard({ row, locked, busy, onOpen, onPlace }: { row: api.ActivityRow; locked: boolean; busy: boolean; onOpen: () => void; onPlace: (level: api.Level | 'reject') => void }) {
  return (
    <View style={st.card}>
      <Pressable accessibilityRole="button" accessibilityLabel={`Open ${row.title}`} onPress={onOpen} style={{ gap: space[4] }}>
        <Text maxFontSizeMultiplier={1.25} style={st.cardTitle} numberOfLines={2}>
          {row.title}
        </Text>
        <Small>{[row.grade, row.type, row.words ? `${row.words} words` : null].filter(Boolean).join(' · ')}</Small>
        {row.passage ? (
          <View style={st.passageBox}>
            <Text maxFontSizeMultiplier={1.25} style={st.passage} numberOfLines={3}>
              {row.passage}
            </Text>
          </View>
        ) : null}
      </Pressable>
      {locked ? null : (
        <>
          <Small>{row.aiLevel ? `The AI suggests ${row.aiLevel}. Put it where you think it belongs.` : 'Put it in the level where it belongs.'}</Small>
          <View style={st.levels}>
            {(['Easy', 'Medium', 'Hard'] as api.Level[]).map((level) => (
              <LevelButton key={level} label={level} suggested={row.aiLevel === level} disabled={busy} onPress={() => onPlace(level)} tone={LEVEL_TONE[level]} />
            ))}
          </View>
          <Pressable accessibilityRole="button" accessibilityLabel={`Reject ${row.title}`} disabled={busy} onPress={() => onPlace('reject')} style={({ pressed }) => [st.reject, pressed && { opacity: 0.6 }, busy && { opacity: 0.5 }]}>
            <Text maxFontSizeMultiplier={1.2} style={st.rejectText}>
              Reject
            </Text>
          </Pressable>
        </>
      )}
    </View>
  );
}

const LEVEL_COLORS: Record<string, { bg: string; fg: string }> = {
  green: { bg: colors.mint, fg: colors.ok },
  amber: { bg: colors.gold, fg: colors.warn },
  red: { bg: colors.badSoft, fg: colors.bad },
  grey: { bg: colors.unsureSoft, fg: colors.slate },
};

function LevelButton({ label, tone, suggested, disabled, onPress }: { label: string; tone: Tone; suggested?: boolean; disabled?: boolean; onPress: () => void }) {
  const c = LEVEL_COLORS[tone] ?? LEVEL_COLORS.grey;
  return (
    <Pressable
      accessibilityRole="button"
      accessibilityLabel={label === 'Reject' ? 'Reject this activity' : `Put in ${label}${suggested ? ', the AI suggestion' : ''}`}
      disabled={disabled}
      onPress={onPress}
      style={({ pressed }) => [st.level, { backgroundColor: c.bg, borderColor: suggested ? c.fg : 'transparent' }, pressed && { opacity: 0.7 }, disabled && { opacity: 0.5 }]}
    >
      <Text maxFontSizeMultiplier={1.15} style={[st.levelText, { color: c.fg }]} numberOfLines={1}>
        {label}
      </Text>
    </Pressable>
  );
}

// ------------------------------------------------------------------ the request being written

/** "1m 05s" from a number of seconds. */
const clock = (sec: number) => `${Math.floor(sec / 60)}m ${String(sec % 60).padStart(2, '0')}s`;

/**
 * The request being written in the background. Blue while it works (with a timer, and it keeps working if you leave the screen
 * or the app), green when it is done, red when it failed ("Nothing was charged"). The server does the writing; this only follows it.
 */
export function GenerationNotice({ generation, onChanged }: { generation: api.GenerationStatus; onChanged: () => void }) {
  const [now, setNow] = useState(() => Math.floor(Date.now() / 1000));
  const [status, setStatus] = useState(generation);
  const done = useRef(false);

  useEffect(() => {
    setStatus(generation);
    done.current = false;
  }, [generation]);

  // Follows a running request: one question every 4 seconds, and the list is fetched again the moment it ends.
  useEffect(() => {
    if (!status.active) return;
    const tick = setInterval(() => setNow(Math.floor(Date.now() / 1000)), 1000);
    const poll = setInterval(async () => {
      try {
        const next = await api.generationStatus(status.id);
        setStatus(next);
        if (!next.active && !done.current) {
          done.current = true;
          onChanged();
        }
      } catch {
        // A dropped connection is not a failed request: keep following.
      }
    }, 4000);
    return () => {
      clearInterval(tick);
      clearInterval(poll);
    };
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [status.active, status.id]);

  if (status.active) {
    const elapsed = status.startedAt ? Math.max(0, now - status.startedAt) : 0;
    return (
      <Banner
        tone="blue"
        icon="sparkle"
        title={`Writing ${status.total} ${status.total === 1 ? 'activity' : 'activities'} (${status.summary})`}
        text={`${clock(elapsed)} so far. This takes about 2 minutes. You can leave this screen. It keeps working.`}
      />
    );
  }

  const ok = status.status === 'Done';
  return (
    <View style={{ gap: space[8] }}>
      <Banner
        tone={ok ? 'blue' : 'red'}
        icon={ok ? 'check-circle' : 'warning-circle'}
        title={ok ? 'Your new activities are ready' : 'The activities could not be written'}
        text={status.message ?? (ok ? 'They are waiting in Review.' : 'Nothing was charged.')}
      />
      <Btn
        label={ok ? 'Got it' : 'Dismiss'}
        tone="ghost"
        compact
        onPress={async () => {
          try {
            await api.dismissGeneration(status.id);
          } catch {
            // If this fails the notice simply shows again next time.
          }
          onChanged();
        }}
      />
    </View>
  );
}

const st = StyleSheet.create({
  card: { backgroundColor: colors.white, borderRadius: s(18), borderWidth: 2, borderColor: colors.line, borderBottomWidth: s(5), padding: space[14], gap: space[8] },
  cardTitle: { fontFamily: fonts.display, fontSize: s(20), lineHeight: s(26), color: colors.ink },
  passageBox: { backgroundColor: colors.sky, borderRadius: s(12), padding: space[10] },
  passage: { fontFamily: fonts.read, fontSize: s(15), lineHeight: s(22), color: colors.ink },
  levels: { flexDirection: 'row', gap: space[6] },
  level: { flex: 1, minWidth: 0, minHeight: size.touch, borderRadius: s(14), borderWidth: 2, alignItems: 'center', justifyContent: 'center', paddingHorizontal: space[4] },
  levelText: { fontFamily: fonts.uiBold, fontSize: s(16) },
  reject: { minHeight: size.touch, alignItems: 'center', justifyContent: 'center' },
  rejectText: { fontFamily: fonts.uiBold, fontSize: s(16), color: colors.bad, textDecorationLine: 'underline' },
  undo: { minHeight: size.touch, minWidth: size.touch, alignItems: 'center', justifyContent: 'center', paddingHorizontal: space[10] },
  undoText: { fontFamily: fonts.uiBold, fontSize: s(17), color: colors.blueDark, textDecorationLine: 'underline' },
});
