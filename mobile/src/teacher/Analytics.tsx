import { useState } from 'react';
import { StyleSheet, Text, View } from 'react-native';
import * as api from '../api';
import { Chips, Segmented } from '../fields';
import { H2, P, Screen, Small } from '../ui';
import { ListRow, Loading, TonePill, Tile, TopBar, useLoad, type Tone } from './kit';
import { colors, fonts, space } from '../theme';
import { s } from '../scale';

type Period = 'week' | 'month' | 'all';

const LEVEL_COLOR: Record<string, string> = {
  independent: colors.ok,
  instructional: colors.blue,
  frustration: colors.warn,
  non: colors.bad,
  unchecked: colors.unsure,
};

/**
 * Analytics: the same overview as the website, for this week, this month or the last six months, for every class or one. Reading
 * levels use the Department of Education's own words (Phil-IRI): Independent, Instructional, Frustration and Non-reader.
 */
export function Analytics({ onBack }: { onBack: () => void }) {
  const [period, setPeriod] = useState<Period>('week');
  const [classId, setClassId] = useState<number | null>(null);
  const { data: o, error, reload } = useLoad(() => api.teacherAnalytics(period, classId), [period, classId]);

  const className = (c: { id: number; name: string }) => c.name;
  const totalLevels = o ? o.levels.reduce((n, l) => n + l.count, 0) : 0;
  const missed = o ? Object.entries(o.missedWords) : [];

  return (
    <Screen withTabBar>
      <TopBar title="Analytics" sub={o?.scope} onBack={onBack} />

      <View style={{ gap: space[14] }}>
        <Segmented
          options={[
            { key: 'week', label: 'This week' },
            { key: 'month', label: 'This month' },
            { key: 'all', label: '6 months' },
          ]}
          value={period}
          onChange={setPeriod}
        />
        {o && o.classes.length > 1 ? (
          <Chips
            label="Class"
            options={['All classes', ...o.classes.map(className)]}
            picked={[classId === null ? 'All classes' : (o.classes.find((c) => c.id === classId)?.name ?? 'All classes')]}
            onToggle={(name) => setClassId(name === 'All classes' ? null : (o.classes.find((c) => c.name === name)?.id ?? null))}
          />
        ) : null}

        {!o ? (
          <Loading error={error} onRetry={reload} text="Working out the numbers" />
        ) : (
          <>
            <View style={{ flexDirection: 'row', gap: space[10] }}>
              <Tile value={o.learners} label="Learners" />
              <Tile value={o.readings} label="Readings" />
              <Tile value={o.average === null ? 'None' : `${o.average}%`} label="Average" />
              <Tile value={o.needSupport} label="Need support" />
            </View>

            {o.change !== null ? (
              <P>
                The average is {o.change === 0 ? 'the same as' : `${Math.abs(o.change)} ${Math.abs(o.change) === 1 ? 'point' : 'points'} ${o.change > 0 ? 'up from' : 'down from'}`} the period before.
              </P>
            ) : null}
            <Small>
              {o.bySource.teacher} {o.bySource.teacher === 1 ? 'reading' : 'readings'} from activities you gave. {o.bySource.parent} {o.bySource.parent === 1 ? 'reading' : 'readings'} started by parents.
            </Small>

            {o.learners === 0 ? <P style={{ textAlign: 'center', paddingVertical: space[16] }}>No learners yet. Add learners to a class and their numbers will show here.</P> : null}

            {o.learners > 0 ? (
              <View style={{ gap: space[8] }}>
                <H2>Reading levels</H2>
                {o.levels.map((l) => (
                  <View key={l.key} style={st.levelRow}>
                    <View style={st.levelTop}>
                      <Text maxFontSizeMultiplier={1.25} style={st.levelName}>
                        {l.label}
                      </Text>
                      <Text maxFontSizeMultiplier={1.25} style={st.levelCount}>
                        {l.count}
                      </Text>
                    </View>
                    <View style={st.track}>
                      <View style={[st.fill, { width: `${totalLevels > 0 ? (l.count / totalLevels) * 100 : 0}%`, backgroundColor: LEVEL_COLOR[l.key] ?? colors.blue }]} />
                    </View>
                  </View>
                ))}
                <Small>Levels come from each child's first check and later readings, in the Phil-IRI words of DepEd Order 14, 2018. {o.checked} of {o.learners} have had the first check.</Small>
              </View>
            ) : null}

            {missed.length > 0 ? (
              <View style={{ gap: space[8] }}>
                <H2>Words missed most</H2>
                <View style={st.words}>
                  {missed.map(([word, n]) => (
                    <TonePill key={word} text={`${word} ${n}`} tone="amber" />
                  ))}
                </View>
              </View>
            ) : null}

            {o.patterns.length > 0 ? (
              <View style={{ gap: space[8] }}>
                <H2>What children find hard</H2>
                {o.patterns.map((p) => (
                  <ListRow key={p.key} title={p.label} sub={`${p.learners} ${p.learners === 1 ? 'child' : 'children'}, ${p.misses} ${p.misses === 1 ? 'miss' : 'misses'}`} />
                ))}
              </View>
            ) : null}

            {o.assignments.length > 0 ? (
              <View style={{ gap: space[8] }}>
                <H2>Activities you gave</H2>
                {o.assignments.map((a, i) => (
                  <ListRow
                    key={`${a.title}-${i}`}
                    title={a.title}
                    sub={`For ${a.for}. ${a.done} of ${a.total} have read it${a.average !== null ? `, average ${a.average}%` : ''}.`}
                    right={<TonePill text={`${a.done}/${a.total}`} tone={(a.done >= a.total && a.total > 0 ? 'green' : a.done === 0 ? 'grey' : 'blue') as Tone} />}
                  />
                ))}
              </View>
            ) : null}
          </>
        )}
      </View>
    </Screen>
  );
}

const st = StyleSheet.create({
  levelRow: { gap: space[4] },
  levelTop: { flexDirection: 'row', justifyContent: 'space-between', alignItems: 'baseline' },
  levelName: { fontFamily: fonts.uiBold, fontSize: s(17), color: colors.ink },
  levelCount: { fontFamily: fonts.display, fontSize: s(20), color: colors.ink },
  track: { height: s(12), borderRadius: s(6), backgroundColor: colors.unsureSoft, overflow: 'hidden' },
  fill: { height: '100%', borderRadius: s(6) },
  words: { flexDirection: 'row', flexWrap: 'wrap', gap: space[6] },
});
