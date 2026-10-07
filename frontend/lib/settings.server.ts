import "server-only";

import { ApiError } from "@/lib/api";
import { serverApiFetch } from "@/lib/server-api";
import type { WorkspaceSettings, WorkspaceSettingsResponse } from "@/types/settings";

export async function getWorkspaceSettings(workspaceId: number): Promise<WorkspaceSettings> {
  const response = await serverApiFetch(`/workspaces/${workspaceId}/settings`);

  if (!response.ok) {
    throw new ApiError("The workspace settings could not be loaded.", response.status);
  }

  const payload = await response.json() as WorkspaceSettingsResponse;

  return payload.data;
}
