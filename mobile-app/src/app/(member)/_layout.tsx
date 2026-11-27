import { Redirect } from 'expo-router';
import { Tabs } from 'expo-router/tabs';
import { tabIcon } from '../../components/TabIcon';
import { colors } from '../../components/theme';
import { useI18n } from '../../i18n/I18nProvider';
import { useAuth } from '../../lib/auth';

export default function MemberLayout() {
  const { t } = useI18n();
  const { session } = useAuth();
  if (session?.type !== 'member') return <Redirect href="/login" />;

  return (
    <Tabs screenOptions={{ headerShown: false, tabBarActiveTintColor: colors.primaryDark }}>
      <Tabs.Screen name="home" options={{ title: t('home'), tabBarIcon: tabIcon('home-outline') }} />
      <Tabs.Screen name="card" options={{ title: t('card'), tabBarIcon: tabIcon('qr-code-outline') }} />
      <Tabs.Screen name="history" options={{ title: t('history'), tabBarIcon: tabIcon('time-outline') }} />
      <Tabs.Screen name="account" options={{ title: t('account'), tabBarIcon: tabIcon('person-outline') }} />
    </Tabs>
  );
}
