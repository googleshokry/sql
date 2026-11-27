import { useEffect, useState } from 'react';
import { useI18n } from '../i18n/I18nProvider';
import { api, apiConfig } from './api';
import { Gym } from './types';

let cache: { key: string; gym: Gym } | null = null;

/** Gym info (name, currency, contacts) shared across screens. */
export function useGym(): Gym | null {
  const { lang } = useI18n();
  const key = `${apiConfig.getGym()}:${lang}`;
  const [gym, setGym] = useState<Gym | null>(cache?.key === key ? cache.gym : null);

  useEffect(() => {
    if (cache?.key === key) return;
    api<Gym>('/gym')
      .then((g) => {
        cache = { key, gym: g };
        setGym(g);
      })
      .catch(() => undefined);
  }, [key]);

  return gym;
}
