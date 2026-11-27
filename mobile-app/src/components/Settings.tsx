import { Alert, Linking, Platform } from 'react-native';
import { useI18n } from '../i18n/I18nProvider';
import { useAuth } from '../lib/auth';
import { useGym } from '../lib/useGym';
import { colors } from './theme';
import { Button, Card, Row, Segmented, T } from './ui';

/** Language switch, gym contact info and sign-out — shared by member and staff screens. */
export function CommonSettings() {
  const { t, lang, setLang } = useI18n();
  const { logout } = useAuth();
  const gym = useGym();

  const confirmLogout = () => {
    if (Platform.OS === 'web') {
      if (globalThis.confirm?.(t('logoutConfirm')) ?? true) logout();
      return;
    }
    Alert.alert(t('logout'), t('logoutConfirm'), [
      { text: t('cancel'), style: 'cancel' },
      { text: t('logout'), style: 'destructive', onPress: logout },
    ]);
  };

  return (
    <>
      <Card>
        <T style={{ fontWeight: '800' }}>{t('language')}</T>
        <Segmented
          value={lang}
          onChange={setLang}
          options={[
            { value: 'ar', label: 'العربية' },
            { value: 'en', label: 'English' },
          ]}
        />
      </Card>

      {gym && (
        <Card>
          <T style={{ fontWeight: '800' }}>{t('gymInfo')}</T>
          <T style={{ fontSize: 17, fontWeight: '700' }}>{gym.name}</T>
          {!!gym.about && <T style={{ color: colors.muted }}>{gym.about}</T>}
          {!!gym.working_hours && (
            <T>
              🕒 {t('workingHours')}: {gym.working_hours}
            </T>
          )}
          {!!gym.address && <T>📍 {gym.address}</T>}
          <Row style={{ gap: 8, flexWrap: 'wrap' }}>
            {!!gym.phone && <Button title={`📞 ${t('call')}`} variant="secondary" onPress={() => Linking.openURL(`tel:${gym.phone}`)} />}
            {!!gym.location_url && <Button title={`🗺️ ${t('openMap')}`} variant="secondary" onPress={() => Linking.openURL(gym.location_url!)} />}
            {!!gym.instagram && <Button title="Instagram" variant="secondary" onPress={() => Linking.openURL(gym.instagram!)} />}
            {!!gym.facebook && <Button title="Facebook" variant="secondary" onPress={() => Linking.openURL(gym.facebook!)} />}
          </Row>
        </Card>
      )}

      <Button title={t('logout')} variant="danger" onPress={confirmLogout} />
    </>
  );
}
