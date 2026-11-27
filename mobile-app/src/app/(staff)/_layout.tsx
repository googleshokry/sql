import { Redirect } from 'expo-router';
import { Tabs } from 'expo-router/tabs';
import { tabIcon } from '../../components/TabIcon';
import { colors } from '../../components/theme';
import { useI18n } from '../../i18n/I18nProvider';
import { useAuth } from '../../lib/auth';

export default function StaffLayout() {
  const { t } = useI18n();
  const { session } = useAuth();
  if (session?.type !== 'staff') return <Redirect href="/login" />;

  return (
    <Tabs screenOptions={{ headerShown: false, tabBarActiveTintColor: colors.primaryDark }}>
      <Tabs.Screen name="scan" options={{ title: t('scan'), tabBarIcon: tabIcon('scan-outline') }} />
      <Tabs.Screen name="inside" options={{ title: t('inside'), tabBarIcon: tabIcon('people-outline') }} />
      <Tabs.Screen name="members" options={{ title: t('members'), tabBarIcon: tabIcon('search-outline') }} />
      <Tabs.Screen name="settings" options={{ title: t('settings'), tabBarIcon: tabIcon('settings-outline') }} />
    </Tabs>
  );
}
