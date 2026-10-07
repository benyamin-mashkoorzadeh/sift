"use client";

import {
  ArrowUp,
  CircleHelp,
  LoaderCircle,
  MessageCircleMore,
  ScanSearch,
  X,
} from "lucide-react";
import {
  useEffect,
  useRef,
  useState,
} from "react";
import type { FormEvent, KeyboardEvent, MutableRefObject } from "react";
import {
  DemoWidgetApiError,
  sendDemoWidgetQuestion,
} from "@/lib/demo-widget-api";
import type { WidgetAnswerStatus } from "@/types/widget";

const MAX_QUESTION_LENGTH = 500;
const CHARACTER_COUNT_THRESHOLD = 400;

interface TranscriptMessage {
  id: number;
  role: "customer" | "assistant";
  content: string;
  status?: WidgetAnswerStatus;
}

export function LandingDemoWidget() {
  const [open, setOpen] = useState(false);
  const [question, setQuestion] = useState("");
  const [messages, setMessages] = useState<TranscriptMessage[]>([]);
  const [submitting, setSubmitting] = useState(false);
  const [validationError, setValidationError] = useState<string | null>(null);
  const [requestError, setRequestError] = useState<string | null>(null);
  const launcherRef = useRef<HTMLButtonElement | null>(null);
  const textareaRef = useRef<HTMLTextAreaElement | null>(null);
  const endRef = useRef<HTMLDivElement | null>(null);
  const requestRef = useRef<AbortController | null>(null);
  const sequenceRef = useRef(0);

  useEffect(() => () => requestRef.current?.abort(), []);

  useEffect(() => {
    if (open) textareaRef.current?.focus();
  }, [open]);

  useEffect(() => {
    if (!open) return;

    const reduceMotion = window.matchMedia("(prefers-reduced-motion: reduce)").matches;
    endRef.current?.scrollIntoView({
      block: "nearest",
      behavior: reduceMotion ? "auto" : "smooth",
    });
  }, [messages, open, requestError, submitting]);

  async function submit(event?: FormEvent<HTMLFormElement>) {
    event?.preventDefault();
    const normalized = question.trim();

    if (submitting) return;
    if (!normalized) {
      setValidationError("Enter a question to continue.");
      return;
    }
    if (normalized.length > MAX_QUESTION_LENGTH) {
      setValidationError(`Keep your question under ${MAX_QUESTION_LENGTH} characters.`);
      return;
    }

    const controller = new AbortController();
    requestRef.current = controller;
    setQuestion("");
    setValidationError(null);
    setRequestError(null);
    setSubmitting(true);
    setMessages((current) => [...current, message("customer", normalized, sequenceRef)]);

    try {
      const answer = await sendDemoWidgetQuestion(normalized, controller.signal);
      setMessages((current) => [
        ...current,
        message("assistant", answer.answer, sequenceRef, answer.status),
      ]);
    } catch (error) {
      if (error instanceof DOMException && error.name === "AbortError") return;
      setRequestError(error instanceof DemoWidgetApiError
        ? error.message
        : "We couldn't connect right now. Check your connection and try again.");
    } finally {
      if (requestRef.current === controller) {
        requestRef.current = null;
        setSubmitting(false);
        window.requestAnimationFrame(() => textareaRef.current?.focus());
      }
    }
  }

  function handleKeyDown(event: KeyboardEvent<HTMLTextAreaElement>) {
    if (event.key === "Enter" && !event.shiftKey) {
      event.preventDefault();
      void submit();
    }
  }

  function close() {
    setOpen(false);
    window.requestAnimationFrame(() => launcherRef.current?.focus());
  }

  if (!open) {
    return (
      <button
        aria-label="Open the Lumenfield Supply Demo assistant"
        className="fixed right-[max(22px,env(safe-area-inset-right))] bottom-[max(22px,env(safe-area-inset-bottom))] z-70 grid size-14.5 cursor-pointer place-items-center rounded-[15px] border border-primary bg-primary text-surface shadow-[0_12px_30px_rgba(33,29,30,.2)] transition-[background,border-color,transform] duration-160 hover:-translate-y-px hover:border-primary-hover hover:bg-primary-hover max-[520px]:right-[max(16px,env(safe-area-inset-right))] max-[520px]:bottom-[max(16px,env(safe-area-inset-bottom))] motion-reduce:transition-none motion-reduce:hover:translate-y-0"
        onClick={() => setOpen(true)}
        ref={launcherRef}
        type="button"
      >
        <MessageCircleMore aria-hidden="true" size={25} strokeWidth={1.9} />
      </button>
    );
  }

  return (
    <section
      aria-label="Lumenfield Supply Demo assistant"
      className="fixed right-[max(22px,env(safe-area-inset-right))] bottom-[max(22px,env(safe-area-inset-bottom))] z-70 grid h-[min(610px,calc(100dvh_-_44px))] min-h-0 w-[min(390px,calc(100vw_-_32px))] grid-rows-[auto_minmax(0,1fr)_auto] overflow-hidden rounded-2xl border border-border bg-surface shadow-[0_20px_58px_rgba(33,29,30,.2)] max-[520px]:right-[max(10px,env(safe-area-inset-right))] max-[520px]:bottom-[max(10px,env(safe-area-inset-bottom))] max-[520px]:h-[min(620px,calc(100dvh_-_20px))] max-[520px]:w-[calc(100vw_-_20px)] max-[520px]:rounded-[14px]"
      onKeyDown={(event) => {
        if (event.key === "Escape") close();
      }}
      role="dialog"
    >
      <header className="flex min-w-0 items-center justify-between gap-4 border-b border-border bg-surface px-4 py-3.75 max-[520px]:px-3.5 max-[520px]:py-3.25">
        <div className="flex min-w-0 items-center gap-2.5 [&_h2]:m-0 [&_h2]:overflow-hidden [&_h2]:text-ellipsis [&_h2]:whitespace-nowrap [&_h2]:text-sm [&_h2]:font-bold [&_h2]:tracking-[-.018em] [&_p]:mt-0.5 [&_p]:mb-0 [&_p]:text-[10px] [&_p]:text-muted">
          <span className="grid size-8 shrink-0 place-items-center rounded-lg bg-primary text-surface"><ScanSearch aria-hidden="true" size={17} /></span>
          <div>
            <h2>Lumenfield Supply Demo</h2>
            <p>Customer support · Powered by Sift</p>
          </div>
        </div>
        <button aria-label="Close Demo assistant" className="grid size-8.5 shrink-0 cursor-pointer place-items-center rounded-lg border border-transparent bg-transparent text-secondary transition-colors duration-150 hover:border-border hover:bg-elevated hover:text-foreground motion-reduce:transition-none" onClick={close} type="button">
          <X aria-hidden="true" size={19} />
        </button>
      </header>

      <div aria-live="polite" aria-relevant="additions text" className="min-h-0 overflow-y-auto overscroll-contain bg-background [scrollbar-color:var(--border)_transparent]">
        <div className="flex min-h-full flex-col gap-3.5 px-4 py-5 max-[520px]:px-3.25 max-[520px]:py-4.25">
          <article className="w-fit max-w-[88%] self-start [&>span]:mt-0 [&>span]:mr-0 [&>span]:mb-1.25 [&>span]:ml-0.75 [&>span]:block [&>span]:text-[9px] [&>span]:font-bold [&>span]:tracking-[.06em] [&>span]:text-muted [&>span]:uppercase [&>p]:m-0 [&>p]:[overflow-wrap:anywhere] [&>p]:whitespace-pre-wrap [&>p]:rounded-[11px] [&>p]:border [&>p]:border-border [&>p]:bg-surface [&>p]:px-3 [&>p]:py-2.5 [&>p]:text-[13px] [&>p]:leading-[1.55]">
            <span>Sift</span>
            <p>Hi! Ask me about Lumenfield Supply&apos;s returns, shipping, warranty, billing, or product support.</p>
          </article>

          {messages.map((item) => (
            <article
              className={`w-fit max-w-[88%] [&>span]:mt-0 [&>span]:mr-0 [&>span]:mb-1.25 [&>span]:ml-0.75 [&>span]:block [&>span]:text-[9px] [&>span]:font-bold [&>span]:tracking-[.06em] [&>span]:text-muted [&>span]:uppercase [&>p]:m-0 [&>p]:[overflow-wrap:anywhere] [&>p]:whitespace-pre-wrap [&>p]:rounded-[11px] [&>p]:border [&>p]:px-3 [&>p]:py-2.5 [&>p]:text-[13px] [&>p]:leading-[1.55] ${item.role === "customer" ? "self-end [&>span]:mr-0.75 [&>span]:ml-0 [&>span]:text-right [&>p]:border-[color-mix(in_srgb,var(--primary)_22%,var(--border))] [&>p]:bg-primary-soft [&>p]:text-foreground" : "self-start [&>p]:border-border [&>p]:bg-surface"}`}
              key={item.id}
            >
              <span>{item.role === "customer" ? "You" : "Sift"}</span>
              <p>{item.content}</p>
              {item.status === "needs_review" && (
                <small className="mt-1.75 ml-0.75 inline-flex items-center gap-1.25 text-[9px] font-bold tracking-[.045em] text-warning uppercase">
                  <CircleHelp aria-hidden="true" size={13} /> Needs Review
                </small>
              )}
            </article>
          ))}

          {submitting && (
            <div className="flex items-center gap-1.75 self-start text-[11px] text-secondary" role="status">
              <LoaderCircle aria-hidden="true" className="animate-spin motion-reduce:animate-none" size={15} />
              <span>Sift is checking the Demo knowledge…</span>
            </div>
          )}

          {requestError && <p className="m-0 self-stretch rounded-[9px] border border-[color-mix(in_srgb,var(--danger)_24%,var(--border))] bg-danger-soft px-2.75 py-2.5 text-[11px] leading-[1.5] text-danger" role="alert">{requestError}</p>}
          <div ref={endRef} />
        </div>
      </div>

      <form className="border-t border-border bg-surface px-3.5 pt-3.25 pb-3 max-[520px]:px-3 max-[520px]:pt-2.75 max-[520px]:pb-[max(11px,env(safe-area-inset-bottom))]" onSubmit={submit}>
        <label className="absolute size-px overflow-hidden [clip-path:inset(50%)] whitespace-nowrap" htmlFor="landing-demo-widget-question">Type a question</label>
        <div className="flex items-end gap-2 rounded-[11px] border border-border bg-background py-1.75 pr-1.75 pl-2.75 transition-[border-color,box-shadow] duration-150 focus-within:border-primary focus-within:shadow-[0_0_0_3px_var(--focus-ring)] motion-reduce:transition-none">
          <textarea
            className="min-h-8.5 max-h-23 w-full resize-none border-0 bg-transparent pt-1.75 pb-1 text-[13px] leading-[1.45] text-foreground outline-none placeholder:text-muted disabled:cursor-wait disabled:opacity-65"
            aria-describedby={validationError ? "landing-demo-widget-question-error" : undefined}
            aria-invalid={validationError ? "true" : undefined}
            disabled={submitting}
            id="landing-demo-widget-question"
            maxLength={MAX_QUESTION_LENGTH}
            onChange={(event) => {
              setQuestion(event.target.value);
              if (validationError) setValidationError(null);
              if (requestError) setRequestError(null);
            }}
            onKeyDown={handleKeyDown}
            placeholder="Ask a support question…"
            ref={textareaRef}
            rows={1}
            value={question}
          />
          <button aria-label="Send question" className="grid size-8.5 shrink-0 cursor-pointer place-items-center rounded-[9px] border border-primary bg-primary text-surface transition-[background,border-color,opacity] duration-150 hover:not-disabled:border-primary-hover hover:not-disabled:bg-primary-hover disabled:cursor-not-allowed disabled:opacity-42 motion-reduce:transition-none" disabled={submitting || question.trim() === ""} type="submit">
            <ArrowUp aria-hidden="true" size={19} strokeWidth={2.2} />
          </button>
        </div>
        <div className="mt-1.5 flex min-h-3.75 items-start justify-between gap-2.5 text-[9px] leading-[1.4] text-muted [&_p]:m-0 [&_p]:text-danger">
          {validationError
            ? <p id="landing-demo-widget-question-error" role="alert">{validationError}</p>
            : <span>Each question uses the prepared Demo knowledge.</span>}
          {question.length >= CHARACTER_COUNT_THRESHOLD && (
            <span>{question.length} / {MAX_QUESTION_LENGTH}</span>
          )}
        </div>
      </form>
    </section>
  );
}

function message(
  role: TranscriptMessage["role"],
  content: string,
  sequence: MutableRefObject<number>,
  status?: WidgetAnswerStatus,
): TranscriptMessage {
  sequence.current += 1;

  return { id: sequence.current, role, content, status };
}
