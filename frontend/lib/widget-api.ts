import { apiUrl } from "@/lib/api";
import type {
  WidgetAnswer,
  WidgetBootstrap,
  WidgetConversationSession,
} from "@/types/widget";

interface ApiEnvelope<T> {
  data: T;
}

interface ErrorEnvelope {
  message?: unknown;
  code?: unknown;
  errors?: {
    question?: unknown;
  };
}

export class WidgetApiError extends Error {
  constructor(
    message: string,
    public readonly status: number,
    public readonly code: string | null = null,
    public readonly questionError: string | null = null,
  ) {
    super(message);
    this.name = "WidgetApiError";
  }
}

export async function getWidgetBootstrap(widgetKey: string): Promise<WidgetBootstrap> {
  const response = await widgetFetch(widgetPath(widgetKey));
  const payload = await parseJson(response);

  if (!response.ok) throw toWidgetApiError(response, payload);
  if (!isEnvelope(payload) || !isWidgetBootstrap(payload.data)) {
    throw new WidgetApiError("Widget unavailable.", response.status);
  }

  return payload.data;
}

export async function startWidgetConversation(
  widgetKey: string,
  signal?: AbortSignal,
): Promise<WidgetConversationSession> {
  const response = await widgetFetch(`${widgetPath(widgetKey)}/conversations`, {
    method: "POST",
    headers: { "Content-Type": "application/json" },
    body: "{}",
    signal,
  });
  const payload = await parseJson(response);

  if (!response.ok) throw toWidgetApiError(response, payload);
  if (!isEnvelope(payload) || !isConversationResponse(payload.data)) {
    throw new WidgetApiError("The support session could not be started.", response.status);
  }

  return {
    conversationToken: payload.data.conversation_token,
    expiresAt: payload.data.expires_at,
  };
}

export async function sendWidgetQuestion(
  widgetKey: string,
  conversationToken: string,
  question: string,
  signal?: AbortSignal,
): Promise<WidgetAnswer> {
  const response = await widgetFetch(`${widgetPath(widgetKey)}/messages`, {
    method: "POST",
    headers: {
      "Content-Type": "application/json",
      "X-Sift-Conversation-Token": conversationToken,
    },
    body: JSON.stringify({ question }),
    signal,
  });
  const payload = await parseJson(response);

  if (!response.ok) throw toWidgetApiError(response, payload);
  if (!isEnvelope(payload) || !isWidgetAnswer(payload.data)) {
    throw new WidgetApiError("Sift returned an unexpected response.", response.status);
  }

  return payload.data;
}

async function widgetFetch(path: string, init: RequestInit = {}): Promise<Response> {
  const headers = new Headers(init.headers);
  headers.set("Accept", "application/json");

  return fetch(apiUrl(path), {
    ...init,
    cache: "no-store",
    credentials: "omit",
    headers,
  });
}

function widgetPath(widgetKey: string): string {
  return `/widget/v1/${encodeURIComponent(widgetKey)}`;
}

async function parseJson(response: Response): Promise<unknown> {
  try {
    return await response.json();
  } catch {
    return null;
  }
}

function toWidgetApiError(response: Response, payload: unknown): WidgetApiError {
  const error = isRecord(payload) ? payload as ErrorEnvelope : null;
  const code = typeof error?.code === "string" ? error.code : null;
  const questionErrors = error?.errors?.question;
  const questionError = Array.isArray(questionErrors) && typeof questionErrors[0] === "string"
    ? questionErrors[0]
    : null;

  return new WidgetApiError(
    publicErrorMessage(response.status, code, questionError),
    response.status,
    code,
    questionError,
  );
}

function publicErrorMessage(status: number, code: string | null, questionError: string | null): string {
  if (status === 422) return questionError ?? "Check your question and try again.";
  if (status === 429) return "Please wait a moment and try again.";
  if (status === 503 || code === "widget_answer_unavailable") {
    return "An answer is temporarily unavailable. Please try again.";
  }
  if (code === "conversation_unavailable") return "The support session has expired.";
  if (code === "widget_unavailable" || status === 404) return "Widget unavailable.";

  return "Something went wrong. Please try again.";
}

function isEnvelope(value: unknown): value is ApiEnvelope<unknown> {
  return isRecord(value) && "data" in value;
}

function isWidgetBootstrap(value: unknown): value is WidgetBootstrap {
  return isRecord(value)
    && typeof value.workspace_name === "string"
    && value.workspace_name.trim() !== "";
}

function isConversationResponse(value: unknown): value is {
  conversation_token: string;
  expires_at: string;
} {
  return isRecord(value)
    && typeof value.conversation_token === "string"
    && value.conversation_token !== ""
    && typeof value.expires_at === "string"
    && !Number.isNaN(Date.parse(value.expires_at));
}

function isWidgetAnswer(value: unknown): value is WidgetAnswer {
  return isRecord(value)
    && (value.status === "answered" || value.status === "needs_review")
    && typeof value.answer === "string"
    && value.answer.trim() !== "";
}

function isRecord(value: unknown): value is Record<string, unknown> {
  return typeof value === "object" && value !== null;
}
