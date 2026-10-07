import { WidgetShell } from "@/components/widget/widget-shell";
import { getWidgetBootstrap } from "@/lib/widget-api";

export const dynamic = "force-dynamic";

export default async function WidgetPage({ params }: PageProps<"/widget/[widgetKey]">) {
  const { widgetKey } = await params;
  let workspaceName: string | undefined;
  let unavailable = false;

  try {
    const bootstrap = await getWidgetBootstrap(widgetKey);
    workspaceName = bootstrap.workspace_name;
  } catch {
    unavailable = true;
  }

  return <WidgetShell unavailable={unavailable} widgetKey={widgetKey} workspaceName={workspaceName} />;
}
