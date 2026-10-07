"use client";

import { AlertCircle, RotateCcw } from "lucide-react";
export default function TeamError({ reset }: { error: Error; reset: () => void }) {
  return (
    <section className="mx-auto w-full max-w-280">
      <div className="grid min-h-105 place-items-center content-center text-center [&>span]:grid [&>span]:size-12 [&>span]:place-items-center [&>span]:rounded-xl [&>span]:bg-danger-soft [&>span]:text-danger [&>h1]:mt-4 [&>h1]:mb-0 [&>h1]:text-[19px] [&>p]:mt-1.75 [&>p]:mb-4.5 [&>p]:text-xs [&>p]:text-secondary">
        <span><AlertCircle aria-hidden="true" size={23} /></span>
        <h1>Team could not be loaded</h1>
        <p>Check the API connection and try again.</p>
        <button className="inline-flex min-h-9.25 cursor-pointer items-center justify-center gap-1.75 rounded-lg border border-border bg-elevated px-3 text-[11px] font-bold text-secondary transition-[color,border-color] duration-160 hover:border-primary hover:text-primary" onClick={reset} type="button"><RotateCcw aria-hidden="true" size={15} /> Try again</button>
      </div>
    </section>
  );
}
