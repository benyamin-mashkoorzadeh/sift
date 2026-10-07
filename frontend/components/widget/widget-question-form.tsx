"use client";

import { ArrowUp } from "lucide-react";
import { FormEvent, KeyboardEvent, useEffect, useRef, useState } from "react";

interface WidgetQuestionFormProps {
  submitting: boolean;
  onSubmit: (question: string) => Promise<void>;
}

const MAX_QUESTION_LENGTH = 1_000;
const CHARACTER_COUNT_THRESHOLD = 800;

export function WidgetQuestionForm({ submitting, onSubmit }: WidgetQuestionFormProps) {
  const [question, setQuestion] = useState("");
  const [error, setError] = useState<string | null>(null);
  const textareaRef = useRef<HTMLTextAreaElement | null>(null);

  useEffect(() => {
    textareaRef.current?.focus();
  }, []);

  async function submit(event?: FormEvent<HTMLFormElement>) {
    event?.preventDefault();
    const normalized = question.trim();

    if (submitting) return;
    if (!normalized) {
      setError("Enter a question to continue.");
      return;
    }
    if (normalized.length > MAX_QUESTION_LENGTH) {
      setError(`Keep your question under ${MAX_QUESTION_LENGTH.toLocaleString()} characters.`);
      return;
    }

    setError(null);
    setQuestion("");
    await onSubmit(normalized);
    textareaRef.current?.focus();
  }

  function handleKeyDown(event: KeyboardEvent<HTMLTextAreaElement>) {
    if (event.key === "Enter" && !event.shiftKey) {
      event.preventDefault();
      void submit();
    }
  }

  return (
    <form className="border-t border-border bg-surface px-3.5 pt-3.25 pb-3 max-[480px]:px-3 max-[480px]:pt-2.75 max-[480px]:pb-[max(11px,env(safe-area-inset-bottom))]" onSubmit={submit}>
      <label className="sr-only" htmlFor="sift-widget-question">Type a question</label>
      <div className="flex items-end gap-2 rounded-[11px] border border-border bg-background py-1.75 pr-1.75 pl-2.75 transition-[border-color,box-shadow] duration-150 focus-within:border-primary focus-within:shadow-[0_0_0_3px_var(--focus-ring)] motion-reduce:transition-none">
        <textarea
          className="min-h-8.5 max-h-23 w-full resize-none border-0 bg-transparent pt-1.75 pb-1 text-[13px] leading-[1.45] text-foreground outline-none placeholder:text-muted disabled:cursor-wait disabled:opacity-65"
          aria-describedby={error ? "sift-widget-question-error" : undefined}
          aria-invalid={error ? "true" : undefined}
          disabled={submitting}
          id="sift-widget-question"
          maxLength={MAX_QUESTION_LENGTH}
          onChange={(event) => {
            setQuestion(event.target.value);
            if (error) setError(null);
          }}
          onKeyDown={handleKeyDown}
          placeholder="Type a question…"
          ref={textareaRef}
          rows={1}
          value={question}
        />
        <button
          aria-label="Send question"
          className="grid size-8.5 shrink-0 cursor-pointer place-items-center rounded-[9px] border border-primary bg-primary text-surface transition-[background,border-color,opacity] duration-150 hover:not-disabled:border-primary-hover hover:not-disabled:bg-primary-hover disabled:cursor-not-allowed disabled:opacity-42 motion-reduce:transition-none"
          disabled={submitting || question.trim() === ""}
          type="submit"
        >
          <ArrowUp aria-hidden="true" size={19} strokeWidth={2.2} />
        </button>
      </div>
      <div className="mt-1.5 flex min-h-3.75 items-start justify-between gap-2.5 text-[9px] leading-[1.4] text-muted [&_p]:m-0 [&_p]:text-danger">
        {error
          ? <p id="sift-widget-question-error" role="alert">{error}</p>
          : <span>Enter to send · Shift+Enter for a new line</span>}
        {question.length >= CHARACTER_COUNT_THRESHOLD && (
          <span>{question.length.toLocaleString()} / {MAX_QUESTION_LENGTH.toLocaleString()}</span>
        )}
      </div>
    </form>
  );
}
