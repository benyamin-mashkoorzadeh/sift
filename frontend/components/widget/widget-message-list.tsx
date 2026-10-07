"use client";

import { LoaderCircle } from "lucide-react";
import { useEffect, useRef } from "react";
import type { WidgetTranscriptMessage } from "@/types/widget";

interface WidgetMessageListProps {
  messages: WidgetTranscriptMessage[];
  submitting: boolean;
  error: string | null;
}

export function WidgetMessageList({ messages, submitting, error }: WidgetMessageListProps) {
  const endRef = useRef<HTMLDivElement | null>(null);

  useEffect(() => {
    const reduceMotion = window.matchMedia("(prefers-reduced-motion: reduce)").matches;
    endRef.current?.scrollIntoView({
      block: "nearest",
      behavior: reduceMotion ? "auto" : "smooth",
    });
  }, [error, messages, submitting]);

  return (
    <div aria-live="polite" aria-relevant="additions text" className="min-h-0 overflow-y-auto overscroll-contain bg-background [scrollbar-color:var(--border)_transparent]">
      <div className="flex min-h-full flex-col gap-3.5 px-4 py-5 max-[480px]:px-3.25 max-[480px]:py-4.25">
        <article className="w-fit max-w-[88%] self-start [&>span]:mt-0 [&>span]:mr-0 [&>span]:mb-1.25 [&>span]:ml-0.75 [&>span]:block [&>span]:text-[9px] [&>span]:font-bold [&>span]:tracking-[.06em] [&>span]:text-muted [&>span]:uppercase [&>p]:m-0 [&>p]:[overflow-wrap:anywhere] [&>p]:whitespace-pre-wrap [&>p]:rounded-[11px] [&>p]:border [&>p]:border-border [&>p]:bg-surface [&>p]:px-3 [&>p]:py-2.5 [&>p]:text-[13px] [&>p]:leading-[1.55]">
          <span>Sift</span>
          <p>Hi! How can I help?</p>
        </article>

        {messages.map((item) => (
          <article
            className={`w-fit max-w-[88%] [&>span]:mt-0 [&>span]:mr-0 [&>span]:mb-1.25 [&>span]:ml-0.75 [&>span]:block [&>span]:text-[9px] [&>span]:font-bold [&>span]:tracking-[.06em] [&>span]:text-muted [&>span]:uppercase [&>p]:m-0 [&>p]:[overflow-wrap:anywhere] [&>p]:whitespace-pre-wrap [&>p]:rounded-[11px] [&>p]:border [&>p]:px-3 [&>p]:py-2.5 [&>p]:text-[13px] [&>p]:leading-[1.55] ${item.role === "customer" ? "self-end [&>span]:mr-0.75 [&>span]:ml-0 [&>span]:text-right [&>p]:border-[color-mix(in_srgb,var(--primary)_22%,var(--border))] [&>p]:bg-primary-soft [&>p]:text-foreground" : "self-start [&>p]:border-border [&>p]:bg-surface"}`}
            key={item.id}
          >
            <span>{item.role === "customer" ? "You" : "Sift"}</span>
            <p>{item.content}</p>
          </article>
        ))}

        {submitting && (
          <div className="flex items-center gap-1.75 self-start text-[11px] text-secondary" role="status">
            <LoaderCircle aria-hidden="true" className="animate-spin motion-reduce:animate-none" size={15} />
            <span>Sift is checking the knowledge base…</span>
          </div>
        )}

        {error && <p className="m-0 self-stretch rounded-[9px] border border-[color-mix(in_srgb,var(--danger)_24%,var(--border))] bg-danger-soft px-2.75 py-2.5 text-[11px] leading-[1.5] text-danger" role="alert">{error}</p>}
        <div ref={endRef} />
      </div>
    </div>
  );
}
