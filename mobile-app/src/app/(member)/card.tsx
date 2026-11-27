import { View } from 'react-native';
import QRCode from 'react-native-qrcode-svg';
import { colors, statusColors } from '../../components/theme';
import { Avatar, Badge, Card, Screen, T } from '../../components/ui';
import { useI18n } from '../../i18n/I18nProvider';
import { useAuth } from '../../lib/auth';
import { formatDate } from '../../lib/format';
import { useGym } from '../../lib/useGym';

/** Digital membership card: the QR holds the membership code, exactly like the printed card's barcode. */
export default function MemberCard() {
  const { t, lang } = useI18n();
  const { session } = useAuth();
  const gym = useGym();
  if (session?.type !== 'member') return null;
  const m = session.member;
  const sub = m.subscription;

  return (
    <Screen>
      <Card style={{ backgroundColor: colors.dark, alignItems: 'center', gap: 14, paddingVertical: 24 }}>
        <T style={{ color: colors.primary, fontWeight: '900', fontSize: 18, textAlign: 'center' }}>{gym?.name ?? t('appName')}</T>
        <Avatar name={m.name} uri={m.photo_url} size={72} />
        <T style={{ color: '#fff', fontSize: 20, fontWeight: '800', textAlign: 'center' }}>{m.name}</T>
        {sub && <Badge label={sub.status_label} color={statusColors[sub.status] ?? colors.muted} />}
        <View style={{ backgroundColor: '#fff', padding: 16, borderRadius: 16 }} testID="member-qr">
          <QRCode value={m.code} size={220} />
        </View>
        <T style={{ color: '#fff', letterSpacing: 4, fontSize: 22, fontWeight: '800', textAlign: 'center' }}>{m.code}</T>
        {sub && (
          <T style={{ color: '#d1d5db', textAlign: 'center' }}>
            {sub.plan} — {t('endsOn')} {formatDate(sub.end_date, lang)}
          </T>
        )}
      </Card>
      <T style={{ color: colors.muted, textAlign: 'center' }}>{t('showCard')}</T>
    </Screen>
  );
}
