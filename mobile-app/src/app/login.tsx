import { Redirect } from 'expo-router';
import { useState } from 'react';
import { KeyboardAvoidingView, Platform, Pressable, Text, View } from 'react-native';
import { Button, Card, Input, Row, Screen, Segmented, T } from '../components/ui';
import { colors } from '../components/theme';
import { useI18n } from '../i18n/I18nProvider';
import { ApiError, apiConfig } from '../lib/api';
import { useAuth } from '../lib/auth';

export default function Login() {
  const { t, lang, setLang } = useI18n();
  const { session, loginMember, loginStaff } = useAuth();
  const [mode, setMode] = useState<'member' | 'staff'>('member');
  const [login, setLogin] = useState('');
  const [password, setPassword] = useState('');
  const [server, setServer] = useState(apiConfig.getBaseUrl());
  const [gymCode, setGymCode] = useState(apiConfig.getGym());
  const [showServer, setShowServer] = useState(false);
  const [error, setError] = useState<string | null>(null);
  const [busy, setBusy] = useState(false);

  if (session) return <Redirect href="/" />;

  const submit = async () => {
    setError(null);
    setBusy(true);
    try {
      if (server !== apiConfig.getBaseUrl()) await apiConfig.setBaseUrl(server);
      await apiConfig.setGym(gymCode);
      if (mode === 'member') await loginMember(login.trim(), password);
      else await loginStaff(login.trim(), password);
    } catch (e) {
      setError(e instanceof ApiError && e.status !== 0 ? e.message : t('networkError'));
    } finally {
      setBusy(false);
    }
  };

  return (
    <KeyboardAvoidingView style={{ flex: 1 }} behavior={Platform.OS === 'ios' ? 'padding' : undefined}>
      <Screen>
        <Row style={{ justifyContent: 'flex-end' }}>
          <Pressable onPress={() => setLang(lang === 'ar' ? 'en' : 'ar')} accessibilityRole="button" style={{ padding: 8 }}>
            <Text style={{ fontWeight: '700', color: colors.primaryDark }}>🌐 {t('switchLanguage')}</Text>
          </Pressable>
        </Row>

        <View style={{ alignItems: 'center', gap: 8, marginVertical: 16 }}>
          <View style={{ width: 84, height: 84, borderRadius: 24, backgroundColor: colors.dark, alignItems: 'center', justifyContent: 'center' }}>
            <Text style={{ fontSize: 42 }}>🏋️</Text>
          </View>
          <Text style={{ fontSize: 26, fontWeight: '800', color: colors.text }}>{t('appName')}</Text>
        </View>

        <Card style={{ gap: 14 }}>
          <Segmented
            value={mode}
            onChange={(v) => {
              setMode(v);
              setError(null);
            }}
            options={[
              { value: 'member', label: t('member') },
              { value: 'staff', label: t('staff') },
            ]}
          />
          <T style={{ color: colors.muted }}>{mode === 'member' ? t('memberLoginHint') : t('staffLoginHint')}</T>

          <Input
            label={mode === 'member' ? t('gymCode') : `${t('gymCode')} (${t('optional')})`}
            value={gymCode}
            onChangeText={setGymCode}
            autoCapitalize="none"
            autoCorrect={false}
            placeholder="my-gym"
            testID="gym-input"
          />
          <Input
            label={mode === 'member' ? t('codeOrPhone') : t('email')}
            value={login}
            onChangeText={setLogin}
            autoCapitalize="none"
            autoCorrect={false}
            keyboardType={mode === 'member' ? 'default' : 'email-address'}
            testID="login-input"
          />
          <Input label={t('password')} value={password} onChangeText={setPassword} secureTextEntry onSubmitEditing={submit} testID="password-input" />

          {error && <T style={{ color: colors.danger }}>{error}</T>}

          <Button title={t('signIn')} onPress={submit} loading={busy} disabled={!login || !password || (mode === 'member' && !gymCode.trim())} />
        </Card>

        <Pressable onPress={() => setShowServer((s) => !s)} style={{ alignSelf: 'center', padding: 8 }}>
          <Text style={{ color: colors.muted }}>⚙️ {t('serverUrl')}</Text>
        </Pressable>
        {showServer && (
          <Card>
            <Input label={t('serverUrl')} value={server} onChangeText={setServer} autoCapitalize="none" keyboardType="url" />
            <T style={{ color: colors.muted, fontSize: 12 }}>{t('serverUrlHint')}</T>
          </Card>
        )}
      </Screen>
    </KeyboardAvoidingView>
  );
}
