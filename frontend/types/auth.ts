export type WorkspaceRole = "owner" | "admin" | "member";

export type WorkspaceAccessMode = "normal" | "demo";

export type WorkspacePermission =
  | "overview.view"
  | "assistant.use"
  | "knowledge.view"
  | "knowledge.manage"
  | "conversations.view"
  | "review.view"
  | "review.resolve"
  | "settings.view"
  | "settings.update"
  | "team.view"
  | "team.manage";

export interface AuthenticatedContext {
  user: {
    id: number;
    name: string;
    email: string;
  };
  workspace: {
    id: number;
    name: string;
    role: WorkspaceRole;
  };
  access_mode: WorkspaceAccessMode;
  permissions: WorkspacePermission[];
}

export interface AuthenticatedContextResponse {
  data: AuthenticatedContext;
}

export interface AuthErrorResponse {
  message?: string;
  code?: string;
  errors?: Record<string, string[]>;
}
