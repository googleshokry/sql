import { CameraView, useCameraPermissions } from 'expo-camera';
import { useIsFocused } from 'expo-router';
import { useCallback, useRef, useState } from 'react';
import { Platform, View } from 'react-native';
import { MemberSummary } from '../../components/MemberSummary';
import { colors } from '../../components/theme';
import { Button, Card, Input, Row, Screen, Stat, T } from '../../components/ui';
import { useI18n } from '../../i18n/I18nProvider';
import { api, ApiError } from '../../lib/api';
import { useAuth } from '../../lib/auth';
import { formatMoney } from '../../lib/format';
import { CheckInResult } from '../../lib/types';
import { useApi } from '../../lib/useApi';
import { useGym } from '../../lib/useGym';

type Dashboard = { active_members: number; inside_now: number; visits_today: number; expiring_soon: number; income_today?: number };

export default function Scan() {
  const { t, lang } = useI18n();
  const { session } = useAuth();
  const gym = useGym();
  const focused = useIsFocused();
  const [permission, requestPermission] = useCameraPermissions();
  const [code, setCode] = useState('');
  const [busy, setBusy] = useState(false);
  const [result, setResult] = useState<CheckInResult | null>(null);
  const lastScan = useRef<{ code: string; at: number }>({ code: '', at: 0 });
  const dashboard = useApi<Dashboard>('/staff/dashboard');

  const submit = useCallback(
    async (value: string, force = false) => {
      const c = value.trim();
      if (!c || busy) return;
      setBusy(true);
      try {
        setResult(await api<CheckInResult>('/staff/checkin', { method: 'POST', body: { code: c, force } }));
      } catch (e) {
        if (e instanceof ApiError && e.body && typeof e.body === 'object' && 'message' in e.body) {
          setResult(e.body as CheckInResult);
        } else {
          setResult({ ok: false, action: null, message: t('networkError'), member: null });
        }
      } finally {
        setBusy(false);
        setCode('');
        dashboard.reload();
      }
    },
    [busy, t, dashboard],
  );

  const onScanned = ({ data }: { data: string }) => {
    // The camera fires many times per second; ignore repeats of the same code for a few seconds.
    const now = Date.now();
    if (result || busy || (data === lastScan.current.code && now - lastScan.current.at < 4000)) return;
    lastScan.current = { code: data, at: now };
    submit(data);
  };

  const d = dashboard.data;
  const tone = result ? (result.ok ? (result.action === 'out' ? colors.info : colors.success) : colors.danger) : colors.text;

  return (
    <Screen refreshing={dashboard.refreshing} onRefresh={dashboard.reload}>
      {session?.type === 'staff' && (
        <T style={{ color: colors.muted }}>
          {t('signedInAs')} {session.user.name} — {session.user.role_label}
        </T>
      )}
      {d && (
        <View style={{ flexDirection: 'row', gap: 8, flexWrap: 'wrap' }}>
          <Stat label={t('inside')} value={d.inside_now} color={colors.success} />
          <Stat label={t('visitsToday')} value={d.visits_today} />
          <Stat label={t('activeMembers')} value={d.active_members} />
          <Stat label={t('expiringSoon')} value={d.expiring_soon} color={colors.warning} />
          {d.income_today !== undefined && <Stat label={t('incomeToday')} value={formatMoney(d.income_today, gym?.currency ?? '', lang)} />}
        </View>
      )}

      {result ? (
        <Card style={{ borderWidth: 2, borderColor: tone }}>
          <T style={{ color: tone, fontWeight: '900', fontSize: 18 }}>
            {result.ok ? (result.action === 'out' ? '👋 ' : '✅ ') : '⛔ '}
            {result.message}
          </T>
          {result.member && <MemberSummary member={result.member} currency={gym?.currency ?? ''} />}
          {result.can_force && result.member && (
            <Button title={t('allowException')} variant="danger" onPress={() => submit(result.member!.code, true)} loading={busy} />
          )}
          <Button title={t('scanAgain')} onPress={() => setResult(null)} />
        </Card>
      ) : (
        <Card style={{ padding: 0, overflow: 'hidden' }}>
          {Platform.OS !== 'web' && permission?.granted && focused ? (
            <View style={{ height: 300 }}>
              <CameraView
                style={{ flex: 1 }}
                facing="back"
                barcodeScannerSettings={{ barcodeTypes: ['qr', 'code128', 'code39', 'ean13'] }}
                onBarcodeScanned={onScanned}
              />
              <View pointerEvents="none" style={{ position: 'absolute', top: 60, left: 60, right: 60, bottom: 60, borderWidth: 3, borderColor: colors.primary, borderRadius: 20 }} />
            </View>
          ) : Platform.OS !== 'web' ? (
            <View style={{ padding: 16, gap: 10 }}>
              <T>{t('cameraPermission')}</T>
              <Button title={t('grantPermission')} onPress={requestPermission} />
            </View>
          ) : null}
          <T style={{ color: colors.muted, padding: 12, textAlign: 'center' }}>{t('scanHint')}</T>
        </Card>
      )}

      <Card>
        <Input
          label={t('manualCode')}
          value={code}
          onChangeText={setCode}
          onSubmitEditing={() => submit(code)}
          autoCapitalize="none"
          returnKeyType="go"
          testID="manual-code"
        />
        <Row>
          <Button title={t('submit')} onPress={() => submit(code)} loading={busy} disabled={!code.trim()} style={{ flex: 1 }} />
        </Row>
      </Card>
    </Screen>
  );
}
