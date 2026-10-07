import { sanctumFetch } from "@/lib/sanctum";
import type {
  AssistantAnswer,
  AssistantAnswerResponse,
  AssistantErrorResponse,
} from "@/types/assistant";

export class AssistantApiError extends Error {
  constructor(
    message: string,
    public readonly status: number,
    public readonly questionError: string | null = null,
  ) {
    super(message);
    this.name = "AssistantApiError";
  }
}

export async function askWorkspaceQuestion(
  workspaceId: number,
  question: string,
  signal?: AbortSignal,
): Promise<AssistantAnswer> {
  const response = await sanctumFetch(`/workspaces/${workspaceId}/answers`, {
    method: "POST",
    headers: {
      Accept: "application/json",
      "Content-Type": "application/json",
    },
    body: JSON.stringify({ question }),
    signal,
  });

  const payload = await parseJson(response);

  if (!response.ok) {
    const error = payload as AssistantErrorResponse | null;
    const questionError = error?.errors?.question?.[0] ?? null;
    const message = error?.code === "demo_rate_limited"
      ? "Guest Assistant is busy right now. Please wait a moment and try again."
      : error?.code === "demo_assistant_unavailable"
        ? "Guest Assistant is unavailable right now. Please try again later."
        : response.status === 503 && error?.code === "answer_unavailable"
          ? error.message ?? "Sift could not generate an answer right now."
          : response.status === 422
            ? questionError ?? "Check the question and try again."
            : "Sift could not complete the request. Please try again.";

    throw new AssistantApiError(message, response.status, questionError);
  }

  if (!isAssistantAnswerResponse(payload)) {
    throw new AssistantApiError("Sift returned an unexpected response. Please try again.", response.status);
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

function isAssistantAnswerResponse(value: unknown): value is AssistantAnswerResponse {
  if (!isRecord(value) || !isRecord(value.data)) return false;

  const { data } = value;

  return (data.status === "answered" || data.status === "needs_review")
    && typeof data.answer === "string"
    && Array.isArray(data.citations)
    && data.citations.every((citation) => isRecord(citation)
      && typeof citation.document_id === "number"
      && typeof citation.original_filename === "string"
      && typeof citation.page_number === "number"
      && typeof citation.chunk_id === "number"
      && typeof citation.chunk_index === "number");
}

function isRecord(value: unknown): value is Record<string, unknown> {
  return typeof value === "object" && value !== null;
}
