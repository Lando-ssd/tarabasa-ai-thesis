import { useEffect, useState } from 'react';
import { View } from 'react-native';
import { Animation, Btn, H2, Notice, P, Screen, Busy } from './ui';
import { s } from './scale';

/**
 * The waiting screen while a reading is being scored. The free server can be asleep, so the first reading of the day
 * may take a minute: the screen says why, in kind words, so nobody thinks the app has frozen.
 */
export function Waiting({ title = 'Checking your reading' }: { title?: string }) {
  const [seconds, setSeconds] = useState(0);
  useEffect(() => {
    const timer = setInterval(() => setSeconds((s) => s + 1), 1000);
    return () => clearInterval(timer);
  }, []);

  const text =
    seconds < 12
      ? 'Tara is listening carefully.'
      : seconds < 40
        ? 'Thank you for waiting. Tara is still listening carefully.'
        : 'TaraBasa is waking up. This can take up to a minute. Please keep this screen open.';

  return (
    <Screen scroll={false}>
      <View style={{ flex: 1, alignItems: 'center', justifyContent: 'center', gap: s(14) }}>
        <Animation name="head" px={s(150)} />
        <H2 style={{ textAlign: 'center' }}>{title}</H2>
        <P style={{ textAlign: 'center' }}>{text}</P>
        <Busy />
      </View>
    </Screen>
  );
}

/** The reading is safe on the phone, but it could not be sent. Offer to send it again without reading it again. */
export function SendFailed({ message, onRetry, onReread }: { message: string; onRetry: () => void; onReread: () => void }) {
  return (
    <Screen
      anchor="content"
      footer={
        <View style={{ gap: s(10) }}>
          <Btn label="Send Again" onPress={onRetry} />
          <Btn label="Read It Again" tone="ghost" compact onPress={onReread} />
        </View>
      }
    >
      <View style={{ alignItems: 'center', gap: s(14) }}>
        <Animation name="head" px={s(130)} />
        <H2 style={{ textAlign: 'center' }}>We could not send it yet</H2>
        <Notice text={message} />
        <P style={{ textAlign: 'center' }}>Your reading is still on this phone. Check the internet, then tap Send Again.</P>
      </View>
    </Screen>
  );
}
