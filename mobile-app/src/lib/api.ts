import Constants from 'expo-constants';
import { storage } from './storage';

/** Thrown for non-2xx responses; `errors` holds Laravel validation messages when present. */
export class ApiError extends Error {
  constructor(
    message: string,
    public status: number,
    public errors: Record<string, string[]> = {},
    public body: unknown = null,
  ) {
    super(message);
  }
}

const DEFAULT_URL =
  process.env.EXPO_PUBLIC_API_URL ?? (Constants.expoConfig?.extra?.apiUrl as string | undefined) ?? 'http://localhost:8000';

let baseUrl = DEFAULT_URL;
let token: string | null = null;
let lang = 'ar';
let gym = '';
let onUnauthorized: (() => void) | null = null;

export const apiConfig = {
  defaultUrl: DEFAULT_URL,
  async load() {
    baseUrl = (await storage.get('server_url')) || DEFAULT_URL;
    gym = (await storage.get('gym_code')) || '';
    return baseUrl;
  },
  getGym: () => gym,
  /** The gym (tenant) code sent as X-Gym; one app serves every gym on the platform. */
  async setGym(code: string) {
    gym = code.trim().toLowerCase();
    await storage.set('gym_code', gym);
  },
  getBaseUrl: () => baseUrl,
  async setBaseUrl(url: string) {
    baseUrl = url.trim().replace(/\/+$/, '');
    await storage.set('server_url', baseUrl);
  },
  setToken(value: string | null) {
    token = value;
  },
  setLang(value: string) {
    lang = value;
  },
  onUnauthorized(cb: () => void) {
    onUnauthorized = cb;
  },
};

export async function api<T>(path: string, options: { method?: string; body?: unknown } = {}): Promise<T> {
  let res: Response;
  try {
    res = await fetch(`${baseUrl}/api/v1${path}`, {
      method: options.method ?? 'GET',
      headers: {
        Accept: 'application/json',
        'Accept-Language': lang,
        ...(gym ? { 'X-Gym': gym } : {}),
        ...(options.body !== undefined ? { 'Content-Type': 'application/json' } : {}),
        ...(token ? { Authorization: `Bearer ${token}` } : {}),
      },
      body: options.body !== undefined ? JSON.stringify(options.body) : undefined,
    });
  } catch {
    throw new ApiError('network', 0);
  }

  const data = await res.json().catch(() => null);

  if (res.status === 401 && token) {
    onUnauthorized?.();
  }
  if (!res.ok) {
    const errors = (data?.errors ?? {}) as Record<string, string[]>;
    const first = Object.values(errors)[0]?.[0];
    throw new ApiError(first ?? data?.message ?? `HTTP ${res.status}`, res.status, errors, data);
  }
  return data as T;
}
