import { createContext, useCallback, useContext, useEffect, useMemo, useState, type ReactNode } from 'react';
import { router } from 'expo-router';
import { api, ApiError, setApiReady, setApiToken, setOnUnauthorized } from './api';
import { getItem, removeItem, setItem } from './storage';
import { unregisterPush } from './push';
import type { AppConfig, Me } from './types';

const TOKEN_KEY = 'enro_token';

const DEFAULT_CONFIG: AppConfig = {
  app_name: 'Enro', coins_per_naira: 10, airtime_amounts: [100, 200, 500, 1000], min_withdraw: 1000, withdraw_fee: 50,
  daily_task_limit: 10, ref_bonus_referrer: 200, ref_bonus_new: 100, ref_unlock_tasks: 5, streak_bonus: 100, support_email: '',
  push: { server: false, evening_hour: 19, evening_enabled: true },
  ads: { interstitial: { enabled: false, every: 3 }, smartlink: '' },
};

type AuthCtx = {
  ready: boolean;
  token: string | null;
  me: Me | null;
  config: AppConfig;
  refresh: () => Promise<void>;
  signIn: (token: string) => Promise<void>;
  signOut: (message?: string) => Promise<void>;
  setBalances: (b: { coins?: number; cash?: number }) => void;
};

const Ctx = createContext<AuthCtx | null>(null);

export function AuthProvider({ children }: { children: ReactNode }) {
  const [ready, setReady] = useState(false);
  const [token, setToken] = useState<string | null>(null);
  const [me, setMe] = useState<Me | null>(null);
  const [config, setConfig] = useState<AppConfig>(DEFAULT_CONFIG);

  const refresh = useCallback(async () => {
    const data = await api<Me>('/me');
    setMe(data);
  }, []);

  const signOut = useCallback(async (message?: string) => {
    if (!message) await unregisterPush(); // voluntary logout: stop pushes for this account on this phone
    await removeItem(TOKEN_KEY);
    setApiToken(null);
    setToken(null);
    setMe(null);
    router.replace(message ? { pathname: '/login', params: { notice: message } } : '/login');
  }, []);

  const signIn = useCallback(async (t: string) => {
    await setItem(TOKEN_KEY, t);
    setApiToken(t);
    setToken(t);
    const data = await api<Me>('/me');
    setMe(data);
  }, []);

  const setBalances = useCallback((b: { coins?: number; cash?: number }) => {
    setMe((m) => (m ? { ...m, user: { ...m.user, ...b } } : m));
  }, []);

  useEffect(() => {
    (async () => {
      api<AppConfig>('/config', { boot: true }).then(setConfig).catch(() => {});
      const saved = await getItem(TOKEN_KEY);
      if (saved) {
        setApiToken(saved);
        try {
          setMe(await api<Me>('/me', { boot: true }));
          setToken(saved);
        } catch (e) {
          if (e instanceof ApiError && (e.status === 401 || e.status === 403)) {
            await removeItem(TOKEN_KEY); // expired or banned: start at login
            setApiToken(null);
          } else {
            setToken(saved); // offline: stay logged in, screens offer retry
          }
        }
      }
      // after startup, any 401/ban sends the user back to login
      setOnUnauthorized((msg) => void signOut(msg));
      setApiReady();
      setReady(true);
    })();
  }, [signOut]);

  const value = useMemo(() => ({ ready, token, me, config, refresh, signIn, signOut, setBalances }),
    [ready, token, me, config, refresh, signIn, signOut, setBalances]);
  return <Ctx.Provider value={value}>{children}</Ctx.Provider>;
}

export function useAuth() {
  const c = useContext(Ctx);
  if (!c) throw new Error('useAuth outside AuthProvider');
  return c;
}
