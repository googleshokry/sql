import { useState } from 'react';
import { View } from 'react-native';
import { SubscriptionCard } from '../../components/SubscriptionCard';
import { colors } from '../../components/theme';
import { Card, Empty, ErrorBox, Loading, Row, Screen, Segmented, T } from '../../components/ui';
import { useI18n } from '../../i18n/I18nProvider';
import { formatDate, formatMoney, formatTime } from '../../lib/format';
import { Attendance, Measurement, Paginated, Payment, Subscription } from '../../lib/types';
import { useApi } from '../../lib/useApi';
import { useGym } from '../../lib/useGym';

type Tab = 'attendance' | 'payments' | 'subscriptions' | 'measurements';

export default function History() {
  const { t } = useI18n();
  const [tab, setTab] = useState<Tab>('attendance');

  return (
    <Screen scroll={false}>
      <Segmented
        value={tab}
        onChange={setTab}
        options={[
          { value: 'attendance', label: t('attendance') },
          { value: 'payments', label: t('payments') },
          { value: 'subscriptions', label: t('subscriptions') },
          { value: 'measurements', label: t('measurements') },
        ]}
      />
      <View style={{ flex: 1, marginHorizontal: -16 }}>
        {tab === 'attendance' && <AttendanceList />}
        {tab === 'payments' && <PaymentList />}
        {tab === 'subscriptions' && <SubscriptionList />}
        {tab === 'measurements' && <MeasurementList />}
      </View>
    </Screen>
  );
}

function useList<T>(path: string) {
  const { t } = useI18n();
  const res = useApi<T>(path);
  const fallback = res.loading && !res.data ? <Loading /> : res.error && !res.data ? (
    <ErrorBox message={res.error.status ? res.error.message : t('networkError')} onRetry={res.reload} retryLabel={t('retry')} />
  ) : null;
  return { ...res, fallback };
}

function AttendanceList() {
  const { t, lang } = useI18n();
  const { data, fallback, refreshing, reload } = useList<Paginated<Attendance>>('/member/attendances');
  if (fallback) return fallback;
  return (
    <Screen refreshing={refreshing} onRefresh={reload}>
      {data!.data.length === 0 && <Empty text={t('noData')} />}
      {data!.data.map((a) => (
        <Card key={a.id} style={{ paddingVertical: 12 }}>
          <Row style={{ justifyContent: 'space-between' }}>
            <T style={{ fontWeight: '700' }}>{formatDate(a.check_in_at, lang)}</T>
            <T style={{ color: a.check_out_at ? colors.muted : colors.success, fontWeight: '700' }}>
              {a.duration ?? t('stillInside')}
            </T>
          </Row>
          <T style={{ color: colors.muted, fontSize: 13 }}>
            {t('checkIn')} {formatTime(a.check_in_at, lang)}
            {a.check_out_at ? ` — ${t('checkOut')} ${formatTime(a.check_out_at, lang)}` : ''}
          </T>
        </Card>
      ))}
    </Screen>
  );
}

function PaymentList() {
  const { t, lang } = useI18n();
  const gym = useGym();
  const { data, fallback, refreshing, reload } = useList<Paginated<Payment>>('/member/payments');
  if (fallback) return fallback;
  return (
    <Screen refreshing={refreshing} onRefresh={reload}>
      {data!.data.length === 0 && <Empty text={t('noData')} />}
      {data!.data.map((p) => (
        <Card key={p.id} style={{ paddingVertical: 12 }}>
          <Row style={{ justifyContent: 'space-between' }}>
            <T style={{ fontWeight: '800', color: colors.success }}>{formatMoney(p.amount, gym?.currency ?? '', lang)}</T>
            <T style={{ color: colors.muted }}>{formatDate(p.paid_at, lang)}</T>
          </Row>
          <T style={{ color: colors.muted, fontSize: 13 }}>
            {p.type_label}
            {p.plan ? ` — ${p.plan}` : ''} • {p.method_label} • {t('receipt')} {p.receipt_number}
          </T>
        </Card>
      ))}
    </Screen>
  );
}

function SubscriptionList() {
  const { t } = useI18n();
  const gym = useGym();
  const { data, fallback, refreshing, reload } = useList<{ data: Subscription[] }>('/member/subscriptions');
  if (fallback) return fallback;
  return (
    <Screen refreshing={refreshing} onRefresh={reload}>
      {data!.data.length === 0 && <Empty text={t('noData')} />}
      {data!.data.map((s) => (
        <SubscriptionCard key={s.id} sub={s} currency={gym?.currency ?? ''} />
      ))}
    </Screen>
  );
}

function MeasurementList() {
  const { t, lang } = useI18n();
  const { data, fallback, refreshing, reload } = useList<{ data: Measurement[] }>('/member/measurements');
  if (fallback) return fallback;
  const fmt = (v: number | null, unit: string) => (v === null ? '-' : `${v} ${unit}`);
  return (
    <Screen refreshing={refreshing} onRefresh={reload}>
      {data!.data.length === 0 && <Empty text={t('noData')} />}
      {data!.data.map((m) => (
        <Card key={m.id}>
          <T style={{ fontWeight: '800' }}>{formatDate(m.measured_at, lang)}</T>
          <View style={{ flexDirection: 'row', gap: 8, flexWrap: 'wrap' }}>
            <Cell label={t('weight')} value={fmt(m.weight, t('kg'))} />
            <Cell label={t('bmi')} value={m.bmi === null ? '-' : String(m.bmi)} />
            <Cell label={t('bodyFat')} value={fmt(m.body_fat, '%')} />
            <Cell label={t('muscle')} value={fmt(m.muscle_mass, t('kg'))} />
          </View>
          {m.notes && <T style={{ color: colors.muted }}>{m.notes}</T>}
        </Card>
      ))}
    </Screen>
  );
}

function Cell({ label, value }: { label: string; value: string }) {
  return (
    <View style={{ flexGrow: 1, flexBasis: '45%', backgroundColor: colors.bg, borderRadius: 10, padding: 10 }}>
      <T style={{ color: colors.muted, fontSize: 12 }}>{label}</T>
      <T style={{ fontWeight: '800' }}>{value}</T>
    </View>
  );
}
