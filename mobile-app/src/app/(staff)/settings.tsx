import { CommonSettings } from '../../components/Settings';
import { colors } from '../../components/theme';
import { Card, Screen, T, Title } from '../../components/ui';
import { useI18n } from '../../i18n/I18nProvider';
import { apiConfig } from '../../lib/api';
import { useAuth } from '../../lib/auth';

export default function StaffSettings() {
  const { t } = useI18n();
  const { session } = useAuth();
  if (session?.type !== 'staff') return null;

  return (
    <Screen>
      <Title>{t('settings')}</Title>
      <Card>
        <T style={{ fontWeight: '800' }}>{session.user.name}</T>
        <T style={{ color: colors.muted }}>
          {session.user.email} — {session.user.role_label}
        </T>
        <T style={{ color: colors.muted, fontSize: 12 }}>
          {t('serverUrl')}: {apiConfig.getBaseUrl()}
        </T>
      </Card>
      <CommonSettings />
    </Screen>
  );
}
