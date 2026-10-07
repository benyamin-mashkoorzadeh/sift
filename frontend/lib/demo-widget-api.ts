import { apiUrl } from "@/lib/api";
import type { WidgetAnswer } from "@/types/widget";

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

export class DemoWidgetApiError extends Error {
  constructor(
    message: string,
    public readonly status: number,
    public readonly code: string | null = null,
  ) {
    super(message);
    this.name = "DemoWidgetApiError";
  }
}

export async function sendDemoWidgetQuestion(
  question: string,
  signal?: AbortSignal,
): Promise<WidgetAnswer> {
  const response = await fetch(apiUrl("/demo/widget/messages"), {
    method: "POST",
    cache: "no-store",
    credentials: "omit",
    headers: {
      Accept: "application/json",
      "Content-Type": "application/json",
    },
    body: JSON.stringify({ question }),
    signal,
  });
  const payload = await parseJson(response);

  if (!response.ok) throw toDemoWidgetApiError(response, payload);
  if (!isEnvelope(payload) || !isWidgetAnswer(payload.data)) {
    throw new DemoWidgetApiError("Sift returned an unexpected response.", response.status);
  }

  return payload.data;
}

async function parseJson(response: Response): Promise<unknown> {
  try {
    return await response.json();
  } catch {
    return null;
  }
}

function toDemoWidgetApiError(response: Response, payload: unknown): DemoWidgetApiError {
  const error = isRecord(payload) ? payload as ErrorEnvelope : null;
  const code = typeof error?.code === "string" ? error.code : null;
  const questionErrors = error?.errors?.question;
  const questionError = Array.isArray(questionErrors) && typeof questionErrors[0] === "string"
    ? questionErrors[0]
    : null;

  return new DemoWidgetApiError(publicErrorMessage(response.status, code, questionError), response.status, code);
}

function publicErrorMessage(status: number, code: string | null, questionError: string | null): string {
  if (status === 422) return questionError ?? "Check your question and try again.";
  if (status === 429 || code === "demo_widget_rate_limited") {
    return "The Demo is busy right now. Please wait a moment and try again.";
  }
  if (status === 503 || code === "demo_widget_unavailable") {
    return "The Demo assistant is temporarily unavailable. Please try again later.";
  }

  return "We couldn't get an answer right now. Please try again.";
}

function isEnvelope(value: unknown): value is ApiEnvelope<unknown> {
  return isRecord(value) && "data" in value;
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
