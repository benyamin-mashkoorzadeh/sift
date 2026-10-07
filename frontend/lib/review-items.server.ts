import "server-only";

import { ApiError } from "@/lib/api";
import { serverApiFetch } from "@/lib/server-api";
import type { PaginatedResponse } from "@/types/api";
import type { ReviewItem, ReviewItemStatus } from "@/types/review-item";

export async function getReviewItems(
  workspaceId: number,
  status: ReviewItemStatus,
  page: number,
): Promise<PaginatedResponse<ReviewItem>> {
  const parameters = new URLSearchParams({ status, page: String(page) });
  const response = await serverApiFetch(
    `/workspaces/${workspaceId}/review-items?${parameters}`,
  );

  if (!response.ok) {
    throw new ApiError("The review queue could not be loaded.", response.status);
  }

  return response.json() as Promise<PaginatedResponse<ReviewItem>>;
}
