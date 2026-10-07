import type { WidgetConversationSession } from "@/types/widget";

const STORAGE_PREFIX = "sift:widget:session:";
const EXPIRATION_BUFFER_MS = 5_000;

export function readWidgetSession(widgetKey: string): WidgetConversationSession | null {
  try {
    const stored = window.sessionStorage.getItem(storageKey(widgetKey));
    if (!stored) return null;

    const value: unknown = JSON.parse(stored);
    if (!isSession(value) || Date.parse(value.expiresAt) <= Date.now() + EXPIRATION_BUFFER_MS) {
      clearWidgetSession(widgetKey);
      return null;
    }

    return value;
  } catch {
    clearWidgetSession(widgetKey);
    return null;
  }
}

export function saveWidgetSession(widgetKey: string, session: WidgetConversationSession): void {
  try {
    window.sessionStorage.setItem(storageKey(widgetKey), JSON.stringify(session));
  } catch {
    // Storage can be unavailable in privacy-restricted iframe contexts.
  }
}

export function clearWidgetSession(widgetKey: string): void {
  try {
    window.sessionStorage.removeItem(storageKey(widgetKey));
  } catch {
    // An unavailable storage backend should not prevent a fresh in-memory session.
  }
}

function storageKey(widgetKey: string): string {
  return `${STORAGE_PREFIX}${widgetKey}`;
}

function isSession(value: unknown): value is WidgetConversationSession {
  if (typeof value !== "object" || value === null) return false;

  const session = value as Record<string, unknown>;

  return typeof session.conversationToken === "string"
    && session.conversationToken !== ""
    && typeof session.expiresAt === "string"
    && !Number.isNaN(Date.parse(session.expiresAt));
}
