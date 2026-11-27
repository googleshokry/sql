import { useEffect, useState } from 'react';
import { useI18n } from '../i18n/I18nProvider';
import { api } from './api';
import { Gym } from './types';

let cache: { lang: string; gym: Gym } | null = null;

/** Gym info (name, currency, contacts) shared across screens. */
export function useGym(): Gym | null {
  const { lang } = useI18n();
  const [gym, setGym] = useState<Gym | null>(cache?.lang === lang ? cache.gym : null);

  useEffect(() => {
    if (cache?.lang === lang) return;
    api<Gym>('/gym')
      .then((g) => {
        cache = { lang, gym: g };
        setGym(g);
      })
      .catch(() => undefined);
  }, [lang]);

  return gym;
}
