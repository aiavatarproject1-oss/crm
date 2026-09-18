import { getToken } from "./auth";

const API_BASE = process.env.NEXT_PUBLIC_API_URL || "https://resale-displace-banker.ngrok-free.dev";

function apiHeaders(init?: HeadersInit, json?: unknown): Headers {
  const headers = new Headers(init);
  headers.set("Accept", "application/json");
  headers.set("ngrok-skip-browser-warning", "true");
  const token = getToken();
  if (token) headers.set("Authorization", `Bearer ${token}`);
  if (json !== undefined) headers.set("Content-Type", "application/json");
  return headers;
}

export class ApiError extends Error {
  constructor(
    public status: number,
    message: string,
    public code?: string,
  ) {
    super(message);
  }
}

export async function api<T = unknown>(
  path: string,
  init: RequestInit & { json?: unknown } = {},
): Promise<T> {
  const headers = apiHeaders(init.headers, init.json);

  const res = await fetch(`${API_BASE}${path}`, {
    ...init,
    headers,
    body: init.json !== undefined ? JSON.stringify(init.json) : init.body,
  });

  const text = await res.text();
  let body: { success?: boolean; data?: T; message?: string; code?: string; meta?: unknown } = {};
  if (text) {
    try {
      body = JSON.parse(text);
    } catch {
      body = { message: text.slice(0, 200) };
    }
  }

  if (!res.ok) {
    throw new ApiError(res.status, body.message || `HTTP ${res.status}`, body.code);
  }

  return (body.data !== undefined ? body.data : (body as T)) as T;
}

export async function apiWithMeta<T>(
  path: string,
  init?: RequestInit & { json?: unknown },
): Promise<{ data: T; meta?: { page: number; per_page: number; total: number } }> {
  const headers = apiHeaders(init?.headers, init?.json);

  const res = await fetch(`${API_BASE}${path}`, {
    ...init,
    headers,
    body: init?.json !== undefined ? JSON.stringify(init.json) : init?.body,
  });
  const body = await res.json();
  if (!res.ok) {
    throw new ApiError(res.status, body.message || `HTTP ${res.status}`, body.code);
  }
  return { data: body.data, meta: body.meta };
}
