import { View } from 'react-native';
import type { Account } from '../api';
import { Avatar, Btn, H1, Icon, Screen, Small } from '../ui';
import { Banner, ListRow, TonePill } from './kit';
import { colors, space } from '../theme';
import { s } from '../scale';

export type MoreTo = 'analytics' | 'promotions' | 'profile';

type Props = {
  account: Account;
  claimable?: number;
  onOpen: (to: MoreTo) => void;
  onSignOut: () => void;
};

/**
 * More: the places that are not on the bottom bar (Analytics, Promotions, My profile) and Log out, under who is signed in. The
 * status says whether the Admin has approved the school details yet, because that decides what a Pending teacher can do.
 */
export function More({ account, claimable = 0, onOpen, onSignOut }: Props) {
  const t = account.teacher;
  const full = [account.firstName, account.middleInitial ? `${account.middleInitial}.` : null, account.lastName].filter(Boolean).join(' ');
  const status = t?.status === 'Active' ? 'Approved' : t?.status === 'Rejected' ? 'Not approved' : 'Waiting for the Admin';

  return (
    <Screen withTabBar>
      <View style={{ gap: space[14] }}>
        <View style={{ flexDirection: 'row', alignItems: 'center', gap: space[12] }}>
          <Avatar name={account.firstName} px={s(64)} />
          <View style={{ flex: 1, gap: space[4] }}>
            <H1 numberOfLines={2}>{full}</H1>
            <Small>{t?.schoolName ?? 'Teacher'}</Small>
            <TonePill text={status} tone={t?.status === 'Active' ? 'green' : t?.status === 'Rejected' ? 'red' : 'amber'} />
          </View>
        </View>

        {t && t.status !== 'Active' ? (
          <Banner tone="amber" icon="info" title={t.status === 'Rejected' ? 'Your school details were not approved' : 'Waiting for the Admin'} text={t.status === 'Rejected' ? 'Please contact the Admin.' : 'You can look around. Giving out activities and moving learners open once you are approved.'} />
        ) : null}

        <View style={{ gap: space[10] }}>
          <ListRow lead={<Icon name="chart-bar" size={s(28)} color={colors.blueDark} />} title="Analytics" sub="How your classes are reading" onPress={() => onOpen('analytics')} />
          <ListRow
            lead={<Icon name="graduation-cap" size={s(28)} color={colors.blueDark} />}
            title="Promotions"
            sub="Move learners up to the next grade"
            right={claimable > 0 ? <TonePill text={`${claimable} to claim`} tone="amber" /> : undefined}
            onPress={() => onOpen('promotions')}
          />
          <ListRow lead={<Icon name="user-circle" size={s(28)} color={colors.blueDark} />} title="My profile" sub="Your details, password and grades" onPress={() => onOpen('profile')} />
        </View>

        <Btn label="Log out" tone="ghost" icon="sign-out" onPress={onSignOut} />
      </View>
    </Screen>
  );
}
