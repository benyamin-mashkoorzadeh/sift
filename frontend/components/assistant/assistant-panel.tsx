"use client";

import {
  AlertTriangle,
  BookOpenText,
  CheckCircle2,
  FileText,
  LoaderCircle,
  Search,
  Send,
} from "lucide-react";
import { FormEvent, useEffect, useRef, useState } from "react";
import { AssistantApiError, askWorkspaceQuestion } from "@/lib/assistant";
import type { AssistantAnswer } from "@/types/assistant";

interface AssistantPanelProps {
  workspaceId: number;
}

const MAX_QUESTION_LENGTH = 2000;
const panelClass = "rounded-[13px] border border-border bg-surface";
const resultHeadingClass = "flex items-center gap-3 [&_p]:m-0 [&_p]:text-[10px] [&_p]:font-bold [&_p]:tracking-[.1em] [&_p]:text-secondary [&_p]:uppercase [&_h2]:mt-0.75 [&_h2]:mb-0 [&_h2]:text-[17px] [&_h2]:tracking-[-.015em]";
const resultIconClass = "grid size-9.5 shrink-0 place-items-center rounded-[9px] border";

export function AssistantPanel({ workspaceId }: AssistantPanelProps) {
  const requestRef = useRef<AbortController | null>(null);
  const outcomeRef = useRef<HTMLDivElement | null>(null);
  const [question, setQuestion] = useState("");
  const [submittedQuestion, setSubmittedQuestion] = useState<string | null>(null);
  const [answer, setAnswer] = useState<AssistantAnswer | null>(null);
  const [fieldError, setFieldError] = useState<string | null>(null);
  const [requestError, setRequestError] = useState<string | null>(null);
  const [loading, setLoading] = useState(false);

  useEffect(() => () => requestRef.current?.abort(), []);

  useEffect(() => {
    if (!loading && (answer || requestError)) {
      outcomeRef.current?.focus();
    }
  }, [answer, loading, requestError]);

  async function submit(event: FormEvent<HTMLFormElement>) {
    event.preventDefault();

    const normalizedQuestion = question.trim();

    if (normalizedQuestion === "") {
      setFieldError("Enter a question to continue.");
      return;
    }

    if (normalizedQuestion.length > MAX_QUESTION_LENGTH) {
      setFieldError(`Keep the question under ${MAX_QUESTION_LENGTH.toLocaleString()} characters.`);
      return;
    }

    const controller = new AbortController();
    requestRef.current?.abort();
    requestRef.current = controller;
    setSubmittedQuestion(normalizedQuestion);
    setAnswer(null);
    setFieldError(null);
    setRequestError(null);
    setLoading(true);

    try {
      const result = await askWorkspaceQuestion(workspaceId, normalizedQuestion, controller.signal);
      setAnswer(result);
    } catch (error) {
      if (error instanceof DOMException && error.name === "AbortError") return;

      if (error instanceof AssistantApiError && error.questionError) {
        setFieldError(error.questionError);
      } else if (error instanceof AssistantApiError) {
        setRequestError(error.message);
      } else {
        setRequestError("Sift could not reach the API. Check your connection and try again.");
      }
    } finally {
      if (requestRef.current === controller) {
        requestRef.current = null;
        setLoading(false);
      }
    }
  }

  return (
    <section className="mx-auto w-[min(100%,980px)]">
      <header className="max-w-170 [&_h1]:m-0 [&_h1]:text-[clamp(31px,4vw,43px)] [&_h1]:leading-[1.08] [&_h1]:tracking-[-.045em]">
        <p className="mt-0 mb-2.25 text-[11px] font-bold tracking-[.13em] text-primary uppercase">Knowledge assistant</p>
        <h1>Assistant</h1>
        <p className="mt-3.25 mb-0 text-[15px] leading-[1.65] text-secondary">
          Ask a focused question and Sift will answer only from this workspace&apos;s processed knowledge.
        </p>
      </header>

      <form aria-busy={loading} className="mt-9 rounded-[13px] border border-border bg-surface p-6 max-[620px]:p-5" onSubmit={submit}>
        <div className="mb-6 flex items-center gap-3 max-[620px]:items-start [&_h2]:m-0 [&_h2]:text-base [&_h2]:tracking-[-.015em] [&_p]:mt-1 [&_p]:mb-0 [&_p]:text-xs [&_p]:leading-[1.5] [&_p]:text-secondary">
          <span className="grid size-9.5 shrink-0 place-items-center rounded-[9px] border border-border bg-primary-soft text-primary"><Search aria-hidden="true" size={18} /></span>
          <div>
            <h2>Ask your knowledge base</h2>
            <p>One question at a time. Each answer is checked against retrieved source material.</p>
          </div>
        </div>

        <label className="mb-2 block text-xs font-bold text-foreground" htmlFor="assistant-question">Question</label>
        <textarea
          aria-describedby={`assistant-question-help${fieldError ? " assistant-question-error" : ""}`}
          aria-invalid={fieldError ? "true" : undefined}
          className={`block min-h-30.5 w-full resize-y rounded-[10px] border bg-elevated px-3.75 py-3.5 text-sm leading-[1.65] text-foreground transition-[border-color,background,box-shadow] duration-160 placeholder:text-muted hover:not-disabled:border-muted focus:border-primary focus:outline-0 focus:shadow-[0_0_0_3px_var(--focus-ring)] disabled:cursor-wait disabled:opacity-72 motion-reduce:transition-none ${fieldError ? "border-danger" : "border-border"}`}
          disabled={loading}
          id="assistant-question"
          maxLength={MAX_QUESTION_LENGTH}
          onChange={(event) => {
            setQuestion(event.target.value);
            if (fieldError) setFieldError(null);
          }}
          placeholder="Ask about a policy, process, or support question…"
          rows={4}
          value={question}
        />
        <div className="mt-2.25 flex items-start justify-between gap-5 max-[620px]:flex-col max-[620px]:gap-1.75 [&_p]:m-0 [&_p]:text-[11px] [&_p]:leading-[1.5] [&_p]:text-secondary [&>span]:shrink-0 [&>span]:font-mono [&>span]:text-[10px] [&>span]:text-muted">
          <div>
            <p id="assistant-question-help">Answers use only processed documents in this workspace.</p>
            {fieldError && <p className="mt-1.25! text-danger!" id="assistant-question-error" role="alert">{fieldError}</p>}
          </div>
          <span>{question.length.toLocaleString()} / {MAX_QUESTION_LENGTH.toLocaleString()}</span>
        </div>
        <div className="mt-4.5 flex justify-end max-[620px]:w-full">
          <button className="inline-flex min-h-10.5 cursor-pointer items-center justify-center gap-2 rounded-[9px] border border-primary bg-primary px-4.25 text-[13px] font-bold text-surface transition-[background,border-color,opacity] duration-160 hover:not-disabled:border-primary-hover hover:not-disabled:bg-primary-hover disabled:cursor-not-allowed disabled:opacity-48 max-[620px]:w-full motion-reduce:transition-none" disabled={loading || question.trim() === ""} type="submit">
            {loading
              ? <><LoaderCircle aria-hidden="true" className="animate-spin motion-reduce:animate-none" size={17} /> Searching knowledge…</>
              : <><Send aria-hidden="true" size={16} /> Ask Sift</>}
          </button>
        </div>
      </form>

      <div aria-live="polite" className="mt-5">
        {loading && <LoadingState question={submittedQuestion} />}
        {!loading && requestError && <ErrorState error={requestError} outcomeRef={outcomeRef} />}
        {!loading && answer?.status === "answered" && <AnsweredState answer={answer} outcomeRef={outcomeRef} question={submittedQuestion} />}
        {!loading && answer?.status === "needs_review" && <NeedsReviewState answer={answer} outcomeRef={outcomeRef} question={submittedQuestion} />}
        {!loading && !requestError && !answer && <EmptyState />}
      </div>
    </section>
  );
}

function AnsweredState({ answer, outcomeRef, question }: { answer: AssistantAnswer; outcomeRef: React.RefObject<HTMLDivElement | null>; question: string | null }) {
  return (
    <div className={`${panelClass} p-6.5 focus:outline-2 focus:outline-offset-3 focus:outline-primary max-[620px]:p-5`} ref={outcomeRef} tabIndex={-1}>
      <ResultQuestion question={question} />
      <div className={resultHeadingClass}>
        <span className={`${resultIconClass} border-[#b8d0c5] bg-success-soft text-success`}><CheckCircle2 aria-hidden="true" size={19} /></span>
        <div><p>Grounded answer</p><h2>Answer from your knowledge base</h2></div>
      </div>
      <p className="mt-5.75 mb-0 whitespace-pre-wrap text-[15px] leading-[1.8] text-foreground">{answer.answer}</p>
      <div className="mt-7 border-t border-border pt-5">
        <div className="flex items-center gap-2 text-secondary [&_h3]:m-0 [&_h3]:text-xs [&_h3]:font-bold [&_h3]:tracking-[.06em] [&_h3]:uppercase"><BookOpenText aria-hidden="true" size={16} /><h3>Sources</h3></div>
        <ul className="mt-3 mb-0 grid list-none gap-2 p-0">
          {answer.citations.map((citation) => (
            <li className="flex min-w-0 items-center gap-2.5 rounded-[9px] border border-border bg-elevated px-3 py-2.75" key={citation.chunk_id}>
              <span className="grid size-7 shrink-0 place-items-center rounded-[7px] border border-border bg-surface text-secondary"><FileText aria-hidden="true" size={16} /></span>
              <span className="min-w-0 overflow-hidden text-ellipsis whitespace-nowrap text-xs font-semibold text-foreground max-[620px]:whitespace-normal">{citation.original_filename}</span>
              <span className="ml-auto font-mono text-[10px] text-secondary">p.{citation.page_number}</span>
            </li>
          ))}
        </ul>
      </div>
    </div>
  );
}

function NeedsReviewState({ answer, outcomeRef, question }: { answer: AssistantAnswer; outcomeRef: React.RefObject<HTMLDivElement | null>; question: string | null }) {
  return (
    <div className="rounded-[13px] border border-[#ddc7a8] bg-surface p-6.5 focus:outline-2 focus:outline-offset-3 focus:outline-primary max-[620px]:p-5" ref={outcomeRef} tabIndex={-1}>
      <ResultQuestion question={question} />
      <div className={resultHeadingClass}>
        <span className={`${resultIconClass} border-[#ddc7a8] bg-warning-soft text-warning`}><AlertTriangle aria-hidden="true" size={19} /></span>
        <div><p>Insufficient knowledge</p><h2>Needs review</h2></div>
      </div>
      <p className="mt-5.75 mb-0 whitespace-pre-wrap text-[15px] leading-[1.8] text-foreground">{answer.answer}</p>
      <p className="mt-4 mb-0 rounded-lg bg-warning-soft px-3.25 py-3 text-xs leading-[1.55] text-warning">Sift did not find enough supporting knowledge to answer without guessing.</p>
    </div>
  );
}

function LoadingState({ question }: { question: string | null }) {
  return (
    <div aria-label="Sift is searching workspace knowledge" className={`${panelClass} p-6.5 max-[620px]:p-5`} role="status">
      <ResultQuestion question={question} />
      <div className="flex items-center gap-2.25 text-[13px] text-secondary"><LoaderCircle aria-hidden="true" className="animate-spin motion-reduce:animate-none" size={19} /><span>Sift is searching workspace knowledge…</span></div>
      <div className="mt-6 h-3 w-full animate-pulse rounded-md bg-elevated motion-reduce:animate-none" />
      <div className="mt-2.5 h-3 w-[68%] animate-pulse rounded-md bg-elevated motion-reduce:animate-none" />
    </div>
  );
}

function ErrorState({ error, outcomeRef }: { error: string; outcomeRef: React.RefObject<HTMLDivElement | null> }) {
  return (
    <div className={`${panelClass} flex min-h-32.5 items-center justify-start gap-3.5 border-[#dcb9bc] p-6.5 focus:outline-2 focus:outline-offset-3 focus:outline-primary max-[620px]:items-start max-[620px]:p-5 [&_h2]:m-0 [&_h2]:text-[15px] [&_p]:mt-1.25 [&_p]:mb-0 [&_p]:text-xs [&_p]:leading-[1.55] [&_p]:text-secondary`} ref={outcomeRef} role="alert" tabIndex={-1}>
      <span className={`${resultIconClass} border-[#dcb9bc] bg-danger-soft text-danger`}><AlertTriangle aria-hidden="true" size={19} /></span>
      <div><h2>Answer unavailable</h2><p>{error}</p></div>
    </div>
  );
}

function EmptyState() {
  return (
    <div className={`${panelClass} flex min-h-37.5 items-center justify-center gap-3.5 p-6.5 max-[620px]:items-start max-[620px]:justify-start [&_h2]:m-0 [&_h2]:text-[15px] [&_p]:mt-1.25 [&_p]:mb-0 [&_p]:text-xs [&_p]:leading-[1.55] [&_p]:text-secondary`}>
      <span className="grid size-10 shrink-0 place-items-center rounded-[10px] border border-border bg-elevated text-secondary"><BookOpenText aria-hidden="true" size={21} /></span>
      <div><h2>Ready when you are</h2><p>Your grounded answer and supporting sources will appear here.</p></div>
    </div>
  );
}

function ResultQuestion({ question }: { question: string | null }) {
  if (!question) return null;

  return <p className="mt-0 mb-5.5 border-b border-border pb-4.5 text-[13px] leading-[1.55] text-secondary [&_span]:mb-1.25 [&_span]:block [&_span]:text-[10px] [&_span]:font-bold [&_span]:tracking-[.1em] [&_span]:text-muted [&_span]:uppercase"><span>Your question</span>{question}</p>;
}
