export type ApiEnvelope<T> = {
  data: T;
};

export type TenantOption = {
  id: number;
  name: string;
  slug: string;
  default_currency: string;
};

function readCookie(name: string): string | null {
  if (typeof document === "undefined") return null;

  const prefix = `${name}=`;

  for (const part of document.cookie.split(";")) {
    const value = part.trim();

    if (value.startsWith(prefix)) {
      return decodeURIComponent(value.slice(prefix.length));
    }
  }

  return null;
}

export async function ensureCsrfCookie(): Promise<void> {
  const response = await fetch("/sanctum/csrf-cookie", {
    credentials: "include",
    cache: "no-store",
  });

  if (!response.ok && response.status !== 204) {
    throw new Error("Unable to initialize secure session.");
  }
}

export async function apiFetch<T>(
  path: string,
  options: RequestInit = {},
  tenant?: number | string | null,
): Promise<T> {
  const headers = new Headers(options.headers);

  headers.set("Accept", "application/json");

  if (options.body && !headers.has("Content-Type")) {
    headers.set("Content-Type", "application/json");
  }

  if (tenant !== undefined && tenant !== null && String(tenant) !== "") {
    headers.set("X-AGCP-Tenant", String(tenant));
  }

  const method = (options.method || "GET").toUpperCase();

  if (!["GET", "HEAD", "OPTIONS"].includes(method)) {
    const token = readCookie("XSRF-TOKEN");
    if (token) headers.set("X-XSRF-TOKEN", token);
  }

  const response = await fetch(path, {
    ...options,
    headers,
    credentials: "include",
    cache: "no-store",
  });

  if (response.status === 401) {
    throw new Error("UNAUTHENTICATED");
  }

  const payload = await response.json().catch(() => ({}));

  if (!response.ok) {
    const message =
      payload?.message ||
      payload?.errors?.email?.[0] ||
      payload?.errors?.wallet?.[0] ||
      `Request failed with status ${response.status}.`;

    throw new Error(message);
  }

  return payload as T;
}

export async function login(email: string, password: string): Promise<void> {
  await ensureCsrfCookie();

  await apiFetch("/api/v1/auth/login", {
    method: "POST",
    body: JSON.stringify({ email, password }),
  });
}

export async function logout(): Promise<void> {
  await apiFetch("/api/v1/auth/logout", {
    method: "POST",
  });
}