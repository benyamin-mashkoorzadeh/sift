import { sanctumFetch } from "@/lib/sanctum";
import type {
  AuthenticatedContext,
  AuthenticatedContextResponse,
  AuthErrorResponse,
} from "@/types/auth";

export class AuthApiError extends Error {
  constructor(
    message: string,
    public readonly status: number,
    public readonly errors: Record<string, string[]> = {},
  ) {
    super(message);
    this.name = "AuthApiError";
  }
}

export async function login(email: string, password: string): Promise<void> {
  await submitAuth("/auth/login", { email, password });
}

export async function register(data: {
  name: string;
  company_name: string;
  email: string;
  password: string;
  password_confirmation: string;
}): Promise<void> {
  await submitAuth("/auth/register", data);
}

export async function enterDemo(): Promise<AuthenticatedContext> {
  const response = await sanctumFetch("/auth/demo", { method: "POST" });
  const payload = await parseJson(response) as AuthenticatedContextResponse | AuthErrorResponse | null;

  if (!response.ok) {
    const error = payload as AuthErrorResponse | null;

    throw new AuthApiError(demoErrorMessage(response.status, error), response.status);
  }

  if (!isAuthenticatedContextResponse(payload)) {
    throw new AuthApiError("Sift returned an unexpected response. Please try again.", response.status);
  }

  return payload.data;
}

export async function logout(): Promise<void> {
  const response = await sanctumFetch("/auth/logout", { method: "POST" });

  if (!response.ok) {
    throw new AuthApiError("Sift could not log you out. Please try again.", response.status);
  }
}

async function submitAuth(path: string, data: Record<string, string>): Promise<void> {
  const response = await sanctumFetch(path, {
    method: "POST",
    headers: { "Content-Type": "application/json" },
    body: JSON.stringify(data),
  });
  const payload = await parseJson(response) as AuthErrorResponse | null;

  if (!response.ok) {
    throw new AuthApiError(
      response.status === 422
        ? payload?.message ?? "Check the highlighted fields and try again."
        : "Sift could not complete the request. Please try again.",
      response.status,
      payload?.errors ?? {},
    );
  }
}

async function parseJson(response: Response): Promise<unknown> {
  try {
    return await response.json();
  } catch {
    return null;
  }
}

function demoErrorMessage(status: number, error: AuthErrorResponse | null): string {
  if (status === 429 || error?.code === "demo_rate_limited") {
    return "Guest Demo is busy right now. Please wait a moment and try again.";
  }

  if (status === 409 || error?.code === "demo_session_conflict") {
    return "You are already signed in. Log out before entering Guest Demo.";
  }

  if (status === 503 || error?.code === "demo_unavailable") {
    return "Guest Demo is unavailable right now. Please try again later.";
  }

  return "Sift could not enter Guest Demo. Please try again.";
}

function isAuthenticatedContextResponse(value: unknown): value is AuthenticatedContextResponse {
  if (!isRecord(value) || !isRecord(value.data)) return false;

  const { data } = value;

  return isRecord(data.user)
    && typeof data.user.id === "number"
    && typeof data.user.name === "string"
    && typeof data.user.email === "string"
    && isRecord(data.workspace)
    && typeof data.workspace.id === "number"
    && typeof data.workspace.name === "string"
    && (data.workspace.role === "owner" || data.workspace.role === "admin" || data.workspace.role === "member")
    && (data.access_mode === "normal" || data.access_mode === "demo")
    && Array.isArray(data.permissions)
    && data.permissions.every((permission) => typeof permission === "string");
}

function isRecord(value: unknown): value is Record<string, unknown> {
  return typeof value === "object" && value !== null;
}
