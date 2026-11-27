import { View } from 'react-native';
import { useI18n } from '../i18n/I18nProvider';
import { formatDate, formatMoney } from '../lib/format';
import { Member } from '../lib/types';
import { colors, statusColors } from './theme';
import { Avatar, Badge, Row, T } from './ui';

export function MemberSummary({ member, currency }: { member: Member; currency: string }) {
  const { t, lang } = useI18n();
  const sub = member.subscription;
  return (
    <View style={{ gap: 8 }}>
      <Row style={{ gap: 12 }}>
        <Avatar name={member.name} uri={member.photo_url} />
        <View style={{ flex: 1, gap: 2 }}>
          <T style={{ fontWeight: '800', fontSize: 17 }}>{member.name}</T>
          <T style={{ color: colors.muted, fontSize: 13 }}>
            #{member.code} • {member.phone}
          </T>
          <Row style={{ gap: 6, flexWrap: 'wrap' }}>
            {sub && <Badge label={sub.status_label} color={statusColors[sub.status] ?? colors.muted} />}
            {member.is_blocked && <Badge label={t('blocked')} color={colors.danger} />}
            {member.is_inside && <Badge label={t('stillInside')} color={colors.success} />}
          </Row>
        </View>
      </Row>
      {sub && (
        <T style={{ color: colors.muted, fontSize: 13 }}>
          {sub.plan} — {t('endsOn')} {formatDate(sub.end_date, lang)} ({sub.days_left} {t('days')})
          {sub.sessions_total ? ` • ${t('sessions')} ${sub.sessions_used}/${sub.sessions_total}` : ''}
        </T>
      )}
      {member.balance > 0 && (
        <T style={{ color: colors.danger, fontWeight: '700' }}>
          {t('balance')}: {formatMoney(member.balance, currency, lang)}
        </T>
      )}
    </View>
  );
}
