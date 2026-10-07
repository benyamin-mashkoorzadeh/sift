import "server-only";

import { cookies } from "next/headers";
import { apiUrl } from "@/lib/api";

export async function serverApiFetch(
  path: string,
  init: RequestInit = {},
): Promise<Response> {
  const cookieStore = await cookies();
  const headers = new Headers(init.headers);
  const cookieHeader = cookieStore.toString();

  headers.set("Accept", "application/json");
  headers.set("Origin", frontendOrigin());

  if (cookieHeader) {
    headers.set("Cookie", cookieHeader);
  }

  return fetch(apiUrl(path), {
    ...init,
    cache: "no-store",
    headers,
  });
}

function frontendOrigin(): string {
  const configuredUrl = process.env.SIFT_FRONTEND_URL;

  if (!configuredUrl) {
    throw new Error("SIFT_FRONTEND_URL is not configured.");
  }

  const url = new URL(configuredUrl);

  if (url.protocol !== "http:" && url.protocol !== "https:") {
    throw new Error("SIFT_FRONTEND_URL must use HTTP or HTTPS.");
  }

  return url.origin;
}
