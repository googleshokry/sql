import { getLocales } from 'expo-localization';
import { createContext, ReactNode, useCallback, useContext, useEffect, useMemo, useState } from 'react';
import { apiConfig } from '../lib/api';
import { storage } from '../lib/storage';
import { Lang, TKey, translations } from './translations';

type I18n = {
  lang: Lang;
  isRTL: boolean;
  t: (key: TKey) => string;
  setLang: (lang: Lang) => void;
  ready: boolean;
};

const I18nContext = createContext<I18n | null>(null);
const KEY = 'lang';

/** Language used before the user picks one: the device language if supported, otherwise Arabic. */
function deviceLang(): Lang {
  const code = getLocales()[0]?.languageCode;
  return code === 'en' ? 'en' : 'ar';
}

export function I18nProvider({ children }: { children: ReactNode }) {
  const [lang, setLangState] = useState<Lang>(deviceLang());
  const [ready, setReady] = useState(false);

  // Set synchronously (not in an effect) so requests fired by child effects already use the new language.
  apiConfig.setLang(lang);

  useEffect(() => {
    storage.get(KEY).then((saved) => {
      if (saved === 'ar' || saved === 'en') setLangState(saved);
      setReady(true);
    });
  }, []);

  const setLang = useCallback((next: Lang) => {
    setLangState(next);
    storage.set(KEY, next);
  }, []);

  const value = useMemo<I18n>(
    () => ({
      lang,
      isRTL: lang === 'ar',
      t: (key) => translations[lang][key] ?? key,
      setLang,
      ready,
    }),
    [lang, setLang, ready],
  );

  return <I18nContext.Provider value={value}>{children}</I18nContext.Provider>;
}

export function useI18n(): I18n {
  const ctx = useContext(I18nContext);
  if (!ctx) throw new Error('useI18n must be used inside I18nProvider');
  return ctx;
}
