import { useState } from 'react';
import { KeyboardAvoidingView, Pressable, StyleSheet, Text, View } from 'react-native';
import { SvgXml } from 'react-native-svg';
import * as api from '../api';
import { Chips, Field } from '../fields';
import { Btn, Icon, IconBtn, Notice, P, Panel, Screen, Small } from '../ui';
import { Loading, TonePill, TopBar, useLoad } from '../teacher/kit';
import { BAND_TONE, ChildFace } from './kit';
import { colors, fonts, space } from '../theme';
import { s } from '../scale';

type ListProps = {
  refreshKey: number;
  /** What the last action said, such as a link that worked. */
  flash?: string | null;
  onOpen: (child: api.ParentChild) => void;
  onAdd: () => void;
  onLink: () => void;
};

/** Children: every child linked to this account, the ways to add one, and the card with the code the teacher scans. */
export function Children({ refreshKey, flash, onOpen, onAdd, onLink }: ListProps) {
  const { data, error, reload } = useLoad(() => api.parentChildren(), [refreshKey]);

  return (
    <Screen withTabBar>
      <TopBar title="Children" sub={data ? `${data.children.length} ${data.children.length === 1 ? 'child' : 'children'}` : undefined} />
      <View style={{ gap: space[12] }}>
        {!data ? (
          <Loading error={error} onRetry={reload} text="Getting your children" />
        ) : (
          <>
            {flash ? <Notice tone="info" text={flash} /> : null}
            {data.children.map((c) => (
              <ChildRow key={c.id} c={c} onPress={() => onOpen(c)} />
            ))}
            {data.children.length === 0 ? <P style={{ textAlign: 'center', paddingVertical: space[16] }}>You have not added a child yet.</P> : null}
            <Btn label="Add a child" icon="plus" onPress={onAdd} />
            <Btn label="Link an existing child" icon="link-simple" tone="ghost" onPress={onLink} />
          </>
        )}
      </View>
    </Screen>
  );
}

/** One child: the picture, the name and where they are, with the level tag under the name so a long name never gets squeezed. */
function ChildRow({ c, onPress }: { c: api.ParentChildRow; onPress: () => void }) {
  return (
    <Pressable accessibilityRole="button" accessibilityLabel={`${c.name}, ${c.grade ?? ''}, open the card`} onPress={onPress} style={({ pressed }) => [st.row, pressed && { opacity: 0.7 }]}>
      <ChildFace child={c} px={s(52)} />
      <View style={{ flex: 1, gap: space[4] }}>
        <Text maxFontSizeMultiplier={1.25} style={st.rowName}>
          {c.name}
        </Text>
        <Small>{[c.grade, c.className ?? 'Not in a class yet'].join(' · ')}</Small>
        <View style={st.rowTags}>
          <TonePill text={c.level.short === 'Not checked' ? 'Not checked' : c.level.short} tone={BAND_TONE[c.level.band] ?? 'grey'} />
          {c.dayStreak > 0 ? <TonePill text={`${c.dayStreak} ${c.dayStreak === 1 ? 'day' : 'days'} in a row`} tone="green" /> : null}
        </View>
      </View>
      <Icon name="caret-right" size={s(20)} color={colors.slate} />
    </Pressable>
  );
}

/**
 * The child's card: the Learner Code in big letters and a QR code that holds the code only (never the PIN). A teacher scans it to
 * add the child to a class. The QR is drawn by the server, so no outside service ever sees the code.
 */
export function ChildCard({ child, onBack, onProgress, onNewPin }: { child: api.ParentChild; onBack: () => void; onProgress: () => void; onNewPin: () => void }) {
  const { data, error, reload } = useLoad(() => api.childCard(child.id), [child.id]);

  return (
    <Screen withTabBar>
      <TopBar title={child.firstName} sub={[child.grade, child.className].filter(Boolean).join(' · ')} onBack={onBack} right={<ChildFace child={child} px={s(52)} />} />
      <View style={{ gap: space[14] }}>
        {!data ? (
          <Loading error={error} onRetry={reload} text="Getting the card" />
        ) : (
          <>
            <Panel style={{ alignItems: 'center', gap: space[10] }}>
              <Small>Learner Code</Small>
              <Text selectable maxFontSizeMultiplier={1.2} style={st.code} accessibilityLabel={`Learner code ${data.code.split('').join(' ')}`}>
                {data.code}
              </Text>
              <View style={st.qr} accessible accessibilityLabel={`QR code for ${data.firstName}`}>
                <SvgXml xml={data.qrSvg} width={s(220)} height={s(220)} />
              </View>
              <Small style={{ textAlign: 'center' }}>Show this to the teacher so they can add {data.firstName} to the class. The PIN is never in the QR code.</Small>
            </Panel>
            <Btn label={`See ${data.firstName}'s progress`} icon="chart-bar" onPress={onProgress} />
            {/* Forgot the PIN? Only a guardian can make a new one (the child has no email, and a brother could reset a PIN they do not know). */}
            <Btn label="New PIN" icon="key" tone="ghost" onPress={onNewPin} accessibilityHint={`Make a new PIN for ${data.firstName}`} />
          </>
        )}
      </View>
    </Screen>
  );
}

const RELATIONSHIPS = ['Mother', 'Father', 'Guardian'];

/** A second guardian joins a child with the Learner Code and the child's 4 digit PIN. The words for a refusal come from the server. */
export function LinkChild({ onBack, onLinked }: { onBack: () => void; onLinked: (message: string) => void }) {
  const [code, setCode] = useState('');
  const [pin, setPin] = useState('');
  const [relationship, setRelationship] = useState('Mother');
  const [errors, setErrors] = useState<Record<string, string>>({});
  const [error, setError] = useState<string | null>(null);
  const [busy, setBusy] = useState(false);

  async function link() {
    if (busy) return;
    const found: Record<string, string> = {};
    if (!code.trim()) found.learner_code = 'Type the child\'s Learner Code.';
    if (!/^\d{4}$/.test(pin)) found.pin = 'The PIN is 4 digits.';
    setErrors(found);
    if (Object.keys(found).length) return;

    setBusy(true);
    setError(null);
    try {
      const done = await api.linkChild({ learner_code: code.trim(), pin, relationship });
      onLinked(done.message);
    } catch (e) {
      const fields = api.fieldErrors(e);
      setErrors(fields);
      if (!Object.keys(fields).length) setError(e instanceof Error ? e.message : 'Something went wrong. Please try again.');
    } finally {
      setBusy(false);
    }
  }

  return (
    <KeyboardAvoidingView style={{ flex: 1 }} behavior="padding">
      <Screen withTabBar anchor="content" header={<IconBtn icon="caret-left" label="Back" onPress={onBack} />} footer={<Btn label="Link this child" onPress={link} loading={busy} />}>
        <View style={{ gap: space[10], paddingVertical: space[8] }}>
          <TopBar title="Link an existing child" sub="For a second guardian" />
          <P>Ask the child's other guardian for the Learner Code and the child's PIN. Both are needed, so nobody can look a child up by guessing.</P>
          <Field label="Learner Code" value={code} onChangeText={(t) => (setErrors((e) => ({ ...e, learner_code: '' })), setCode(t.toUpperCase()))} placeholder="TB26-48293" autoCapitalize="characters" maxLength={20} error={errors.learner_code} />
          <Field label="The child's PIN" value={pin} onChangeText={(t) => (setErrors((e) => ({ ...e, pin: '' })), setPin(t.replace(/\D/g, '').slice(0, 4)))} keyboardType="number-pad" maxLength={4} password error={errors.pin} />
          <View style={{ gap: space[6] }}>
            <Text maxFontSizeMultiplier={1.25} style={st.label}>
              You are the child's
            </Text>
            <Chips label="Relationship" options={RELATIONSHIPS} picked={[relationship]} onToggle={setRelationship} />
            {errors.relationship ? <Small style={{ color: colors.bad }}>{errors.relationship}</Small> : null}
          </View>
          {error ? <Notice text={error} /> : null}
        </View>
      </Screen>
    </KeyboardAvoidingView>
  );
}

const st = StyleSheet.create({
  row: { flexDirection: 'row', alignItems: 'center', gap: space[12], minHeight: s(72), backgroundColor: colors.white, borderRadius: s(16), borderWidth: 2, borderColor: colors.line, borderBottomWidth: s(4), padding: space[12] },
  rowName: { fontFamily: fonts.uiBold, fontSize: s(19), lineHeight: s(24), color: colors.ink },
  rowTags: { flexDirection: 'row', flexWrap: 'wrap', gap: space[6] },
  code: { fontFamily: fonts.display, fontSize: s(34), lineHeight: s(42), color: colors.ink, letterSpacing: s(1) },
  qr: { backgroundColor: colors.white, padding: space[10], borderRadius: s(14), borderWidth: 2, borderColor: colors.line },
  label: { fontFamily: fonts.uiBold, fontSize: s(13), letterSpacing: s(0.8), textTransform: 'uppercase', color: colors.slate },
});
