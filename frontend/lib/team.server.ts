import "server-only";

import { ApiError } from "@/lib/api";
import { serverApiFetch } from "@/lib/server-api";
import type {
  TeamCollectionResponse,
  WorkspaceInvitation,
  WorkspaceMember,
} from "@/types/team";

export async function getWorkspaceTeam(workspaceId: number): Promise<{
  members: WorkspaceMember[];
  invitations: WorkspaceInvitation[];
}> {
  const [membersResponse, invitationsResponse] = await Promise.all([
    serverApiFetch(`/workspaces/${workspaceId}/team/members`),
    serverApiFetch(`/workspaces/${workspaceId}/team/invitations`),
  ]);

  if (!membersResponse.ok || !invitationsResponse.ok) {
    throw new ApiError("The workspace team could not be loaded.", Math.max(
      membersResponse.status,
      invitationsResponse.status,
    ));
  }

  const members = await membersResponse.json() as TeamCollectionResponse<WorkspaceMember>;
  const invitations = await invitationsResponse.json() as TeamCollectionResponse<WorkspaceInvitation>;

  return { members: members.data, invitations: invitations.data };
}
