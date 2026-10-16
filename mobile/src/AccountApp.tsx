import { useCallback, useEffect, useState } from 'react';
import { BackHandler, StyleSheet, View } from 'react-native';
import type { Account, ClassCard, ClassList, ParentChild } from './api';
import { MyProfile } from './MyProfile';
import { AddChild } from './parent/AddChild';
import { ChildCard, Children, LinkChild } from './parent/Children';
import { ResetPin } from './parent/ResetPin';
import { ParentActivities } from './parent/ParentActivities';
import { ParentAlerts } from './parent/ParentAlerts';
import { ParentHome } from './parent/ParentHome';
import { ParentProgress } from './parent/ParentProgress';
import { Activities } from './teacher/Activities';
import { ActivityScreen } from './teacher/ActivityScreen';
import { Alerts } from './teacher/Alerts';
import { Analytics } from './teacher/Analytics';
import { ClassScreen } from './teacher/ClassScreen';
import { Classes, ClassForm } from './teacher/Classes';
import { Generate } from './teacher/Generate';
import { LearnerScreen } from './teacher/LearnerScreen';
import { More } from './teacher/More';
import { Promotions } from './teacher/Promotions';
import { TeacherHome } from './teacher/TeacherHome';
import { TabBar, type TabItem } from './TabBar';
import { Btn } from './ui';
import { colors } from './theme';

type TeacherTab = 'home' | 'classes' | 'activities' | 'alerts' | 'more';
type ParentTab = 'home' | 'children' | 'progress' | 'activities' | 'alerts';

// The same places as the approved design: a Teacher's Analytics, Promotions and Profile live under More; a Parent's Profile
// opens from the picture at the top.
const TEACHER_TABS: TabItem<TeacherTab>[] = [
  { key: 'home', label: 'Home', icon: 'house' },
  { key: 'classes', label: 'Classes', icon: 'users' },
  { key: 'activities', label: 'Activities', icon: 'books' },
  { key: 'alerts', label: 'Alerts', icon: 'bell' },
  { key: 'more', label: 'More', icon: 'dots-three' },
];

const PARENT_TABS: TabItem<ParentTab>[] = [
  { key: 'home', label: 'Home', icon: 'house' },
  { key: 'children', label: 'Children', icon: 'student' },
  { key: 'progress', label: 'Progress', icon: 'chart-bar' },
  { key: 'activities', label: 'Activities', icon: 'books' },
  { key: 'alerts', label: 'Alerts', icon: 'bell' },
];

type Props = { account: Account; onSignOut: () => void };

/**
 * The signed in app for a Teacher or a Parent: the bottom bar of the approved design, opened from the type of account (there
 * is no role picker to get wrong). The screens behind most tabs are still being built, and they say so. Log out and the
 * account's own details already work.
 */
export function AccountApp({ account, onSignOut }: Props) {
  return account.role === 'teacher' ? <TeacherApp account={account} onSignOut={onSignOut} /> : <ParentApp account={account} onSignOut={onSignOut} />;
}

type ClassesRoute =
  | { name: 'list' }
  | { name: 'class'; id: number }
  | { name: 'learner'; classId: number; id: number }
  | { name: 'new' }
  | { name: 'edit'; card: ClassCard };

type ActivitiesRoute = { name: 'list' } | { name: 'activity'; id: number } | { name: 'generate' };

type MoreRoute = 'menu' | 'analytics' | 'promotions' | 'profile';

function TeacherApp({ account: initial, onSignOut }: Props) {
  const [account, setAccount] = useState(initial);
  const [tab, setTab] = useState<TeacherTab>('home');
  const [route, setRoute] = useState<ClassesRoute>({ name: 'list' });
  const [actRoute, setActRoute] = useState<ActivitiesRoute>({ name: 'list' });
  const [moreRoute, setMoreRoute] = useState<MoreRoute>('menu');
  // The grades this teacher handles and the current school year come from the Classes list, so a new class is made with the server's own idea of "this year".
  const [meta, setMeta] = useState<Pick<ClassList, 'grades' | 'currentSchoolYear'> | null>(null);
  // Bumped when a class (or an activity) changes, so the list and the page behind fetch again when they come back into view.
  const [refresh, setRefresh] = useState(0);
  const [actRefresh, setActRefresh] = useState(0);
  const [alertCount, setAlertCount] = useState(0);
  const [claimable, setClaimable] = useState(0);
  // The school year shown on Classes (null is this year) and what the last class action said (a learner moved, for example).
  const [classYear, setClassYear] = useState<string | null>(null);
  const [classFlash, setClassFlash] = useState<string | null>(null);
  const active = account.teacher?.status === 'Active';

  const goTab = (next: TeacherTab) => {
    setTab(next);
    if (next !== 'classes') {
      setRoute({ name: 'list' });
      setClassFlash(null);
    }
    if (next !== 'activities') setActRoute({ name: 'list' });
    if (next !== 'more') setMoreRoute('menu');
  };

  // The phone's back button: one step back inside a tab, then to Home, then out of the app.
  const back = useCallback(() => {
    if (tab === 'classes' && route.name !== 'list') {
      setRoute(route.name === 'learner' ? { name: 'class', id: route.classId } : route.name === 'edit' ? { name: 'class', id: route.card.id } : { name: 'list' });
      return true;
    }
    if (tab === 'activities' && actRoute.name !== 'list') {
      setActRoute({ name: 'list' });
      return true;
    }
    if (tab === 'more' && moreRoute !== 'menu') {
      setMoreRoute('menu');
      return true;
    }
    if (tab !== 'home') {
      goTab('home');
      return true;
    }
    return false;
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [tab, route, actRoute, moreRoute]);
  useEffect(() => {
    const sub = BackHandler.addEventListener('hardwareBackPress', back);
    return () => sub.remove();
  }, [back]);

  let screen;
  if (tab === 'home') {
    screen = (
      <TeacherHome
        account={account}
        go={goTab}
        onLoaded={(h) => {
          setAlertCount(h.counts.alerts);
          setClaimable(h.counts.claim);
        }}
      />
    );
  } else if (tab === 'more') {
    if (moreRoute === 'analytics') screen = <Analytics onBack={() => setMoreRoute('menu')} />;
    else if (moreRoute === 'promotions') screen = <Promotions active={active} onBack={() => setMoreRoute('menu')} />;
    else if (moreRoute === 'profile') screen = <MyProfile account={account} onBack={() => setMoreRoute('menu')} onAccount={setAccount} />;
    else screen = <More account={account} claimable={claimable} onOpen={setMoreRoute} onSignOut={onSignOut} />;
  } else if (tab === 'alerts') {
    screen = (
      <Alerts
        active={active}
        refreshKey={actRefresh}
        onCount={setAlertCount}
        onLearner={(classId, id) => {
          setTab('classes');
          setRoute({ name: 'learner', classId, id });
        }}
      />
    );
  } else if (tab === 'activities') {
    // The list stays mounted (hidden) behind an activity, so the filter and search are still there when you come back.
    screen = (
      <>
        <View style={{ flex: 1, display: actRoute.name === 'list' ? 'flex' : 'none' }}>
          <Activities active={active} refreshKey={actRefresh} onOpen={(id) => setActRoute({ name: 'activity', id })} onGenerate={() => setActRoute({ name: 'generate' })} />
        </View>
        {actRoute.name === 'activity' ? (
          <ActivityScreen key={actRoute.id} id={actRoute.id} locked={!active} onBack={() => setActRoute({ name: 'list' })} onChanged={() => setActRefresh((n) => n + 1)} />
        ) : null}
        {actRoute.name === 'generate' ? (
          <Generate
            onBack={() => setActRoute({ name: 'list' })}
            onStarted={() => {
              setActRefresh((n) => n + 1);
              setActRoute({ name: 'list' });
            }}
          />
        ) : null}
      </>
    );
  } else if (tab === 'classes') {
    if (route.name === 'class') {
      screen = (
        <ClassScreen
          id={route.id}
          refreshKey={refresh}
          flash={classFlash}
          onBack={() => {
            setClassFlash(null);
            setRoute({ name: 'list' });
          }}
          onLearner={(id) => {
            setClassFlash(null);
            setRoute({ name: 'learner', classId: route.id, id });
          }}
          onEdit={(card) => setRoute({ name: 'edit', card })}
        />
      );
    } else if (route.name === 'learner') {
      screen = (
        <LearnerScreen
          classId={route.classId}
          id={route.id}
          onBack={() => setRoute({ name: 'class', id: route.classId })}
          onMoved={(message) => {
            setClassFlash(message);
            setRefresh((n) => n + 1);
            setRoute({ name: 'class', id: route.classId });
          }}
        />
      );
    } else if ((route.name === 'new' || route.name === 'edit') && meta) {
      screen = (
        <ClassForm
          mode={route.name}
          card={route.name === 'edit' ? route.card : null}
          grades={meta.grades}
          schoolYear={route.name === 'edit' ? route.card.schoolYear : meta.currentSchoolYear}
          onBack={() => setRoute(route.name === 'edit' ? { name: 'class', id: route.card.id } : { name: 'list' })}
          onSaved={(card) => {
            setRefresh((n) => n + 1);
            setRoute({ name: 'class', id: card.id });
          }}
        />
      );
    } else {
      screen = <Classes year={classYear} onYear={setClassYear} active={active} refreshKey={refresh} onOpen={(id) => setRoute({ name: 'class', id })} onNew={() => setRoute({ name: 'new' })} onLoaded={(list) => setMeta({ grades: list.grades, currentSchoolYear: list.currentSchoolYear })} />;
    }
  } else {
    screen = null;
  }

  const tabs = TEACHER_TABS.map((t) => (t.key === 'alerts' && alertCount > 0 ? { ...t, badge: alertCount } : t));

  return (
    <View style={st.root}>
      <View style={{ flex: 1 }}>{screen}</View>
      <TabBar tabs={tabs} active={tab} onChange={goTab} />
    </View>
  );
}

type ChildrenRoute = { name: 'list' } | { name: 'add' } | { name: 'link' } | { name: 'card'; child: ParentChild } | { name: 'pin'; child: ParentChild };

function ParentApp({ account: initial, onSignOut }: Props) {
  const [account, setAccount] = useState(initial);
  const [tab, setTab] = useState<ParentTab>('home');
  const [route, setRoute] = useState<ChildrenRoute>({ name: 'list' });
  const [profile, setProfile] = useState(false);
  // The child being looked at stays the same when you move between Home, Progress and Activities. Null lets the server pick the first.
  const [childId, setChildId] = useState<number | null>(null);
  // Bumped when children, unlocks or alerts change, so the screens behind fetch again when they come back into view.
  const [refresh, setRefresh] = useState(0);
  const [unread, setUnread] = useState(0);
  const [flash, setFlash] = useState<string | null>(null);

  const goTab = (next: ParentTab) => {
    setProfile(false);
    setTab(next);
    if (next !== 'children') setRoute({ name: 'list' });
    setFlash(null);
  };

  const addChild = () => {
    setProfile(false);
    setTab('children');
    setRoute({ name: 'add' });
  };

  // The phone's back button: one step back (a form, a card, the profile), then Home, then out of the app.
  const back = useCallback(() => {
    if (profile) {
      setProfile(false);
      return true;
    }
    if (tab === 'children' && route.name === 'pin') {
      setRoute({ name: 'card', child: route.child });
      return true;
    }
    if (tab === 'children' && route.name !== 'list') {
      setRoute({ name: 'list' });
      return true;
    }
    if (tab !== 'home') {
      goTab('home');
      return true;
    }
    return false;
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [profile, tab, route]);
  useEffect(() => {
    const sub = BackHandler.addEventListener('hardwareBackPress', back);
    return () => sub.remove();
  }, [back]);

  let screen;
  if (profile) {
    screen = (
      <MyProfile
        account={account}
        onBack={() => setProfile(false)}
        onAccount={setAccount}
        extra={<Btn label="Log out" tone="ghost" icon="sign-out" onPress={onSignOut} />}
      />
    );
  } else if (tab === 'home') {
    screen = <ParentHome account={account} childId={childId} onPick={setChildId} refreshKey={refresh} onProfile={() => setProfile(true)} onAdd={addChild} go={goTab} onLoaded={(h) => setUnread(h.unread)} />;
  } else if (tab === 'children') {
    if (route.name === 'add') {
      screen = (
        <AddChild
          onBack={() => setRoute({ name: 'list' })}
          onDone={() => {
            setRefresh((n) => n + 1);
            setRoute({ name: 'list' });
          }}
          onCard={(child) => {
            setRefresh((n) => n + 1);
            setRoute({ name: 'card', child });
          }}
        />
      );
    } else if (route.name === 'link') {
      screen = (
        <LinkChild
          onBack={() => setRoute({ name: 'list' })}
          onLinked={(message) => {
            setFlash(message);
            setRefresh((n) => n + 1);
            setRoute({ name: 'list' });
          }}
        />
      );
    } else if (route.name === 'card') {
      screen = (
        <ChildCard
          child={route.child}
          onBack={() => setRoute({ name: 'list' })}
          onProgress={() => {
            setChildId(route.child.id);
            goTab('progress');
          }}
          onNewPin={() => setRoute({ name: 'pin', child: route.child })}
        />
      );
    } else if (route.name === 'pin') {
      screen = <ResetPin child={route.child} onBack={() => setRoute({ name: 'card', child: route.child })} onDone={() => setRoute({ name: 'card', child: route.child })} />;
    } else {
      screen = <Children refreshKey={refresh} flash={flash} onOpen={(child) => setRoute({ name: 'card', child })} onAdd={() => setRoute({ name: 'add' })} onLink={() => setRoute({ name: 'link' })} />;
    }
  } else if (tab === 'progress') {
    screen = <ParentProgress childId={childId} onPick={setChildId} refreshKey={refresh} onProfile={() => setProfile(true)} go={goTab} />;
  } else if (tab === 'activities') {
    screen = <ParentActivities childId={childId} onPick={setChildId} refreshKey={refresh} onAdd={addChild} />;
  } else {
    screen = <ParentAlerts refreshKey={refresh} onUnread={setUnread} />;
  }

  const tabs = PARENT_TABS.map((t) => (t.key === 'alerts' && unread > 0 ? { ...t, badge: unread } : t));

  return (
    <View style={st.root}>
      <View style={{ flex: 1 }}>{screen}</View>
      <TabBar tabs={tabs} active={tab} onChange={goTab} />
    </View>
  );
}

const st = StyleSheet.create({
  root: { flex: 1, backgroundColor: colors.page },
});
