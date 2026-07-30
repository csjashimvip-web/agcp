export type Role = { id: string; name: string; slug: string; scope: "platform" | "tenant" };
export type ApiUser = {
  id: string;
  name: string;
  email: string;
  status: string;
  locale: string;
  timezone: string;
  email_verified: boolean;
  two_factor_enabled: boolean;
  passkeys_enabled: boolean;
  roles: Role[];
  permissions: string[];
  last_login_at: string | null;
  created_at: string | null;
};

export class ApiError extends Error {
  status: number;
  code?: string;
  errors?: Record<string, string[]>;

  constructor(message: string, status: number, payload?: { code?: string; errors?: Record<string, string[]> }) {
    super(message);
    this.name = "ApiError";
    this.status = status;
    this.code = payload?.code;
    this.errors = payload?.errors;
  }
}

function getCookie(name: string): string | null {
  if (typeof document === "undefined") return null;
  const item = document.cookie.split("; ").find((row) => row.startsWith(`${name}=`));
  return item ? decodeURIComponent(item.split("=").slice(1).join("=")) : null;
}

function getDeviceId(): string {
  if (typeof window === "undefined") return "server";
  const key = "agcp_device_id";
  let value = window.localStorage.getItem(key);
  if (!value) {
    value = window.crypto.randomUUID();
    window.localStorage.setItem(key, value);
  }
  return value;
}

export async function csrf(): Promise<void> {
  const response = await fetch("/sanctum/csrf-cookie", {
    credentials: "include",
    headers: { Accept: "application/json", "X-Device-ID": getDeviceId() },
  });
  if (!response.ok) throw new ApiError("Unable to initialize secure session.", response.status);
}

export async function apiFetch<T = unknown>(path: string, options: RequestInit = {}): Promise<T> {
  const method = (options.method ?? "GET").toUpperCase();
  if (!["GET", "HEAD", "OPTIONS"].includes(method)) await csrf();

  const headers = new Headers(options.headers);
  headers.set("Accept", "application/json");
  headers.set("X-Device-ID", getDeviceId());
  const xsrf = getCookie("XSRF-TOKEN");
  if (xsrf) headers.set("X-XSRF-TOKEN", xsrf);
  if (options.body && !(options.body instanceof FormData) && !headers.has("Content-Type")) {
    headers.set("Content-Type", "application/json");
  }

  const response = await fetch(path, { ...options, headers, credentials: "include" });
  if (response.status === 204) return undefined as T;

  const payload = await response.json().catch(() => ({}));
  if (!response.ok) {
    throw new ApiError(payload.message ?? "The request could not be completed.", response.status, payload);
  }
  return payload as T;
}

export const authApi = {
  me: () => apiFetch<{ data: ApiUser }>("/api/v1/auth/me"),
  login: (email: string, password: string, remember = false) =>
    apiFetch<{ two_factor?: boolean } | void>("/api/v1/auth/login", {
      method: "POST",
      body: JSON.stringify({ email, password, remember }),
    }),
  twoFactorChallenge: (code: string, recoveryCode?: string) =>
    apiFetch<void>("/api/v1/auth/two-factor-challenge", {
      method: "POST",
      body: JSON.stringify(recoveryCode ? { recovery_code: recoveryCode } : { code }),
    }),
  register: (input: { name: string; email: string; password: string; password_confirmation: string; terms: boolean }) =>
    apiFetch<void>("/api/v1/auth/register", { method: "POST", body: JSON.stringify(input) }),
  logout: () => apiFetch<void>("/api/v1/auth/logout", { method: "POST" }),
};

export function errorMessage(error: unknown): string {
  if (error instanceof ApiError) {
    const first = error.errors ? Object.values(error.errors).flat()[0] : undefined;
    return first ?? error.message;
  }
  return error instanceof Error ? error.message : "Something went wrong.";
}
