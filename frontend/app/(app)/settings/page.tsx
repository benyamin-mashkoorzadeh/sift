import { WorkspaceSettings } from "@/components/settings/workspace-settings";
import { requirePermission } from "@/lib/auth.server";
import { hasPermission } from "@/lib/permissions";
import { getWorkspaceSettings } from "@/lib/settings.server";

export const dynamic = "force-dynamic";

export default async function SettingsPage() {
  const context = await requirePermission("settings.view");
  const workspaceId = context.workspace.id;
  const settings = await getWorkspaceSettings(workspaceId);

  return (
    <section className="mx-auto w-full max-w-230">
      <header className="max-w-165 [&_h1]:m-0 [&_h1]:text-[clamp(31px,4vw,43px)] [&_h1]:leading-[1.08] [&_h1]:tracking-[-.045em]">
        <p className="mt-0 mb-2.25 text-[11px] font-bold tracking-[.13em] text-primary uppercase">Workspace preferences</p>
        <h1>Settings</h1>
        <p className="mt-3.25 mb-0 text-[15px] leading-[1.65] text-secondary">
          Manage the small set of workspace details Sift currently supports.
        </p>
      </header>
      <WorkspaceSettings
        canUpdate={hasPermission(context, "settings.update")}
        settings={settings}
        workspaceId={workspaceId}
      />
    </section>
  );
}
