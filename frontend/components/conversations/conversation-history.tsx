import {
  AlertTriangle,
  BookOpenText,
  CheckCircle2,
  ChevronDown,
  ChevronLeft,
  ChevronRight,
  FileQuestion,
  FileText,
  History,
  ShieldCheck,
} from "lucide-react";
import Link from "next/link";
import type { PaginatedResponse } from "@/types/api";
import type {
  AssistantInteraction,
  AssistantInteractionFilter,
} from "@/types/assistant-interaction";

const historyCardClass = "overflow-hidden rounded-[13px] border border-border bg-surface";
const outcomeClass = "mt-5.5 flex items-start gap-2.5 rounded-[9px] border border-[#ddc7a8] bg-warning-soft px-3.5 py-3.25 text-warning [&_svg]:mt-px [&_svg]:shrink-0 [&_strong]:block [&_strong]:text-xs [&_strong]:text-foreground [&_p]:mt-1 [&_p]:mb-0 [&_p]:text-xs [&_p]:leading-[1.6] [&_p]:text-secondary";
const pageLinkClass = "inline-flex min-h-9 items-center justify-center gap-1.75 rounded-lg border border-border bg-elevated px-2.75 text-xs font-bold text-secondary transition-[color,border-color] duration-160 hover:border-primary hover:text-primary motion-reduce:transition-none";

interface ConversationHistoryProps {
  interactions: PaginatedResponse<AssistantInteraction>;
  status: AssistantInteractionFilter;
}

export function ConversationHistory({ interactions, status }: ConversationHistoryProps) {
  if (interactions.data.length === 0) {
    return <EmptyState status={status} />;
  }

  return (
    <>
      <div className="grid gap-2.5">
        {interactions.data.map((interaction) => <InteractionItem interaction={interaction} key={interaction.id} />)}
      </div>
      {interactions.meta.last_page > 1 && <Pagination interactions={interactions} status={status} />}
    </>
  );
}

function InteractionItem({ interaction }: { interaction: AssistantInteraction }) {
  const answered = interaction.status === "answered";

  return (
    <details className={`${historyCardClass} group`}>
      <summary className="flex min-h-25.5 cursor-pointer list-none items-center gap-3.5 px-5.25 py-4.75 transition-colors duration-160 hover:bg-elevated focus-visible:outline-2 focus-visible:-outline-offset-3 focus-visible:outline-primary group-open:bg-elevated max-[620px]:items-start max-[620px]:p-4.25 motion-reduce:transition-none [&::-webkit-details-marker]:hidden">
        <span className={`grid size-9.75 shrink-0 place-items-center rounded-[9px] border ${answered ? "border-[#b8d0c5] bg-success-soft text-success" : "border-[#ddc7a8] bg-warning-soft text-warning"}`}>
          {answered ? <CheckCircle2 aria-hidden="true" size={18} /> : <AlertTriangle aria-hidden="true" size={18} />}
        </span>
        <span className="min-w-0 flex-1">
          <span className="flex items-center gap-2.5 max-[620px]:flex-col max-[620px]:items-start max-[620px]:gap-1.5 [&>time]:font-mono [&>time]:text-[9px] [&>time]:text-muted">
            <span className={`rounded-full border px-1.75 py-1 text-[9px] font-bold tracking-[.05em] uppercase ${answered ? "border-[#b8d0c5] bg-success-soft text-success" : "border-[#ddc7a8] bg-warning-soft text-warning"}`}>
              {answered ? "Answered" : "Needs Review"}
            </span>
            <span className={`rounded-full border px-1.75 py-1 text-[9px] font-bold ${interaction.origin === "widget" ? "border-[color-mix(in_srgb,var(--primary)_24%,var(--border))] bg-primary-soft text-primary" : "border-border bg-elevated text-secondary"}`}>
              {interaction.origin === "widget" ? "Customer Widget" : "Assistant"}
            </span>
            <time dateTime={interaction.created_at}>{formatDateTime(interaction.created_at)}</time>
          </span>
          <strong className="mt-2 block overflow-hidden text-ellipsis whitespace-nowrap text-sm font-semibold leading-[1.45] text-foreground max-[620px]:whitespace-normal">{interaction.question}</strong>
        </span>
        <ChevronDown aria-hidden="true" className="shrink-0 text-muted transition-transform duration-170 group-open:rotate-180 motion-reduce:transition-none" size={18} />
      </summary>

      <div className="border-t border-border pt-6 pr-6 pb-6.25 pl-18.5 max-[620px]:px-4.25 max-[620px]:py-5">
        <section>
          <p className="m-0 text-[10px] font-bold tracking-[.09em] text-muted uppercase">{answered ? "Grounded answer" : "Assistant response"}</p>
          <p className="mt-2.25 mb-0 whitespace-pre-wrap text-sm leading-[1.75] text-foreground">{interaction.answer}</p>
        </section>

        {answered && <CitationSnapshots citations={interaction.citations} />}
        {!answered && <ReviewOutcome interaction={interaction} />}
      </div>
    </details>
  );
}

function CitationSnapshots({ citations }: { citations: AssistantInteraction["citations"] }) {
  return (
    <section className="mt-6 border-t border-border pt-4.75">
      <div className="flex items-center gap-2 text-secondary [&_h3]:m-0 [&_h3]:text-[11px] [&_h3]:font-bold [&_h3]:tracking-[.07em] [&_h3]:uppercase"><BookOpenText aria-hidden="true" size={16} /><h3>Sources at answer time</h3></div>
      <ul className="mt-3 grid list-none gap-2 p-0">
        {citations.map((citation, index) => (
          <li className={`flex min-w-0 items-center gap-2.25 rounded-[9px] border border-border bg-elevated px-2.75 py-2.5 max-[620px]:flex-wrap max-[620px]:items-start ${!citation.source_available ? "text-muted" : ""}`} key={`${citation.original_filename}-${citation.page_number}-${citation.chunk_index}-${index}`}>
            <span className="grid size-7 shrink-0 place-items-center rounded-[7px] border border-border bg-surface text-secondary">{citation.source_available ? <FileText aria-hidden="true" size={16} /> : <FileQuestion aria-hidden="true" size={16} />}</span>
            <span className={`min-w-0 overflow-hidden text-ellipsis whitespace-nowrap text-xs font-semibold max-[620px]:flex-1 max-[620px]:whitespace-normal ${citation.source_available ? "text-foreground" : "text-secondary"}`}>{citation.original_filename}</span>
            <span className="ml-auto font-mono text-[10px] text-secondary max-[620px]:ml-0">p.{citation.page_number}</span>
            {!citation.source_available && <span className="whitespace-nowrap border-l border-border pl-2.25 text-[10px] font-bold text-warning max-[620px]:w-full max-[620px]:border-0 max-[620px]:pt-1.5 max-[620px]:pl-9.25">Source unavailable</span>}
          </li>
        ))}
      </ul>
    </section>
  );
}

function ReviewOutcome({ interaction }: { interaction: AssistantInteraction }) {
  const review = interaction.review;

  if (!review) {
    return <div className={outcomeClass}><AlertTriangle aria-hidden="true" size={17} /><p>The linked review item is no longer available.</p></div>;
  }

  if (review.status === "pending") {
    return (
      <div className={outcomeClass}>
        <AlertTriangle aria-hidden="true" size={17} />
        <div><strong>Pending human review</strong><p>This question is waiting for a reliable human resolution.</p></div>
      </div>
    );
  }

  return (
    <div className={`${outcomeClass} border-[#b8d0c5] bg-success-soft text-success [&_time]:mt-2 [&_time]:block [&_time]:font-mono [&_time]:text-[9px] [&_time]:text-success`}>
      <ShieldCheck aria-hidden="true" size={18} />
      <div>
        <strong>Human resolution</strong>
        <p>{review.resolution}</p>
        {review.resolved_at && <time dateTime={review.resolved_at}>Resolved {formatDateTime(review.resolved_at)}</time>}
      </div>
    </div>
  );
}

function EmptyState({ status }: { status: AssistantInteractionFilter }) {
  const copy = status === "answered"
    ? ["No answered interactions yet", "Grounded Assistant answers will appear here."]
    : status === "needs_review"
      ? ["No questions need review", "Insufficient-knowledge interactions will appear here."]
      : ["No conversation history yet", "Assistant and Customer Widget interactions will appear here."];

  return (
    <div className="flex min-h-80 flex-col items-center justify-center rounded-[13px] border border-dashed border-border bg-surface px-6 py-10 text-center [&_h3]:mt-4.25 [&_h3]:mb-0 [&_h3]:text-[17px] [&_p]:mt-2 [&_p]:mb-0 [&_p]:max-w-102.5 [&_p]:text-[13px] [&_p]:leading-[1.6] [&_p]:text-secondary">
      <span className="grid size-12 place-items-center rounded-xl border border-border bg-elevated text-secondary"><History aria-hidden="true" size={23} /></span>
      <h3>{copy[0]}</h3>
      <p>{copy[1]}</p>
    </div>
  );
}

function Pagination({ interactions, status }: ConversationHistoryProps) {
  const statusParameter = status === "all" ? "" : `status=${status}&`;

  return (
    <nav aria-label="Conversation history pagination" className="mt-4 flex items-center justify-between max-[620px]:flex-col max-[620px]:items-start max-[620px]:gap-3">
      <span className="font-mono text-[11px] text-secondary">Page {interactions.meta.current_page} of {interactions.meta.last_page}</span>
      <div className="flex gap-2">
        <PageLink disabled={!interactions.links.prev} href={`/conversations?${statusParameter}page=${interactions.meta.current_page - 1}`} label="Previous" previous />
        <PageLink disabled={!interactions.links.next} href={`/conversations?${statusParameter}page=${interactions.meta.current_page + 1}`} label="Next" />
      </div>
    </nav>
  );
}

function PageLink({ disabled, href, label, previous = false }: { disabled: boolean; href: string; label: string; previous?: boolean }) {
  const content = <>{previous && <ChevronLeft aria-hidden="true" size={16} />}{label}{!previous && <ChevronRight aria-hidden="true" size={16} />}</>;
  return disabled
    ? <span aria-disabled="true" className={`${pageLinkClass} cursor-not-allowed opacity-35`}>{content}</span>
    : <Link className={pageLinkClass} href={href}>{content}</Link>;
}

function formatDateTime(value: string): string {
  return `${new Intl.DateTimeFormat("en", {
    day: "2-digit",
    month: "short",
    year: "numeric",
    hour: "2-digit",
    minute: "2-digit",
    hour12: false,
    timeZone: "UTC",
  }).format(new Date(value))} UTC`;
}
