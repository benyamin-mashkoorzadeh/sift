import type { WorkspaceRole } from "@/types/auth";

export type ManageableWorkspaceRole = Exclude<WorkspaceRole, "owner">;

export interface WorkspaceMember {
  id: number;
  name: string;
  email: string;
  role: WorkspaceRole;
  joined_at: string;
}

export interface WorkspaceInvitation {
  id: number;
  email: string;
  role: ManageableWorkspaceRole;
  created_at: string;
  expires_at: string;
}

export interface WorkspaceInvitationLink {
  invitation: WorkspaceInvitation;
  invitation_url: string;
}

export interface TeamCollectionResponse<T> {
  data: T[];
}

export interface TeamItemResponse<T> {
  data: T;
}

export interface TeamApiErrorResponse {
  message?: string;
  code?: string;
  errors?: Record<string, string[]>;
}

export type InvitationAccountState = "new_account" | "existing_account";

export interface InvitationPreview {
  workspace: { name: string };
  email: string;
  role: ManageableWorkspaceRole;
  expires_at: string;
  account_state: InvitationAccountState;
}

export interface InvitationPreviewResponse {
  data: InvitationPreview;
}
