export class ApiError extends Error {
  constructor(message: string, public readonly status: number) {
    super(message);
    this.name = "ApiError";
  }
}

export function apiUrl(path: string): string {
  const baseUrl = process.env.NEXT_PUBLIC_API_URL?.replace(/\/$/, "");

  if (!baseUrl) {
    throw new Error("NEXT_PUBLIC_API_URL is not configured.");
  }

  return `${baseUrl}/${path.replace(/^\//, "")}`;
}

export function sanctumCsrfUrl(): string {
  return new URL("/sanctum/csrf-cookie", apiUrl("/")).toString();
}
