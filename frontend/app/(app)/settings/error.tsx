"use client";

import { AlertCircle, RotateCcw } from "lucide-react";
export default function SettingsError({ reset }: { error: Error; reset: () => void }) {
  return (
    <section className="mx-auto w-full max-w-230">
      <header className="max-w-165 [&_h1]:m-0 [&_h1]:text-[clamp(31px,4vw,43px)] [&_h1]:leading-[1.08] [&_h1]:tracking-[-.045em]">
        <p className="mt-0 mb-2.25 text-[11px] font-bold tracking-[.13em] text-primary uppercase">Workspace preferences</p>
        <h1>Settings</h1>
      </header>
      <div className="mt-8.5 flex min-h-80 flex-col items-center justify-center rounded-[13px] border border-dashed border-border bg-surface px-6 py-10 text-center [&_h2]:mt-4.25 [&_h2]:mb-0 [&_h2]:text-[17px] [&_p]:mt-2 [&_p]:mb-5 [&_p]:max-w-102.5 [&_p]:text-[13px] [&_p]:leading-[1.6] [&_p]:text-secondary">
        <span className="grid size-12 place-items-center rounded-xl border border-border bg-elevated text-danger"><AlertCircle aria-hidden="true" size={22} /></span>
        <h2>Workspace settings could not be loaded</h2>
        <p>Check the API connection and try again.</p>
        <button className="inline-flex min-h-9 cursor-pointer items-center justify-center gap-1.75 rounded-lg border border-border bg-elevated px-2.75 text-xs font-bold text-secondary transition-[color,border-color] duration-160 hover:border-primary hover:text-primary motion-reduce:transition-none" onClick={reset} type="button">
          <RotateCcw aria-hidden="true" size={16} /> Try again
        </button>
      </div>
    </section>
  );
}
