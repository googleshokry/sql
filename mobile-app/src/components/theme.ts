export const colors = {
  primary: '#f59e0b',
  primaryDark: '#b45309',
  dark: '#111827',
  text: '#111827',
  muted: '#6b7280',
  border: '#e5e7eb',
  bg: '#f3f4f6',
  card: '#ffffff',
  success: '#16a34a',
  danger: '#dc2626',
  info: '#0284c7',
  warning: '#d97706',
};

export const statusColors: Record<string, string> = {
  active: colors.success,
  upcoming: colors.info,
  frozen: colors.info,
  expired: colors.danger,
  cancelled: colors.muted,
};
