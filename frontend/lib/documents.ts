import "server-only";

import { ApiError } from "@/lib/api";
import { serverApiFetch } from "@/lib/server-api";
import type { Document, PaginatedResponse } from "@/types/document";

export async function getDocuments(
  workspaceId: number,
  page: number,
): Promise<PaginatedResponse<Document>> {
  const response = await serverApiFetch(`/workspaces/${workspaceId}/documents?page=${page}`);

  if (!response.ok) {
    throw new ApiError("The document list could not be loaded.", response.status);
  }

  return response.json() as Promise<PaginatedResponse<Document>>;
}
