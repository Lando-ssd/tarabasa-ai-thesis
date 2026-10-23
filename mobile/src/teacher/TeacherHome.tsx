import { useEffect } from 'react';
import { View } from 'react-native';
import type { Account } from '../api';
import * as api from '../api';
import { AccountAvatar } from '../AccountAvatar';
import { Icon, H2, P, Screen } from '../ui';
import { Banner, Initial, ListRow, Loading, Tile, TopBar, useLoad } from './kit';
import { colors, space } from '../theme';
import { s } from '../scale';

type Props = {
  account: Account;
  /** Opens a tab by name, so a row on Home leads to the place its work lives. */
  go: (to: 'classes' | 'activities' | 'alerts' | 'more') => void;
  /** Tells the app what the server said, such as how many alerts are open (for the badge on the bottom bar). */
  onLoaded?: (home: api.TeacherHome) => void;
};

/**
 * Teacher Home: who needs support, the numbers, and what is waiting. The same facts, in the same words, as the website's Home
 * (the server works them out), in one column.
 */
export function TeacherHome({ account, go, onLoaded }: Props) {
  const t = account.teacher;
  const { data, error, reload } = useLoad(() => api.teacherHome());
  useEffect(() => {
    if (data) onLoaded?.(data);
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [data]);
  const active = t?.status === 'Active';
  const c = data?.counts;

  return (
    <Screen withTabBar>
      <TopBar title={`Hi, ${account.firstName}`} sub={data ? `Here is what needs you today. School year ${data.schoolYear.replace('-', ' to ')}` : (t?.schoolName ?? 'Teacher')} right={
          <AccountAvatar account={account} px={s(48)}>
            <Initial name={account.firstName} px={s(48)} />
          </AccountAvatar>
        } />

      <View style={{ gap: space[14] }}>
        {!active ? (
          <Banner
            tone="amber"
            icon="info"
            title={t?.status === 'Rejected' ? 'Your school details were not approved' : 'Waiting for the Admin'}
            text={
              t?.status === 'Rejected'
                ? 'Please contact the Admin.'
                : 'Your school details are being checked. You can look around now. Classes and learners open once you are approved.'
            }
          />
        ) : null}

        {!data ? (
          <Loading error={error} onRetry={reload} text="Getting your day ready" />
        ) : (
          <>
            {data.needsSupportCount > 0 ? (
              <Banner
                tone="red"
                icon="warning-circle"
                title={`${data.needsSupportCount} ${data.needsSupportCount === 1 ? 'learner needs' : 'learners need'} support`}
                text={data.needsSupport.map((n) => n.title).join('. ')}
              />
            ) : null}

            <View style={{ flexDirection: 'row', gap: space[10] }}>
              <Tile value={c!.classes} label="Classes" onPress={() => go('classes')} />
              <Tile value={c!.learners} label="Learners" onPress={() => go('classes')} />
              <Tile value={c!.approved} label="Approved" onPress={() => go('activities')} />
              <Tile value={c!.credits} label="Credits" />
            </View>

            <H2>Waiting for you</H2>
            <View style={{ gap: space[10] }}>
              <Waiting icon="note-pencil" title={count(c!.drafts, 'draft')} sub={c!.drafts ? 'to review. Place each one in the level it belongs to.' : 'Nothing to review right now.'} onPress={() => go('activities')} />
              <Waiting icon="bell" title={count(c!.alerts, 'open alert')} sub={c!.alerts ? 'Who needs support, who is ready to move up.' : 'Nobody needs you right now.'} onPress={() => go('alerts')} />
              {c!.claim > 0 ? <Waiting icon="graduation-cap" title={count(c!.claim, 'learner')} sub="Released by another teacher. You can claim them." onPress={() => go('more')} /> : null}
              <Waiting icon="sparkle" title="Generate activities" sub={`${count(c!.credits, 'free credit')} left.`} onPress={() => go('activities')} />
            </View>
            {c!.classes === 0 ? <P>You have no class this school year yet. Open Classes to make one.</P> : null}
          </>
        )}
      </View>
    </Screen>
  );
}

function Waiting({ icon, title, sub, onPress }: { icon: string; title: string; sub: string; onPress: () => void }) {
  return <ListRow lead={<Icon name={icon} size={s(28)} color={colors.blueDark} />} title={title} sub={sub} onPress={onPress} />;
}

const count = (n: number, noun: string) => `${n} ${noun}${n === 1 ? '' : 's'}`;
