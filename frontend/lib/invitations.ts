import { sanctumFetch } from "@/lib/sanctum";
import type { AuthenticatedContextResponse } from "@/types/auth";
import type { TeamApiErrorResponse } from "@/types/team";

export class InvitationApiError extends Error {
  constructor(
    message: string,
    public readonly status: number,
    public readonly errors: Record<string, string[]> = {},
    public readonly code?: string,
  ) {
    super(message);
    this.name = "InvitationApiError";
  }
}

export async function acceptInvitation(
  token: string,
  data: Record<string, string>,
): Promise<AuthenticatedContextResponse> {
  const response = await sanctumFetch(`/invitations/${encodeURIComponent(token)}/accept`, {
    method: "POST",
    headers: { "Content-Type": "application/json" },
    body: JSON.stringify(data),
  });
  const payload = await parseJson(response);

  if (!response.ok) {
    const error = payload as TeamApiErrorResponse | null;
    throw new InvitationApiError(
      error?.message ?? invitationFallback(response.status),
      response.status,
      error?.errors ?? {},
      error?.code,
    );
  }

  return payload as AuthenticatedContextResponse;
}

function invitationFallback(status: number): string {
  if (status === 404) return "This invitation is no longer available.";
  if (status === 403) return "This invitation belongs to a different account.";
  if (status === 409) return "This invitation cannot be accepted.";
  if (status === 422) return "Check the highlighted fields and try again.";

  return "Sift could not accept this invitation. Please try again.";
}

async function parseJson(response: Response): Promise<unknown> {
  try {
    return await response.json();
  } catch {
    return null;
  }
}
