import { AssistantPanel } from "@/components/assistant/assistant-panel";
import { requirePermission } from "@/lib/auth.server";

export default async function AssistantPage() {
  const context = await requirePermission("assistant.use");

  return <AssistantPanel workspaceId={context.workspace.id} />;
}
