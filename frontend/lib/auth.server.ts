import "server-only";

import { cache } from "react";
import { redirect } from "next/navigation";
import { ApiError } from "@/lib/api";
import { hasPermission } from "@/lib/permissions";
import { serverApiFetch } from "@/lib/server-api";
import type {
  AuthenticatedContext,
  AuthenticatedContextResponse,
  WorkspacePermission,
} from "@/types/auth";

export const getAuthenticatedContext = cache(async (): Promise<AuthenticatedContext | null> => {
  const response = await serverApiFetch("/auth/user");

  if (response.status === 401) return null;

  if (!response.ok) {
    throw new ApiError("The authenticated workspace could not be loaded.", response.status);
  }

  const payload = await response.json() as AuthenticatedContextResponse;

  return payload.data;
});

export async function requireAuthenticatedContext(): Promise<AuthenticatedContext> {
  const context = await getAuthenticatedContext();

  if (!context) redirect("/login");

  return context;
}

export async function requirePermission(
  permission: WorkspacePermission,
): Promise<AuthenticatedContext> {
  const context = await requireAuthenticatedContext();

  if (!hasPermission(context, permission)) redirect("/overview");

  return context;
}
