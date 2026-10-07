import { WorkspaceOverview } from "@/components/overview/workspace-overview";
import { requirePermission } from "@/lib/auth.server";
import { getWorkspaceOverview } from "@/lib/overview";
import { hasPermission } from "@/lib/permissions";

export const dynamic = "force-dynamic";

export default async function OverviewPage() {
  const context = await requirePermission("overview.view");
  const overview = await getWorkspaceOverview(context.workspace.id);

  return (
    <WorkspaceOverview
      canUseAssistant={hasPermission(context, "assistant.use")}
      canViewConversations={hasPermission(context, "conversations.view")}
      canViewKnowledge={hasPermission(context, "knowledge.view")}
      canViewReview={hasPermission(context, "review.view")}
      overview={overview}
    />
  );
}
