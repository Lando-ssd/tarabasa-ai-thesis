import { useEffect, useState, type ReactNode } from 'react';
import { Pressable, StyleSheet, Text, View } from 'react-native';
import * as api from '../api';
import { Segmented } from '../fields';
import { Btn, Busy, Icon, Notice, P, Small } from '../ui';
import { TonePill, type Tone } from '../teacher/kit';
import { Sheet } from '../teacher/ClassDanger';
import { colors, fonts, size, space } from '../theme';
import { s } from '../scale';

/**
 * Everything that opens from a shared activity, in ONE sheet whose contents change: the activity itself (its words once unlocked
 * for this child, this parent's receipt, a rating), the demo checkout (pretend money: choose, pay, result) and the payment
 * history. The rules are all on the server (ParentRepositoryService and PaymentService, shared with the website): only an
 * approved demo payment unlocks. There is no box to type a card or wallet number, and nothing goes to any other company.
 */

export type FlowView = { kind: 'detail'; id: number } | { kind: 'checkout'; id: number } | { kind: 'history' } | null;

export type PayMode = 'off' | 'demo' | 'paymongo';

type Props = {
  view: FlowView;
  setView: (v: FlowView) => void;
  listing: api.ParentListing | null;
  learnerId: number;
  first: string;
  mode: PayMode;
  busy: boolean;
  problem: string | null;
  onUnlock: (listing: api.ParentListing) => void;
  onRate: (listing: api.ParentListing, stars: number) => void;
  /** A payment changed something (paid, or a new record): the list asks the server again. */
  onPaid: () => void;
};

const OUTCOMES: { key: api.DemoOutcome; label: string }[] = [
  { key: 'approve', label: 'Approved' },
  { key: 'decline', label: 'Declined' },
  { key: 'cancel', label: 'Cancelled' },
];

const STATUS_TONE: Record<string, Tone> = { paid: 'green', pending: 'amber', cancelled: 'grey', expired: 'grey', failed: 'red' };

const words = (e: unknown) => {
  const fields = api.fieldErrors(e);
  return fields.unlock ?? fields.method ?? (e instanceof Error ? e.message : 'Something went wrong. Please try again.');
};

export function ActivityFlow({ view, setView, listing, learnerId, first, mode, busy, problem, onUnlock, onRate, onPaid }: Props) {
  const [payment, setPayment] = useState<api.DemoPayment | null>(null);
  const [methods, setMethods] = useState<{ key: string; name: string }[]>([]);
  const [method, setMethod] = useState<string | null>(null);
  const [outcome, setOutcome] = useState<api.DemoOutcome>('approve');
  const [step, setStep] = useState<'choose' | 'pay' | 'result'>('choose');
  const [working, setWorking] = useState(false);
  const [failed, setFailed] = useState<string | null>(null);
  const [attempt, setAttempt] = useState(0);
  const [history, setHistory] = useState<api.DemoPayment[] | null>(null);

  const kind = view?.kind ?? null;
  const checkoutId = view?.kind === 'checkout' ? view.id : null;

  // Opening the checkout makes (or finds again) the pending payment. Nothing is unlocked by this.
  useEffect(() => {
    setPayment(null);
    setMethods([]);
    setMethod(null);
    setOutcome('approve');
    setStep('choose');
    setFailed(null);
    if (checkoutId === null) return;
    let alive = true;
    api
      .startDemoPayment(checkoutId, learnerId)
      .then((r) => {
        if (!alive) return;
        setPayment(r.payment);
        setMethods(r.methods);
      })
      .catch((e) => alive && setFailed(words(e)));
    return () => {
      alive = false;
    };
  }, [checkoutId, learnerId, attempt]);

  useEffect(() => {
    if (kind !== 'history') return;
    setHistory(null);
    setFailed(null);
    let alive = true;
    api
      .paymentHistory()
      .then((r) => alive && setHistory(r.payments))
      .catch((e) => alive && setFailed(words(e)));
    return () => {
      alive = false;
    };
  }, [kind]);

  async function answer(result: api.DemoOutcome) {
    if (!payment || working) return;
    setWorking(true);
    setFailed(null);
    try {
      const done = await api.answerDemoPayment(payment.reference, method ?? methods[0]?.key ?? 'card', result);
      setPayment(done.payment);
      setStep('result');
      onPaid();
    } catch (e) {
      setFailed(words(e));
    } finally {
      setWorking(false);
    }
  }

  if (!view) return null;
  const close = () => setView(null);

  // ------------------------------------------------------------------ payment history
  if (view.kind === 'history') {
    return (
      <Sheet visible title="Payment history" sub="Demo payments use pretend money" onClose={close} footer={<Btn label="Close" tone="ghost" onPress={close} />}>
        {failed ? <Notice text={failed} /> : null}
        {!history && !failed ? <Busy text="Getting your payments" /> : null}
        {history && history.length === 0 ? <P>No payments yet. When you pay for a paid activity, each attempt is listed here.</P> : null}
        {(history ?? []).map((p) => (
          <View key={p.reference} style={st.row}>
            <Text maxFontSizeMultiplier={1.25} style={st.rowTitle}>
              {p.activity}
            </Text>
            <Small>{[`For ${p.child}`, p.method, `${p.amount} (${p.word})`].filter(Boolean).join(' · ')}</Small>
            <Small>{[p.reference, p.date].filter(Boolean).join(' · ')}</Small>
            <View style={{ alignSelf: 'flex-start' }}>
              <TonePill text={p.statusLabel} tone={STATUS_TONE[p.status] ?? 'grey'} />
            </View>
          </View>
        ))}
      </Sheet>
    );
  }

  if (!listing) return null;

  // ------------------------------------------------------------------ the demo checkout
  if (view.kind === 'checkout') {
    const chosen = methods.find((m) => m.key === method)?.name ?? '';
    let footer: ReactNode = <Btn label="Close" tone="ghost" onPress={close} />;
    if (payment && step === 'choose') {
      footer = (
        <View style={{ gap: space[8] }}>
          {/* Kept above the buttons so a short phone never hides it. */}
          <Label>Demo only: what should happen?</Label>
          <Segmented options={OUTCOMES} value={outcome} onChange={setOutcome} />
          <Btn label="Continue" tone="blue" disabled={!method || working} onPress={() => setStep('pay')} />
          <Btn label="Cancel and go back" tone="ghost" loading={working} onPress={() => answer('cancel')} />
        </View>
      );
    } else if (payment && step === 'pay') {
      footer = (
        <View style={{ gap: space[8] }}>
          <Btn label={`Pay ${payment.amount} (pretend)`} loading={working} onPress={() => answer(outcome)} />
          <Btn label="Choose another way" tone="ghost" disabled={working} onPress={() => setStep('choose')} />
        </View>
      );
    } else if (payment && step === 'result') {
      footer = (
        <View style={{ gap: space[8] }}>
          {payment.status === 'paid' ? <Btn label="See the activity" onPress={() => setView({ kind: 'detail', id: listing.id })} /> : <Btn label="Try again" onPress={() => setAttempt((n) => n + 1)} />}
          <Btn label="Done" tone="ghost" onPress={close} />
        </View>
      );
    }

    return (
      <Sheet visible title="Demo checkout" sub="Pretend money only" onClose={close} footer={footer}>
        <Notice tone="info" text="Demo mode. Pretend money only. Nothing real is charged." />
        {failed ? <Notice text={failed} /> : null}
        {!payment && !failed ? <Busy text="Getting the checkout ready" /> : null}

        {payment && step !== 'result' ? (
          <View style={st.amountBox}>
            <Text maxFontSizeMultiplier={1.2} style={st.amount}>
              {payment.amount}
            </Text>
            <Small style={{ textAlign: 'center' }}>{`${payment.activity} · for ${payment.child}`}</Small>
            <Small style={{ textAlign: 'center' }}>{`Reference ${payment.reference}`}</Small>
          </View>
        ) : null}

        {payment && step === 'choose' ? (
          <>
            <Label>How do you want to pay?</Label>
            <View accessibilityRole="radiogroup" style={{ gap: space[8] }}>
              {methods.map((m) => {
                const on = m.key === method;
                return (
                  <Pressable key={m.key} accessibilityRole="radio" accessibilityState={{ selected: on }} onPress={() => setMethod(m.key)} style={[st.option, on && st.optionOn]}>
                    <Text maxFontSizeMultiplier={1.25} style={st.optionTitle}>
                      {m.name}
                    </Text>
                    <Small>{m.key === 'card' ? 'A pretend card. Nothing to type.' : 'A pretend wallet approval.'}</Small>
                  </Pressable>
                );
              })}
            </View>
          </>
        ) : null}

        {payment && step === 'pay' ? (
          <>
            <View style={st.row}>
              <Label>{chosen}</Label>
              <Small>{method === 'card' ? 'Pretend card number (read only)' : `Pretend ${chosen} number (read only)`}</Small>
              <Text maxFontSizeMultiplier={1.2} style={st.digits}>
                {method === 'card' ? '•••• •••• •••• 4242' : '0917 ••• 0000'}
              </Text>
            </View>
            <Small>{`Demo ending: ${OUTCOMES.find((o) => o.key === outcome)?.label}. You chose it on the previous step.`}</Small>
          </>
        ) : null}

        {payment && step === 'result' ? (
          <>
            <View style={st.result}>
              <Icon name={payment.status === 'paid' ? 'check-circle' : 'warning-circle'} size={s(64)} color={payment.status === 'paid' ? colors.ok : payment.declined ? colors.bad : colors.slate} />
              <Text maxFontSizeMultiplier={1.2} style={st.resultTitle}>
                {payment.status === 'paid' ? `Unlocked for ${payment.child}` : payment.declined ? 'The payment was declined' : payment.status === 'cancelled' ? 'You cancelled' : payment.status === 'expired' ? 'The payment ran out of time' : 'The payment did not go through'}
              </Text>
              <P style={{ textAlign: 'center' }}>{payment.status === 'paid' ? `${payment.activity} is ready to read. No real money was charged.` : 'Nothing was unlocked and nothing was charged.'}</P>
            </View>
            <Record payment={payment} />
          </>
        ) : null}
      </Sheet>
    );
  }

  // ------------------------------------------------------------------ one activity
  const locked = !listing.unlocked;
  let footer: ReactNode = <Btn label="Close" tone="ghost" onPress={close} />;
  if (locked && !listing.paid) footer = <Btn label="Get for free" loading={busy} onPress={() => onUnlock(listing)} />;
  else if (locked && mode === 'demo') footer = <Btn label="Go to demo checkout" icon="lock-simple" tone="blue" onPress={() => setView({ kind: 'checkout', id: listing.id })} />;
  else if (locked && mode === 'off') footer = <Btn label="Confirm unlock" tone="blue" loading={busy} onPress={() => onUnlock(listing)} />;

  return (
    <Sheet visible title={listing.title} sub={[listing.grade, listing.type ?? listing.skill, listing.words ? `${listing.words} words` : null].filter(Boolean).join(' · ')} onClose={close} footer={footer}>
      <View style={st.tags}>
        {listing.level ? <TonePill text={listing.level} tone={listing.level === 'Easy' ? 'green' : listing.level === 'Medium' ? 'amber' : 'red'} /> : null}
        {listing.unlocked ? <TonePill text="Unlocked" tone="green" /> : <TonePill text={listing.price} tone={listing.paid ? 'amber' : 'green'} />}
        {listing.matchesLevel ? <TonePill text={`Right level for ${first}`} tone="blue" /> : null}
        {listing.matchesPattern ? <TonePill text={`Practises ${listing.matchesPattern}`} tone="blue" /> : null}
      </View>
      {listing.teacher ? <Small>Shared by {listing.teacher}</Small> : null}
      {listing.stars !== null ? <Small>{`Rated ${listing.stars} of 5 by ${listing.ratings} ${listing.ratings === 1 ? 'parent' : 'parents'}`}</Small> : null}
      {problem ? <Notice text={problem} /> : null}

      {listing.unlocked ? (
        <>
          {listing.passage ? (
            <>
              <Label>{`What ${first} will read`}</Label>
              <View style={st.passageBox}>
                <Text maxFontSizeMultiplier={1.25} style={st.passage}>
                  {listing.passage}
                </Text>
              </View>
            </>
          ) : null}
          <Small>{first} opens it from their own app, under extra practice.</Small>
          <Label>Your rating</Label>
          <View style={st.stars} accessibilityRole="radiogroup" accessibilityLabel="Your rating">
            {[1, 2, 3, 4, 5].map((n) => (
              <Pressable key={n} accessibilityRole="radio" accessibilityState={{ selected: listing.mine?.rating === n }} accessibilityLabel={`${n} ${n === 1 ? 'star' : 'stars'}`} disabled={busy} onPress={() => onRate(listing, n)} style={st.star}>
                <Icon name="star" size={s(30)} color={(listing.mine?.rating ?? 0) >= n ? colors.goldDeep : colors.line} />
              </Pressable>
            ))}
          </View>
          {listing.receipt ? (
            <>
              <Label>Receipt</Label>
              <Record payment={listing.receipt} />
              <Btn label="Payment history" tone="ghost" compact onPress={() => setView({ kind: 'history' })} />
            </>
          ) : null}
        </>
      ) : (
        <>
          <P>{`The words to read show here once it is unlocked for ${first}.`}</P>
          <View style={st.amountBox}>
            <Text maxFontSizeMultiplier={1.2} style={st.amount}>
              {listing.price}
            </Text>
          </View>
          {listing.paid && mode === 'demo' ? <Small>You go to TaraBasa's demo checkout, inside the app. We never ask for a card or wallet number, nothing is sent to any other company, and no real money is charged.</Small> : null}
          {listing.paid && mode === 'off' ? <Small>This is a simulated payment, so nothing is charged.</Small> : null}
          {listing.paid && mode === 'paymongo' ? <Small>Paid activities are unlocked on the website, with a test payment.</Small> : null}
        </>
      )}
    </Sheet>
  );
}

function Label({ children }: { children: ReactNode }) {
  return (
    <Text maxFontSizeMultiplier={1.25} style={st.label}>
      {children}
    </Text>
  );
}

/** The same six facts the website's receipt shows. */
function Record({ payment }: { payment: api.DemoPayment }) {
  const rows: [string, string | null][] = [
    ['Status', payment.statusLabel],
    ['Activity', payment.activity],
    ['For', payment.child],
    ['Paid with', payment.method ?? 'Not chosen yet'],
    ['Amount', `${payment.amount} (${payment.word})`],
    ['Reference', payment.reference],
    ['Date', payment.date],
  ];
  return (
    <View style={st.row}>
      {rows.map(([name, value]) =>
        value ? (
          <View key={name} style={st.pair}>
            <Small style={st.pairName}>{name}</Small>
            <Text maxFontSizeMultiplier={1.25} style={st.pairValue}>
              {value}
            </Text>
          </View>
        ) : null,
      )}
    </View>
  );
}

const st = StyleSheet.create({
  label: { fontFamily: fonts.uiBold, fontSize: s(13), letterSpacing: s(0.8), textTransform: 'uppercase', color: colors.slate },
  tags: { flexDirection: 'row', flexWrap: 'wrap', gap: space[6] },
  row: { backgroundColor: colors.white, borderRadius: s(16), borderWidth: 2, borderColor: colors.line, padding: space[12], gap: space[4] },
  rowTitle: { fontFamily: fonts.uiBold, fontSize: s(17), lineHeight: s(22), color: colors.ink },
  amountBox: { alignItems: 'center', gap: space[4], backgroundColor: colors.sky, borderRadius: s(16), padding: space[14] },
  amount: { fontFamily: fonts.display, fontSize: s(34), lineHeight: s(42), color: colors.ink },
  option: { minHeight: size.button, justifyContent: 'center', backgroundColor: colors.white, borderRadius: s(16), borderWidth: 2, borderColor: colors.line, paddingHorizontal: space[14], paddingVertical: space[8] },
  optionOn: { borderColor: colors.blue, backgroundColor: colors.sky },
  optionTitle: { fontFamily: fonts.uiBold, fontSize: s(18), color: colors.ink },
  digits: { fontFamily: fonts.uiBold, fontSize: s(22), letterSpacing: s(1.5), color: colors.ink },
  result: { alignItems: 'center', gap: space[8], paddingVertical: space[12] },
  resultTitle: { fontFamily: fonts.display, fontSize: s(24), lineHeight: s(30), color: colors.orange, textAlign: 'center' },
  passageBox: { backgroundColor: colors.sky, borderRadius: s(16), padding: space[14] },
  passage: { fontFamily: fonts.read, fontSize: s(19), lineHeight: s(30), color: colors.ink },
  stars: { flexDirection: 'row', gap: space[2] },
  star: { width: size.touch, height: size.touch, alignItems: 'center', justifyContent: 'center' },
  pair: { flexDirection: 'row', gap: space[10], alignItems: 'baseline' },
  pairName: { width: s(76) },
  pairValue: { flex: 1, fontFamily: fonts.uiBold, fontSize: s(15), color: colors.ink },
});
