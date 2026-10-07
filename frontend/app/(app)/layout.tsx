import type { ReactNode } from "react";

import { AppSidebar } from "@/components/app-shell/app-sidebar";
import { requireAuthenticatedContext } from "@/lib/auth.server";

export default async function AppLayout({ children }: { children: ReactNode }) {
  const context = await requireAuthenticatedContext();

  return (
    <div className="min-h-screen">
      <AppSidebar context={context} />
      <main className="ml-(--sidebar-width) min-w-0 px-[clamp(24px,5vw,72px)] pt-12 pb-18 max-[860px]:ml-0 max-[860px]:px-5 max-[860px]:pt-22 max-[860px]:pb-12">{children}</main>
    </div>
  );
}
