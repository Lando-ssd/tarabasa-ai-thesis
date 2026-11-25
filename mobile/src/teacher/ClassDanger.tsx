import { useEffect, useState, type ReactNode } from 'react';
import { KeyboardAvoidingView, Modal, ScrollView, StyleSheet, View } from 'react-native';
import { useSafeAreaInsets } from 'react-native-safe-area-context';
import * as api from '../api';
import { Field } from '../fields';
import { Btn, H1, IconBtn, Notice, P, Small } from '../ui';
import { colors, size, space } from '../theme';
import { s } from '../scale';

/**
 * The two ways a Teacher lets go of a child or a class, each behind a sheet that says in plain words what changes and what does not.
 * NEITHER deletes a child: the account, the readings and the level stay with the family, and the parents are told. Every rule is on the
 * server (TeacherClassService::removeLearner and ::delete, shared with the website); these sheets only explain it and show what the
 * server says. The same words as the website's windows.
 */

/** A full screen sheet: a close button and title at the top, the words in the middle, the buttons at the bottom where the thumb is. */
function Sheet({ visible, title, sub, onClose, children, footer }: { visible: boolean; title: string; sub?: string; onClose: () => void; children: ReactNode; footer: ReactNode }) {
  const insets = useSafeAreaInsets();

  return (
    <Modal visible={visible} animationType="slide" onRequestClose={onClose} statusBarTranslucent>
      <KeyboardAvoidingView style={{ flex: 1 }} behavior="padding">
        <View style={[st.root, { paddingTop: insets.top + 8, paddingBottom: Math.max(insets.bottom, 12) }]}>
          <View style={st.top}>
            <IconBtn icon="x" label="Close" onPress={onClose} />
            <View style={{ flex: 1 }}>
              <H1>{title}</H1>
              {sub ? <Small>{sub}</Small> : null}
            </View>
          </View>
          <ScrollView keyboardShouldPersistTaps="handled" contentContainerStyle={st.body}>
            {children}
          </ScrollView>
          <View style={st.foot}>{footer}</View>
        </View>
      </KeyboardAvoidingView>
    </Modal>
  );
}

function Bullet({ children }: { children: ReactNode }) {
  return (
    <View style={st.bullet}>
      <View style={st.dot} />
      <P style={{ flex: 1 }}>{children}</P>
    </View>
  );
}

// ------------------------------------------------------------------ take a learner out of the class

type RemoveProps = {
  visible: boolean;
  classId: number;
  className: string;
  learner: api.RosterLearner;
  onClose: () => void;
  /** The child is out: the app goes back to the class, which no longer lists them, and shows the sentence the server gave. */
  onRemoved: (message: string) => void;
};

export function RemoveLearnerSheet({ visible, classId, className, learner, onClose, onRemoved }: RemoveProps) {
  const [busy, setBusy] = useState(false);
  const [error, setError] = useState<string | null>(null);
  const first = learner.firstName;

  useEffect(() => {
    if (visible) {
      setBusy(false);
      setError(null);
    }
  }, [visible]);

  async function remove() {
    if (busy) return;
    setBusy(true);
    setError(null);
    try {
      const done = await api.removeLearnerFromClass(classId, learner.id);
      onRemoved(done.message);
    } catch (e) {
      setError(e instanceof Error ? e.message : 'Something went wrong. Please try again.');
    } finally {
      setBusy(false);
    }
  }

  return (
    <Sheet
      visible={visible}
      title={`Take ${first} out of ${className}?`}
      sub={[learner.code, learner.grade].filter(Boolean).join(' · ')}
      onClose={onClose}
      footer={
        <>
          {error ? <Notice text={error} /> : null}
          <Btn label="Take out of class" tone="danger" icon="user-minus" onPress={remove} loading={busy} accessibilityHint={`${first} is not deleted. Only the link to ${className} ends.`} />
          <Btn label="Cancel" tone="ghost" onPress={onClose} />
        </>
      }
    >
      <Bullet>{first} is not deleted. The account, readings and reading level stay with the family.</Bullet>
      <Bullet>
        What was given to {className} or to its reading groups stops reaching {first}. What you gave {first} directly stays on {first}'s own list.
      </Bullet>
      <Bullet>{first}'s parents are told.</Bullet>
      <Bullet>
        To add {first} again, use the Learner Code {learner.code}, in this class or another {learner.grade ?? ''} class.
      </Bullet>
    </Sheet>
  );
}

// ------------------------------------------------------------------ delete the class

/** The same name, ignoring capital letters and the spaces around and between the words: exactly what the server accepts. */
export const sameName = (typed: string, name: string) => {
  const clean = (t: string) => t.replace(/\s+/g, ' ').trim().toLowerCase();
  return clean(typed) !== '' && clean(typed) === clean(name);
};

type DeleteProps = {
  visible: boolean;
  card: api.ClassCard;
  /** What deleting would change, counted by the server. */
  deletion: { learners: number; activities: number };
  onClose: () => void;
  /** The class is gone: the app goes back to the class list and shows the sentence the server gave. */
  onDeleted: (message: string) => void;
};

export function DeleteClassSheet({ visible, card, deletion, onClose, onDeleted }: DeleteProps) {
  const [typed, setTyped] = useState('');
  const [busy, setBusy] = useState(false);
  const [error, setError] = useState<string | null>(null);
  const [nameError, setNameError] = useState<string | null>(null);

  const n = deletion.learners;
  const a = deletion.activities;
  // An empty class needs no typing (nobody is affected). A class with learners is deleted only when its name is typed.
  const needsName = n > 0;
  const ready = !needsName || sameName(typed, card.name);

  useEffect(() => {
    if (visible) {
      setTyped('');
      setBusy(false);
      setError(null);
      setNameError(null);
    }
  }, [visible]);

  async function remove() {
    if (busy || !ready) return;
    setBusy(true);
    setError(null);
    setNameError(null);
    try {
      const done = await api.deleteClass(card.id, needsName ? typed : undefined);
      onDeleted(done.message);
    } catch (e) {
      // A name the server does not accept comes back under confirm_name, in the website's words.
      const fields = api.fieldErrors(e);
      if (fields.confirm_name) setNameError(fields.confirm_name);
      else setError(e instanceof Error ? e.message : 'Something went wrong. Please try again.');
    } finally {
      setBusy(false);
    }
  }

  return (
    <Sheet
      visible={visible}
      title="Delete this class?"
      sub={`${card.name} · ${card.grade} · ${card.section}`}
      onClose={onClose}
      footer={
        <>
          {/* The name box and any answer from the server live down here with the buttons, so they are always in view (and above the
              keyboard) however long the words above are. A short phone would otherwise hide the box under the buttons. */}
          {needsName ? (
            <Field
              label={`Type the class name: ${card.name}`}
              value={typed}
              onChangeText={(t) => {
                setTyped(t);
                setNameError(null);
              }}
              placeholder={card.name}
              autoCapitalize="none"
              returnKeyType="done"
              error={nameError}
            />
          ) : null}
          {error ? <Notice text={error} /> : null}
          <Btn label="Delete class" tone="danger" icon="trash" onPress={remove} loading={busy} disabled={!ready} accessibilityHint={ready ? undefined : 'Turns on when the class name is typed'} />
          <Btn label="Cancel" tone="ghost" onPress={onClose} />
        </>
      }
    >
      {n > 0 ? <Notice text={`${n} ${n === 1 ? 'learner is' : 'learners are'} in this class. ${n === 1 ? 'This learner is' : 'They are'} released, not deleted.`} /> : null}

      {n > 0 ? (
        <>
          <Bullet>Each learner keeps their account, readings and reading level. No child is deleted.</Bullet>
          <Bullet>Their parents are told that the child is no longer in this class.</Bullet>
          <Bullet>A learner can be added again any time with their Learner Code, in a class of their grade.</Bullet>
        </>
      ) : (
        <Bullet>This class is empty, so no child is affected.</Bullet>
      )}
      <Bullet>
        {a > 0
          ? `${a} ${a === 1 ? 'activity' : 'activities'} given to this class or its reading groups will no longer be listed here. The activities themselves stay in My activities.`
          : 'Nothing was given to this class, so nothing else changes.'}
      </Bullet>
      <Bullet>This cannot be undone.</Bullet>
    </Sheet>
  );
}

const st = StyleSheet.create({
  root: { flex: 1, backgroundColor: colors.white },
  top: { flexDirection: 'row', alignItems: 'center', gap: space[12], paddingHorizontal: size.gutter, paddingBottom: space[8] },
  body: { padding: size.gutter, gap: space[12], paddingBottom: space[24] },
  foot: { paddingHorizontal: size.gutter, paddingTop: space[8], gap: space[8] },
  bullet: { flexDirection: 'row', gap: space[8], alignItems: 'flex-start' },
  dot: { width: s(8), height: s(8), borderRadius: s(4), backgroundColor: colors.orange, marginTop: s(8) },
});
