import { Image, View } from 'react-native';
import { SubscriptionCard } from '../../components/SubscriptionCard';
import { colors } from '../../components/theme';
import { Avatar, Card, Empty, ErrorBox, Loading, Row, Screen, Stat, T, Title } from '../../components/ui';
import { useI18n } from '../../i18n/I18nProvider';
import { formatDate } from '../../lib/format';
import { Announcement, HomeData } from '../../lib/types';
import { useApi } from '../../lib/useApi';
import { useGym } from '../../lib/useGym';

export default function Home() {
  const { t, lang } = useI18n();
  const gym = useGym();
  const home = useApi<HomeData>('/member/home');
  const news = useApi<{ data: Announcement[] }>('/announcements');

  if (home.loading && !home.data) return <Loading />;

  const reload = () => {
    home.reload();
    news.reload();
  };

  if (home.error && !home.data) {
    return (
      <Screen>
        <ErrorBox message={home.error.status ? home.error.message : t('networkError')} onRetry={reload} retryLabel={t('retry')} />
      </Screen>
    );
  }

  const { member, stats } = home.data!;
  const maxVisits = Math.max(1, ...stats.visits_last_7_days.map((d) => d.count));

  return (
    <Screen refreshing={home.refreshing} onRefresh={reload}>
      <Row style={{ gap: 12 }}>
        <Avatar name={member.name} uri={member.photo_url} />
        <View style={{ flex: 1 }}>
          <T style={{ color: colors.muted }}>{t('hello')} 👋</T>
          <Title>{member.name}</Title>
          {gym && <T style={{ color: colors.muted, fontSize: 13 }}>{gym.name}</T>}
        </View>
      </Row>

      {member.is_inside && (
        <Card style={{ backgroundColor: '#dcfce7' }}>
          <T style={{ color: colors.success, fontWeight: '800' }}>✅ {t('insideNow')}</T>
        </Card>
      )}

      <T style={{ fontWeight: '800', fontSize: 17 }}>{t('yourSubscription')}</T>
      {member.subscription ? (
        <SubscriptionCard sub={member.subscription} currency={gym?.currency ?? ''} />
      ) : (
        <Card>
          <T style={{ color: colors.muted }}>{t('noSubscription')}</T>
        </Card>
      )}

      <Card>
        <View style={{ flexDirection: 'row', gap: 8 }}>
          <Stat label={t('visitsThisMonth')} value={stats.visits_this_month} />
          <Stat label={t('visitsTotal')} value={stats.visits_total} />
        </View>
        <T style={{ color: colors.muted, fontSize: 13 }}>
          {t('lastVisit')}: {formatDate(stats.last_visit, lang)}
        </T>
        <T style={{ fontWeight: '700' }}>{t('last7Days')}</T>
        <View style={{ flexDirection: 'row', alignItems: 'flex-end', gap: 6, height: 80 }}>
          {stats.visits_last_7_days.map((d) => (
            <View key={d.date} style={{ flex: 1, alignItems: 'center', gap: 4 }}>
              <View
                style={{
                  width: '70%',
                  height: Math.max(4, (d.count / maxVisits) * 56),
                  backgroundColor: d.count ? colors.primary : colors.border,
                  borderRadius: 6,
                }}
              />
              <T style={{ fontSize: 10, color: colors.muted, textAlign: 'center' }}>
                {new Date(`${d.date}T00:00:00`).toLocaleDateString(lang === 'ar' ? 'ar-EG' : 'en-GB', { weekday: 'short' })}
              </T>
            </View>
          ))}
        </View>
      </Card>

      <T style={{ fontWeight: '800', fontSize: 17 }}>{t('news')}</T>
      {news.data?.data.length ? (
        news.data.data.map((a) => (
          <Card key={a.id}>
            {a.image_url && <Image source={{ uri: a.image_url }} style={{ width: '100%', height: 160, borderRadius: 12 }} resizeMode="cover" />}
            <T style={{ fontWeight: '800', fontSize: 16 }}>{a.title}</T>
            {a.body && <T style={{ color: colors.muted }}>{a.body}</T>}
            <T style={{ color: colors.muted, fontSize: 12 }}>{formatDate(a.published_at, lang)}</T>
          </Card>
        ))
      ) : (
        <Empty text={t('noData')} />
      )}
    </Screen>
  );
}
