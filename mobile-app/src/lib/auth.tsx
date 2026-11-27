import { createContext, ReactNode, useCallback, useContext, useEffect, useMemo, useState } from 'react';
import { api, apiConfig } from './api';
import { storage } from './storage';
import { Member, StaffUser } from './types';

type Session =
  | { type: 'member'; token: string; member: Member }
  | { type: 'staff'; token: string; user: StaffUser };

type Auth = {
  session: Session | null;
  loading: boolean;
  loginMember: (login: string, password: string) => Promise<void>;
  loginStaff: (email: string, password: string) => Promise<void>;
  logout: () => Promise<void>;
  setMember: (member: Member) => void;
};

const AuthContext = createContext<Auth | null>(null);
const KEY = 'session';

export function AuthProvider({ children }: { children: ReactNode }) {
  const [session, setSession] = useState<Session | null>(null);
  const [loading, setLoading] = useState(true);

  const persist = useCallback(async (next: Session | null) => {
    setSession(next);
    apiConfig.setToken(next?.token ?? null);
    if (next) await storage.set(KEY, JSON.stringify(next));
    else await storage.remove(KEY);
  }, []);

  useEffect(() => {
    (async () => {
      await apiConfig.load();
      const raw = await storage.get(KEY);
      if (raw) {
        try {
          const saved = JSON.parse(raw) as Session;
          apiConfig.setToken(saved.token);
          setSession(saved);
        } catch {
          await storage.remove(KEY);
        }
      }
      setLoading(false);
    })();
    apiConfig.onUnauthorized(() => persist(null));
  }, [persist]);

  const value = useMemo<Auth>(
    () => ({
      session,
      loading,
      async loginMember(login, password) {
        const res = await api<{ token: string; member: Member }>('/auth/member/login', {
          method: 'POST',
          body: { login, password, device_name: 'mobile-app' },
        });
        await persist({ type: 'member', token: res.token, member: res.member });
      },
      async loginStaff(email, password) {
        const res = await api<{ token: string; user: StaffUser }>('/auth/staff/login', {
          method: 'POST',
          body: { email, password, device_name: 'mobile-app' },
        });
        await persist({ type: 'staff', token: res.token, user: res.user });
      },
      async logout() {
        try {
          await api('/auth/logout', { method: 'POST' });
        } catch {
          // token may already be invalid; sign out locally anyway
        }
        await persist(null);
      },
      setMember(member) {
        if (session?.type === 'member') persist({ ...session, member });
      },
    }),
    [session, loading, persist],
  );

  return <AuthContext.Provider value={value}>{children}</AuthContext.Provider>;
}

export function useAuth(): Auth {
  const ctx = useContext(AuthContext);
  if (!ctx) throw new Error('useAuth must be used inside AuthProvider');
  return ctx;
}
