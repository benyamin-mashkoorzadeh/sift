import { sanctumFetch } from "@/lib/sanctum";
import type {
  ReviewErrorResponse,
  ReviewItem,
  ReviewItemResponse,
} from "@/types/review-item";

export class ReviewApiError extends Error {
  constructor(
    message: string,
    public readonly status: number,
    public readonly resolutionError: string | null = null,
  ) {
    super(message);
    this.name = "ReviewApiError";
  }
}

export async function resolveReviewItem(
  workspaceId: number,
  reviewItemId: number,
  resolution: string,
): Promise<ReviewItem> {
  const response = await sanctumFetch(
    `/workspaces/${workspaceId}/review-items/${reviewItemId}/resolve`,
    {
      method: "PATCH",
      headers: {
        Accept: "application/json",
        "Content-Type": "application/json",
      },
      body: JSON.stringify({ resolution }),
    },
  );
  const payload = await parseJson(response);

  if (!response.ok) {
    const error = payload as ReviewErrorResponse | null;
    const resolutionError = error?.errors?.resolution?.[0] ?? null;
    const message = response.status === 409
      ? error?.message ?? "This review item has already been resolved."
      : response.status === 422
        ? resolutionError ?? "Check the resolution and try again."
        : "The review item could not be resolved. Please try again.";

    throw new ReviewApiError(message, response.status, resolutionError);
  }

  if (!isReviewItemResponse(payload)) {
    throw new ReviewApiError("Sift returned an unexpected response. Please try again.", response.status);
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

function isReviewItemResponse(value: unknown): value is ReviewItemResponse {
  if (!isRecord(value) || !isRecord(value.data)) return false;

  const { data } = value;

  return typeof data.id === "number"
    && (data.status === "pending" || data.status === "resolved")
    && typeof data.question === "string"
    && (data.resolution === null || typeof data.resolution === "string")
    && typeof data.created_at === "string"
    && typeof data.last_asked_at === "string"
    && (data.resolved_at === null || typeof data.resolved_at === "string");
}

function isRecord(value: unknown): value is Record<string, unknown> {
  return typeof value === "object" && value !== null;
}
