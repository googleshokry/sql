import { useCallback, useEffect, useState } from 'react';
import { useI18n } from '../i18n/I18nProvider';
import { api, ApiError } from './api';

const toError = (e: unknown) => (e instanceof ApiError ? e : new ApiError(String(e), 0));

/** Fetch a GET endpoint; refetches when the language changes so server labels follow it. */
export function useApi<T>(path: string | null) {
  const { lang } = useI18n();
  const [data, setData] = useState<T | null>(null);
  const [error, setError] = useState<ApiError | null>(null);
  const [loading, setLoading] = useState(true);
  const [refreshing, setRefreshing] = useState(false);

  useEffect(() => {
    if (!path) return;
    let cancelled = false;
    api<T>(path)
      .then((d) => {
        if (cancelled) return;
        setData(d);
        setError(null);
      })
      .catch((e) => !cancelled && setError(toError(e)))
      .finally(() => !cancelled && setLoading(false));
    return () => {
      cancelled = true;
    };
  }, [path, lang]);

  const reload = useCallback(async () => {
    if (!path) return;
    setRefreshing(true);
    try {
      setData(await api<T>(path));
      setError(null);
    } catch (e) {
      setError(toError(e));
    } finally {
      setRefreshing(false);
    }
  }, [path]);

  return { data, error, loading, refreshing, reload, setData };
}
