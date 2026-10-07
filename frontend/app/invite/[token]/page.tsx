import type { Metadata } from "next";
import { InvitationAcceptance } from "@/components/invitations/invitation-acceptance";
import { getAuthenticatedContext } from "@/lib/auth.server";
import { getInvitationPreview } from "@/lib/invitations.server";

export const dynamic = "force-dynamic";
export const metadata: Metadata = {
  title: "Workspace invitation · Sift",
  robots: { index: false, follow: false },
};

export default async function InvitationPage({ params }: PageProps<"/invite/[token]">) {
  const { token } = await params;
  const [preview, context] = await Promise.all([
    getInvitationPreview(token),
    getAuthenticatedContext(),
  ]);

  return (
    <main className="grid min-h-screen place-items-center bg-background px-5 py-10 max-[560px]:items-start max-[560px]:px-4 max-[560px]:py-7">
      <InvitationAcceptance
        authenticatedEmail={context?.user.email ?? null}
        preview={preview}
        token={token}
      />
    </main>
  );
}
