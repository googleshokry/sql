import { useEffect, useState } from 'react';
import { View } from 'react-native';
import { CommonSettings } from '../../components/Settings';
import { colors } from '../../components/theme';
import { Avatar, Button, Card, Input, Row, Screen, T, Title } from '../../components/ui';
import { useI18n } from '../../i18n/I18nProvider';
import { api, ApiError } from '../../lib/api';
import { useAuth } from '../../lib/auth';
import { formatDate } from '../../lib/format';
import { Member } from '../../lib/types';
import { useApi } from '../../lib/useApi';

export default function Account() {
  const { t, lang } = useI18n();
  const { session, setMember } = useAuth();
  const me = useApi<{ data: Member }>('/member/me');
  const member = me.data?.data ?? (session?.type === 'member' ? session.member : null);

  const [current, setCurrent] = useState('');
  const [next, setNext] = useState('');
  const [confirm, setConfirm] = useState('');
  const [msg, setMsg] = useState<{ ok: boolean; text: string } | null>(null);
  const [busy, setBusy] = useState(false);

  // Keep the cached session (used by the card screen) in sync with the latest profile.
  useEffect(() => {
    if (me.data) setMember(me.data.data);
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [me.data]);
  if (!member) return null;

  const changePassword = async () => {
    setBusy(true);
    setMsg(null);
    try {
      const res = await api<{ message: string }>('/member/password', {
        method: 'POST',
        body: { current_password: current, password: next, password_confirmation: confirm },
      });
      setMsg({ ok: true, text: res.message });
      setCurrent('');
      setNext('');
      setConfirm('');
    } catch (e) {
      setMsg({ ok: false, text: e instanceof ApiError && e.status ? e.message : t('networkError') });
    } finally {
      setBusy(false);
    }
  };

  const info: [string, string][] = [
    [t('code'), member.code],
    [t('phone'), member.phone],
    [t('joined'), formatDate(member.joined_at, lang)],
    [t('weight'), member.weight ? `${member.weight} ${t('kg')}` : '-'],
    [t('height'), member.height ? `${member.height} ${t('cm')}` : '-'],
    [t('trainer'), member.trainer?.name ?? '-'],
  ];

  return (
    <Screen refreshing={me.refreshing} onRefresh={me.reload}>
      <Row style={{ gap: 12 }}>
        <Avatar name={member.name} uri={member.photo_url} size={64} />
        <View style={{ flex: 1 }}>
          <Title>{member.name}</Title>
          <T style={{ color: colors.muted }}>#{member.code}</T>
        </View>
      </Row>

      <Card>
        <T style={{ fontWeight: '800' }}>{t('profile')}</T>
        {info.map(([k, v]) => (
          <Row key={k} style={{ justifyContent: 'space-between', borderBottomWidth: 1, borderColor: colors.border, paddingVertical: 8 }}>
            <T style={{ color: colors.muted }}>{k}</T>
            <T style={{ fontWeight: '700' }}>{v}</T>
          </Row>
        ))}
      </Card>

      <CommonSettings />

      <Card>
        <T style={{ fontWeight: '800' }}>{t('changePassword')}</T>
        <Input label={t('currentPassword')} value={current} onChangeText={setCurrent} secureTextEntry />
        <Input label={t('newPassword')} value={next} onChangeText={setNext} secureTextEntry />
        <Input label={t('confirmPassword')} value={confirm} onChangeText={setConfirm} secureTextEntry />
        {msg && <T style={{ color: msg.ok ? colors.success : colors.danger }}>{msg.text}</T>}
        <Button title={t('save')} onPress={changePassword} loading={busy} disabled={!current || !next || !confirm} />
      </Card>
    </Screen>
  );
}
