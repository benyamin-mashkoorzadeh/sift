import { apiUrl, sanctumCsrfUrl } from "@/lib/api";

let csrfRequest: Promise<void> | null = null;

export async function sanctumFetch(
  path: string,
  init: RequestInit = {},
): Promise<Response> {
  await ensureCsrfCookie();

  const token = getXsrfToken();
  const headers = new Headers(init.headers);
  headers.set("Accept", "application/json");

  if (token) {
    headers.set("X-XSRF-TOKEN", token);
  }

  return fetch(apiUrl(path), {
    ...init,
    credentials: "include",
    headers,
  });
}

export async function ensureCsrfCookie(): Promise<void> {
  csrfRequest ??= fetch(sanctumCsrfUrl(), {
    credentials: "include",
    headers: { Accept: "application/json" },
  }).then((response) => {
    if (!response.ok) {
      throw new Error("Sift could not initialize a secure session.");
    }
  }).finally(() => {
    csrfRequest = null;
  });

  return csrfRequest;
}

export function getXsrfToken(): string | null {
  if (typeof document === "undefined") return null;

  const cookie = document.cookie
    .split("; ")
    .find((entry) => entry.startsWith("XSRF-TOKEN="));

  if (!cookie) return null;

  return decodeURIComponent(cookie.slice("XSRF-TOKEN=".length));
}
