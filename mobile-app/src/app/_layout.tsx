import { Stack } from 'expo-router';
import { StatusBar } from 'expo-status-bar';
import { View } from 'react-native';
import { SafeAreaProvider } from 'react-native-safe-area-context';
import { Loading } from '../components/ui';
import { colors } from '../components/theme';
import { I18nProvider, useI18n } from '../i18n/I18nProvider';
import { AuthProvider, useAuth } from '../lib/auth';

function Root() {
  const { isRTL, ready } = useI18n();
  const { loading } = useAuth();

  return (
    // `direction` flips every row/flex layout below it, so the whole UI mirrors for Arabic.
    <View style={{ flex: 1, direction: isRTL ? 'rtl' : 'ltr', backgroundColor: colors.bg }}>
      <StatusBar style="dark" />
      {!ready || loading ? <Loading /> : <Stack screenOptions={{ headerShown: false }} />}
    </View>
  );
}

export default function RootLayout() {
  return (
    <SafeAreaProvider>
      <I18nProvider>
        <AuthProvider>
          <Root />
        </AuthProvider>
      </I18nProvider>
    </SafeAreaProvider>
  );
}
