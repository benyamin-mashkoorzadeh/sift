import "server-only";

import { ApiError } from "@/lib/api";
import { serverApiFetch } from "@/lib/server-api";
import type { PaginatedResponse } from "@/types/api";
import type {
  AssistantInteraction,
  AssistantInteractionFilter,
} from "@/types/assistant-interaction";

export async function getAssistantInteractions(
  workspaceId: number,
  status: AssistantInteractionFilter,
  page: number,
): Promise<PaginatedResponse<AssistantInteraction>> {
  const parameters = new URLSearchParams({ page: String(page) });

  if (status !== "all") {
    parameters.set("status", status);
  }

  const response = await serverApiFetch(
    `/workspaces/${workspaceId}/assistant-interactions?${parameters}`,
  );

  if (!response.ok) {
    throw new ApiError("Assistant history could not be loaded.", response.status);
  }

  return response.json() as Promise<PaginatedResponse<AssistantInteraction>>;
}
