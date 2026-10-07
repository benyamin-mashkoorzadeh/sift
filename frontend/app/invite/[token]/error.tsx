"use client";

import { AlertCircle, RotateCcw, ScanSearch } from "lucide-react";

export default function InvitationError({ reset }: { error: Error; reset: () => void }) {
  return (
    <main className="grid min-h-screen place-items-center bg-background px-5 py-10 max-[560px]:items-start max-[560px]:px-4 max-[560px]:py-7">
      <section className="w-[min(100%,580px)]">
        <div className="mb-6 flex items-center justify-center gap-2.5 text-xl font-extrabold tracking-[-.04em] text-foreground"><span className="grid size-8.75 place-items-center rounded-[9px] bg-primary text-surface"><ScanSearch aria-hidden="true" size={20} /></span>Sift</div>
        <div className="grid min-h-75 place-items-center content-center rounded-[14px] border border-border bg-surface p-8 text-center max-[560px]:px-4.75 max-[560px]:py-6 [&_h1]:mt-3.75 [&_h1]:mb-0 [&_h1]:text-xl [&_p]:mt-1.75 [&_p]:mb-0 [&_p]:max-w-97.5 [&_p]:text-xs [&_p]:leading-[1.6] [&_p]:text-secondary">
          <span className="grid size-12 place-items-center rounded-xl bg-danger-soft text-danger"><AlertCircle aria-hidden="true" size={23} /></span>
          <h1>Invitation could not be loaded</h1>
          <p>Check your connection and try again.</p>
          <button className="mt-4.25 flex min-h-10.75 cursor-pointer items-center justify-center gap-2 rounded-[9px] border border-primary bg-primary px-4 text-xs font-bold text-surface transition-colors duration-160 hover:border-primary-hover hover:bg-primary-hover" onClick={reset} type="button"><RotateCcw aria-hidden="true" size={15} /> Try again</button>
        </div>
      </section>
    </main>
  );
}
