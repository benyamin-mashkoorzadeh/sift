import "server-only";

import { ApiError } from "@/lib/api";
import { serverApiFetch } from "@/lib/server-api";
import type { WorkspaceOverview, WorkspaceOverviewResponse } from "@/types/overview";

export async function getWorkspaceOverview(workspaceId: number): Promise<WorkspaceOverview> {
  const response = await serverApiFetch(`/workspaces/${workspaceId}/overview`);

  if (!response.ok) {
    throw new ApiError("The workspace overview could not be loaded.", response.status);
  }

  const payload = await response.json() as WorkspaceOverviewResponse;

  return payload.data;
}
