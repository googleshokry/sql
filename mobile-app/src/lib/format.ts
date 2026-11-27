import { Lang } from '../i18n/translations';

const locale = (lang: Lang) => (lang === 'ar' ? 'ar-EG' : 'en-GB');

export function formatDate(value: string | null | undefined, lang: Lang): string {
  if (!value) return '-';
  const d = new Date(value.length === 10 ? `${value}T00:00:00` : value);
  return d.toLocaleDateString(locale(lang), { day: 'numeric', month: 'short', year: 'numeric' });
}

export function formatTime(value: string | null | undefined, lang: Lang): string {
  if (!value) return '-';
  return new Date(value).toLocaleTimeString(locale(lang), { hour: 'numeric', minute: '2-digit', hour12: true });
}

export function formatMoney(value: number, currency: string, lang: Lang): string {
  return `${value.toLocaleString(locale(lang), { maximumFractionDigits: 2 })} ${currency}`;
}

export function formatNumber(value: number, lang: Lang): string {
  return value.toLocaleString(locale(lang));
}
