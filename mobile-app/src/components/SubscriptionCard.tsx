import { View } from 'react-native';
import { useI18n } from '../i18n/I18nProvider';
import { formatDate, formatMoney } from '../lib/format';
import { Subscription } from '../lib/types';
import { colors, statusColors } from './theme';
import { Badge, Card, ProgressBar, Row, Stat, T } from './ui';

export function SubscriptionCard({ sub, currency }: { sub: Subscription; currency: string }) {
  const { t, lang } = useI18n();
  const color = statusColors[sub.status] ?? colors.muted;
  const usedRatio = sub.sessions_total ? sub.sessions_used / sub.sessions_total : 1 - sub.days_left / Math.max(1, sub.total_days);

  return (
    <Card>
      <Row style={{ justifyContent: 'space-between' }}>
        <T style={{ fontSize: 18, fontWeight: '800', flexShrink: 1 }}>{sub.plan}</T>
        <Badge label={sub.status_label} color={color} />
      </Row>

      <Row style={{ gap: 6, alignItems: 'baseline' }}>
        <T style={{ fontSize: 40, fontWeight: '900', color }}>{sub.days_left}</T>
        <T style={{ color: colors.muted }}>{t('daysLeft')}</T>
      </Row>
      <ProgressBar value={usedRatio} color={color} />
      <Row style={{ justifyContent: 'space-between' }}>
        <T style={{ color: colors.muted, fontSize: 13 }}>
          {t('startedOn')} {formatDate(sub.start_date, lang)}
        </T>
        <T style={{ color: colors.muted, fontSize: 13 }}>
          {t('endsOn')} {formatDate(sub.end_date, lang)}
        </T>
      </Row>

      <View style={{ flexDirection: 'row', gap: 8, marginTop: 4 }}>
        <Stat label={t('sessions')} value={sub.sessions_total ? `${sub.sessions_used}/${sub.sessions_total}` : t('unlimited')} />
        <Stat label={t('freezeDays')} value={`${sub.freeze_days_used}/${sub.freeze_days_allowed}`} />
        <Stat label={t('invitations')} value={`${sub.guest_invitations - sub.guest_invitations_used}/${sub.guest_invitations}`} />
      </View>

      {sub.remaining > 0 && (
        <Row style={{ justifyContent: 'space-between', backgroundColor: '#fef2f2', padding: 10, borderRadius: 10 }}>
          <T style={{ color: colors.danger, fontWeight: '700' }}>{t('remaining')}</T>
          <T style={{ color: colors.danger, fontWeight: '800' }}>{formatMoney(sub.remaining, currency, lang)}</T>
        </Row>
      )}
      {sub.trainer && (
        <T style={{ color: colors.muted }}>
          {t('trainer')}: {sub.trainer}
        </T>
      )}
    </Card>
  );
}
