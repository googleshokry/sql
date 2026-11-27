import { colors } from '../../components/theme';
import { Avatar, Button, Card, Empty, ErrorBox, Loading, Row, Screen, T, Title } from '../../components/ui';
import { View } from 'react-native';
import { useI18n } from '../../i18n/I18nProvider';
import { api } from '../../lib/api';
import { formatTime } from '../../lib/format';
import { Attendance } from '../../lib/types';
import { useApi } from '../../lib/useApi';

export default function Inside() {
  const { t, lang } = useI18n();
  const list = useApi<{ data: Attendance[] }>('/staff/inside');

  if (list.loading && !list.data) return <Loading />;
  if (list.error && !list.data) {
    return (
      <Screen>
        <ErrorBox message={list.error.status ? list.error.message : t('networkError')} onRetry={list.reload} retryLabel={t('retry')} />
      </Screen>
    );
  }

  const checkout = async (id: number) => {
    await api(`/staff/attendances/${id}/checkout`, { method: 'POST' }).catch(() => undefined);
    list.reload();
  };

  return (
    <Screen refreshing={list.refreshing} onRefresh={list.reload}>
      <Title>
        {t('inside')} ({list.data!.data.length})
      </Title>
      {list.data!.data.length === 0 && <Empty text={t('nobodyInside')} />}
      {list.data!.data.map((a) => (
        <Card key={a.id} style={{ paddingVertical: 12 }}>
          <Row style={{ gap: 12 }}>
            <Avatar name={a.member?.name ?? '?'} uri={a.member?.photo_url} size={44} />
            <View style={{ flex: 1 }}>
              <T style={{ fontWeight: '800' }}>{a.member?.name}</T>
              <T style={{ color: colors.muted, fontSize: 13 }}>
                #{a.member?.code} • {t('since')} {formatTime(a.check_in_at, lang)}
                {a.is_guest ? ` • ${a.guest_name}` : ''}
              </T>
            </View>
            <Button title={t('checkOut')} variant="secondary" onPress={() => checkout(a.id)} style={{ height: 40 }} />
          </Row>
        </Card>
      ))}
    </Screen>
  );
}
