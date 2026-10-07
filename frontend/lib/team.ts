import { sanctumFetch } from "@/lib/sanctum";
import type {
  ManageableWorkspaceRole,
  TeamApiErrorResponse,
  TeamItemResponse,
  WorkspaceInvitationLink,
  WorkspaceMember,
} from "@/types/team";

export class TeamApiError extends Error {
  constructor(
    message: string,
    public readonly status: number,
    public readonly errors: Record<string, string[]> = {},
    public readonly code?: string,
  ) {
    super(message);
    this.name = "TeamApiError";
  }
}

export async function createWorkspaceInvitation(
  workspaceId: number,
  email: string,
  role: ManageableWorkspaceRole,
): Promise<WorkspaceInvitationLink> {
  const response = await teamMutation<TeamItemResponse<WorkspaceInvitationLink>>(
    `/workspaces/${workspaceId}/team/invitations`,
    { method: "POST", body: JSON.stringify({ email, role }) },
  );

  return response.data;
}

export async function regenerateWorkspaceInvitation(
  workspaceId: number,
  invitationId: number,
): Promise<WorkspaceInvitationLink> {
  const response = await teamMutation<TeamItemResponse<WorkspaceInvitationLink>>(
    `/workspaces/${workspaceId}/team/invitations/${invitationId}/regenerate-link`,
    { method: "POST" },
  );

  return response.data;
}

export async function cancelWorkspaceInvitation(
  workspaceId: number,
  invitationId: number,
): Promise<void> {
  await teamMutation<void>(
    `/workspaces/${workspaceId}/team/invitations/${invitationId}`,
    { method: "DELETE" },
  );
}

export async function updateWorkspaceMemberRole(
  workspaceId: number,
  userId: number,
  role: ManageableWorkspaceRole,
): Promise<WorkspaceMember> {
  const response = await teamMutation<TeamItemResponse<WorkspaceMember>>(
    `/workspaces/${workspaceId}/team/members/${userId}`,
    { method: "PATCH", body: JSON.stringify({ role }) },
  );

  return response.data;
}

export async function removeWorkspaceMember(
  workspaceId: number,
  userId: number,
): Promise<void> {
  await teamMutation<void>(
    `/workspaces/${workspaceId}/team/members/${userId}`,
    { method: "DELETE" },
  );
}

async function teamMutation<T>(path: string, init: RequestInit): Promise<T> {
  const response = await sanctumFetch(path, {
    ...init,
    headers: { "Content-Type": "application/json" },
  });
  const payload = response.status === 204 ? null : await parseJson(response);

  if (!response.ok) {
    const error = payload as TeamApiErrorResponse | null;
    throw new TeamApiError(
      response.status === 422
        ? error?.message ?? "Check the highlighted fields and try again."
        : error?.message ?? "The Team request could not be completed.",
      response.status,
      error?.errors ?? {},
      error?.code,
    );
  }

  return payload as T;
}

async function parseJson(response: Response): Promise<unknown> {
  try {
    return await response.json();
  } catch {
    return null;
  }
}
