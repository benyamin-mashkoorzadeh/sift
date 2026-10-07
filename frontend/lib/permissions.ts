import type { AuthenticatedContext, WorkspacePermission } from "@/types/auth";

export function hasPermission(
  context: AuthenticatedContext,
  permission: WorkspacePermission,
): boolean {
  return context.permissions.includes(permission);
}
