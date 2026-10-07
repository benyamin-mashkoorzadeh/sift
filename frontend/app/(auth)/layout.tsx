import type { ReactNode } from "react";
import { redirect } from "next/navigation";
import { ScanSearch } from "lucide-react";
import { getAuthenticatedContext } from "@/lib/auth.server";

export default async function AuthLayout({ children }: { children: ReactNode }) {
  const context = await getAuthenticatedContext();

  if (context) redirect("/overview");

  return (
    <main className="grid min-h-screen place-items-center bg-background px-5 py-10 max-[560px]:items-start max-[560px]:px-4 max-[560px]:py-7">
      <section className="w-[min(100%,480px)]">
        <div className="mb-6 flex items-center justify-center gap-2.75 text-[21px] font-extrabold tracking-[-.04em] text-foreground">
          <span className="grid size-9 place-items-center rounded-[9px] bg-primary text-surface"><ScanSearch aria-hidden="true" size={21} /></span>
          <span>Sift</span>
        </div>
        {children}
        <p className="mt-4.5 mb-0 text-center text-[11px] text-muted">Grounded support knowledge, made useful.</p>
      </section>
    </main>
  );
}
