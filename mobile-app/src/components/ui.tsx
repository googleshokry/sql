import { ReactNode } from 'react';
import {
  ActivityIndicator,
  Image,
  Pressable,
  RefreshControl,
  ScrollView,
  StyleProp,
  StyleSheet,
  Text,
  TextInput,
  TextInputProps,
  TextStyle,
  View,
  ViewStyle,
} from 'react-native';
import { SafeAreaView } from 'react-native-safe-area-context';
import { useI18n } from '../i18n/I18nProvider';
import { colors } from './theme';

/** Text that aligns to the reading direction of the current language. */
export function T({ style, children, ...rest }: { style?: StyleProp<TextStyle>; children: ReactNode; numberOfLines?: number }) {
  const { isRTL } = useI18n();
  return (
    <Text style={[styles.text, { textAlign: isRTL ? 'right' : 'left', writingDirection: isRTL ? 'rtl' : 'ltr' }, style]} {...rest}>
      {children}
    </Text>
  );
}

/** A horizontal row; flips automatically in RTL because the root view sets `direction`. */
export function Row({ children, style }: { children: ReactNode; style?: StyleProp<ViewStyle> }) {
  return <View style={[{ flexDirection: 'row', alignItems: 'center' }, style]}>{children}</View>;
}

export function Screen({
  children,
  refreshing,
  onRefresh,
  scroll = true,
}: {
  children: ReactNode;
  refreshing?: boolean;
  onRefresh?: () => void;
  scroll?: boolean;
}) {
  return (
    <SafeAreaView style={{ flex: 1, backgroundColor: colors.bg }} edges={['top']}>
      {scroll ? (
        <ScrollView
          contentContainerStyle={{ padding: 16, gap: 14, paddingBottom: 32 }}
          refreshControl={onRefresh ? <RefreshControl refreshing={!!refreshing} onRefresh={onRefresh} tintColor={colors.primary} /> : undefined}
        >
          {children}
        </ScrollView>
      ) : (
        <View style={{ flex: 1, padding: 16, gap: 14 }}>{children}</View>
      )}
    </SafeAreaView>
  );
}

export function Card({ children, style }: { children: ReactNode; style?: StyleProp<ViewStyle> }) {
  return <View style={[styles.card, style]}>{children}</View>;
}

export function Title({ children }: { children: ReactNode }) {
  return <T style={styles.title}>{children}</T>;
}

export function Button({
  title,
  onPress,
  variant = 'primary',
  loading,
  disabled,
  style,
}: {
  title: string;
  onPress: () => void;
  variant?: 'primary' | 'secondary' | 'danger';
  loading?: boolean;
  disabled?: boolean;
  style?: StyleProp<ViewStyle>;
}) {
  const bg = variant === 'primary' ? colors.primary : variant === 'danger' ? colors.danger : colors.card;
  const fg = variant === 'secondary' ? colors.text : '#fff';
  return (
    <Pressable
      accessibilityRole="button"
      onPress={onPress}
      disabled={disabled || loading}
      style={({ pressed }) => [
        styles.button,
        { backgroundColor: bg, opacity: pressed || disabled ? 0.7 : 1 },
        variant === 'secondary' && { borderWidth: 1, borderColor: colors.border },
        style,
      ]}
    >
      {loading ? <ActivityIndicator color={fg} /> : <Text style={[styles.buttonText, { color: fg }]}>{title}</Text>}
    </Pressable>
  );
}

export function Input({ label, ...props }: TextInputProps & { label: string }) {
  const { isRTL } = useI18n();
  return (
    <View style={{ gap: 6 }}>
      <T style={{ fontWeight: '600' }}>{label}</T>
      <TextInput
        placeholderTextColor={colors.muted}
        {...props}
        style={[styles.input, { textAlign: isRTL ? 'right' : 'left' }, props.style]}
      />
    </View>
  );
}

export function Badge({ label, color }: { label: string; color: string }) {
  return (
    <View style={[styles.badge, { backgroundColor: color + '1f', borderColor: color + '55' }]}>
      <Text style={{ color, fontWeight: '700', fontSize: 12 }}>{label}</Text>
    </View>
  );
}

export function Stat({ label, value, color }: { label: string; value: string | number; color?: string }) {
  return (
    <View style={styles.stat}>
      <Text style={styles.statLabel}>{label}</Text>
      <Text style={[styles.statValue, color ? { color } : null]}>{value}</Text>
    </View>
  );
}

export function ProgressBar({ value, color = colors.primary }: { value: number; color?: string }) {
  return (
    <View style={styles.progress}>
      <View style={{ width: `${Math.max(0, Math.min(1, value)) * 100}%`, backgroundColor: color, height: '100%', borderRadius: 99 }} />
    </View>
  );
}

export function Avatar({ name, uri, size = 56 }: { name: string; uri?: string | null; size?: number }) {
  if (uri) return <Image source={{ uri }} style={{ width: size, height: size, borderRadius: size / 2 }} />;
  const initials = name
    .split(/\s+/)
    .slice(0, 2)
    .map((p) => p[0])
    .join('');
  return (
    <View style={[styles.avatar, { width: size, height: size, borderRadius: size / 2 }]}>
      <Text style={{ color: '#fff', fontWeight: '800', fontSize: size / 2.6 }}>{initials}</Text>
    </View>
  );
}

export function Loading() {
  return (
    <View style={{ flex: 1, alignItems: 'center', justifyContent: 'center', padding: 40 }}>
      <ActivityIndicator size="large" color={colors.primary} />
    </View>
  );
}

export function ErrorBox({ message, onRetry, retryLabel }: { message: string; onRetry?: () => void; retryLabel: string }) {
  return (
    <Card style={{ alignItems: 'center', gap: 12 }}>
      <Text style={{ color: colors.danger, textAlign: 'center' }}>{message}</Text>
      {onRetry && <Button title={retryLabel} variant="secondary" onPress={onRetry} />}
    </Card>
  );
}

export function Empty({ text }: { text: string }) {
  return <Text style={{ color: colors.muted, textAlign: 'center', padding: 24 }}>{text}</Text>;
}

/** Two-option segmented control. */
export function Segmented<V extends string>({
  value,
  options,
  onChange,
}: {
  value: V;
  options: { value: V; label: string }[];
  onChange: (v: V) => void;
}) {
  return (
    <Row style={styles.segmented}>
      {options.map((o) => (
        <Pressable
          key={o.value}
          onPress={() => onChange(o.value)}
          style={[styles.segment, value === o.value && { backgroundColor: colors.card }]}
          accessibilityRole="tab"
          accessibilityState={{ selected: value === o.value }}
        >
          <Text style={{ fontWeight: '700', color: value === o.value ? colors.text : colors.muted }}>{o.label}</Text>
        </Pressable>
      ))}
    </Row>
  );
}

export const styles = StyleSheet.create({
  text: { color: colors.text, fontSize: 15 },
  title: { fontSize: 22, fontWeight: '800' },
  card: {
    backgroundColor: colors.card,
    borderRadius: 16,
    padding: 16,
    gap: 10,
    shadowColor: '#000',
    shadowOpacity: 0.05,
    shadowRadius: 8,
    shadowOffset: { width: 0, height: 2 },
    elevation: 1,
  },
  button: { height: 50, borderRadius: 12, alignItems: 'center', justifyContent: 'center', paddingHorizontal: 16 },
  buttonText: { fontWeight: '700', fontSize: 16 },
  input: {
    height: 50,
    borderWidth: 1,
    borderColor: colors.border,
    borderRadius: 12,
    paddingHorizontal: 14,
    backgroundColor: colors.card,
    fontSize: 16,
    color: colors.text,
  },
  badge: { paddingHorizontal: 10, paddingVertical: 3, borderRadius: 99, borderWidth: 1, alignSelf: 'flex-start' },
  stat: { flex: 1, backgroundColor: colors.bg, borderRadius: 12, padding: 12, alignItems: 'center', gap: 4 },
  statLabel: { color: colors.muted, fontSize: 12, textAlign: 'center' },
  statValue: { fontSize: 20, fontWeight: '800', color: colors.text },
  progress: { height: 8, backgroundColor: colors.border, borderRadius: 99, overflow: 'hidden' },
  avatar: { backgroundColor: colors.primary, alignItems: 'center', justifyContent: 'center' },
  segmented: { backgroundColor: colors.border, borderRadius: 12, padding: 4 },
  segment: { flex: 1, alignItems: 'center', paddingVertical: 10, borderRadius: 10 },
});
