import "server-only";

import { serverApiFetch } from "@/lib/server-api";
import type { InvitationPreview, InvitationPreviewResponse } from "@/types/team";

export async function getInvitationPreview(token: string): Promise<InvitationPreview | null> {
  const response = await serverApiFetch(`/invitations/${encodeURIComponent(token)}`);

  if (response.status === 404) return null;
  if (!response.ok) throw new Error("The invitation could not be loaded.");

  const payload = await response.json() as InvitationPreviewResponse;

  return payload.data;
}
