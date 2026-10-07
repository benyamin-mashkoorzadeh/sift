import { ScanSearch } from "lucide-react";

export default function InvitationLoading() {
  return (
    <main aria-busy="true" className="grid min-h-screen place-items-center bg-background px-5 py-10 max-[560px]:items-start max-[560px]:px-4 max-[560px]:py-7">
      <section className="w-[min(100%,580px)]">
        <div className="mb-6 flex items-center justify-center gap-2.5 text-xl font-extrabold tracking-[-.04em] text-foreground"><span className="grid size-8.75 place-items-center rounded-[9px] bg-primary text-surface"><ScanSearch aria-hidden="true" size={20} /></span>Sift</div>
        <div className="h-130 animate-pulse rounded-xl bg-elevated motion-reduce:animate-none" />
      </section>
    </main>
  );
}
