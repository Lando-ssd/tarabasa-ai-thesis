import { View } from 'react-native';
import { Animation, H1, P, Screen } from './ui';
import { s } from './scale';

/** Games and the Bookshelf are not in the first version. The tab stays, so the bar is the same as the website's. */
export function ComingSoon({ title, text }: { title: string; text: string }) {
  return (
    <Screen withTabBar scroll={false}>
      <View style={{ flex: 1, alignItems: 'center', justifyContent: 'center', gap: s(12), paddingBottom: s(40) }}>
        <Animation name="head" px={s(140)} />
        <H1 style={{ textAlign: 'center' }}>{title}</H1>
        <P style={{ textAlign: 'center' }}>{text}</P>
      </View>
    </Screen>
  );
}
