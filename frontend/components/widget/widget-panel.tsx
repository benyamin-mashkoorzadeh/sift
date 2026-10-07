import { ScanSearch, X } from "lucide-react";
import { WidgetMessageList } from "@/components/widget/widget-message-list";
import { WidgetQuestionForm } from "@/components/widget/widget-question-form";
import type { WidgetTranscriptMessage } from "@/types/widget";

interface WidgetPanelProps {
  workspaceName: string;
  messages: WidgetTranscriptMessage[];
  error: string | null;
  submitting: boolean;
  unavailable: boolean;
  onClose: () => void;
  onSubmit: (question: string) => Promise<void>;
}

export function WidgetPanel({
  workspaceName,
  messages,
  error,
  submitting,
  unavailable,
  onClose,
  onSubmit,
}: WidgetPanelProps) {
  return (
    <section aria-label={`${workspaceName} customer support`} className="grid h-full min-h-0 w-full grid-rows-[auto_minmax(0,1fr)_auto] overflow-hidden rounded-2xl border border-border bg-surface shadow-[0_18px_50px_rgba(33,29,30,.18)] max-[480px]:rounded-[13px]">
      <header className="flex min-w-0 items-center justify-between gap-4 border-b border-border bg-surface px-4 py-3.75 max-[480px]:px-3.5 max-[480px]:py-3.25">
        <div className="flex min-w-0 items-center gap-2.5 [&_h1]:m-0 [&_h1]:overflow-hidden [&_h1]:text-ellipsis [&_h1]:whitespace-nowrap [&_h1]:text-sm [&_h1]:font-bold [&_h1]:tracking-[-.018em] [&_p]:mt-0.5 [&_p]:mb-0 [&_p]:text-[10px] [&_p]:text-muted">
          <span className="grid size-8 shrink-0 place-items-center rounded-lg bg-primary text-surface"><ScanSearch aria-hidden="true" size={17} /></span>
          <div>
            <h1>{workspaceName}</h1>
            <p>Powered by Sift</p>
          </div>
        </div>
        <button aria-label="Close customer support" className="grid size-8.5 shrink-0 cursor-pointer place-items-center rounded-lg border border-transparent bg-transparent text-secondary transition-colors duration-150 hover:border-border hover:bg-elevated hover:text-foreground motion-reduce:transition-none" onClick={onClose} type="button">
          <X aria-hidden="true" size={19} />
        </button>
      </header>

      {unavailable ? (
        <div className="row-start-2 row-end-4 flex min-h-0 flex-col items-center justify-center bg-background p-7.5 text-center text-secondary [&_h2]:mt-3.25 [&_h2]:mb-0 [&_h2]:text-[15px] [&_h2]:text-foreground [&_p]:mt-1.5 [&_p]:mb-0 [&_p]:text-xs" role="status">
          <ScanSearch aria-hidden="true" size={24} />
          <h2>Support is unavailable</h2>
          <p>Please try again later.</p>
        </div>
      ) : (
        <>
          <WidgetMessageList error={error} messages={messages} submitting={submitting} />
          <WidgetQuestionForm onSubmit={onSubmit} submitting={submitting} />
        </>
      )}
    </section>
  );
}
