import { TeamPanel } from "@/components/team/team-panel";
import { requirePermission } from "@/lib/auth.server";
import { getWorkspaceTeam } from "@/lib/team.server";

export const dynamic = "force-dynamic";

export default async function TeamPage() {
  const context = await requirePermission("team.view");
  const team = await getWorkspaceTeam(context.workspace.id);

  return (
    <section className="mx-auto w-full max-w-280">
      <header className="flex items-end justify-between gap-6 max-[680px]:flex-col max-[680px]:items-stretch">
        <div>
          <p className="mt-0 mb-2.25 text-[11px] font-bold tracking-[.13em] text-primary uppercase">Workspace access</p>
          <h1 className="m-0 text-[clamp(31px,4vw,43px)] leading-[1.08] tracking-[-.045em]">Team</h1>
          <p className="mt-3.25 mb-0 text-[15px] leading-[1.65] text-secondary">Manage people who have access to this workspace.</p>
        </div>
      </header>
      <TeamPanel
        invitations={team.invitations}
        members={team.members}
        workspaceId={context.workspace.id}
      />
    </section>
  );
}
