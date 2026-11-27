import { useEffect, useState } from 'react';
import { MemberSummary } from '../../components/MemberSummary';
import { Button, Card, Empty, Input, Loading, Screen } from '../../components/ui';
import { useI18n } from '../../i18n/I18nProvider';
import { api } from '../../lib/api';
import { CheckInResult, Member, Paginated } from '../../lib/types';
import { useApi } from '../../lib/useApi';
import { useGym } from '../../lib/useGym';

export default function Members() {
  const { t } = useI18n();
  const gym = useGym();
  const [search, setSearch] = useState('');
  const [query, setQuery] = useState('');

  // Debounce typing so we don't hit the server on every keystroke.
  useEffect(() => {
    const id = setTimeout(() => setQuery(search.trim()), 400);
    return () => clearTimeout(id);
  }, [search]);

  const list = useApi<Paginated<Member>>(`/staff/members?search=${encodeURIComponent(query)}`);

  const toggle = async (code: string) => {
    const res = await api<CheckInResult>('/staff/checkin', { method: 'POST', body: { code } }).catch((e) => e.body as CheckInResult);
    if (res?.message) globalThis.alert?.(res.message);
    list.reload();
  };

  return (
    <Screen refreshing={list.refreshing} onRefresh={list.reload}>
      <Input label={t('search')} value={search} onChangeText={setSearch} autoCapitalize="none" testID="member-search" />
      {list.loading && !list.data ? (
        <Loading />
      ) : list.data?.data.length ? (
        list.data.data.map((m) => (
          <Card key={m.id}>
            <MemberSummary member={m} currency={gym?.currency ?? ''} />
            <Button title={m.is_inside ? t('checkOut') : t('checkIn')} variant={m.is_inside ? 'secondary' : 'primary'} onPress={() => toggle(m.code)} />
          </Card>
        ))
      ) : (
        <Empty text={t('noData')} />
      )}
    </Screen>
  );
}
