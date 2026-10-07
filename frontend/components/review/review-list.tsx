"use client";

import {
  AlertCircle,
  Check,
  CheckCircle2,
  ChevronLeft,
  ChevronRight,
  Clock3,
  Inbox,
  LoaderCircle,
  MessageSquareText,
  X,
} from "lucide-react";
import Link from "next/link";
import { useRouter } from "next/navigation";
import { FormEvent, useState } from "react";
import { ReviewApiError, resolveReviewItem } from "@/lib/review-items";
import type { PaginatedResponse } from "@/types/api";
import type { ReviewItem, ReviewItemStatus } from "@/types/review-item";

const secondaryButtonClass = "inline-flex min-h-9.5 cursor-pointer items-center justify-center gap-1.75 rounded-lg border border-border bg-elevated px-3.25 text-xs font-bold text-secondary transition-[background,border-color,color,opacity] duration-160 hover:not-disabled:border-primary hover:not-disabled:text-primary disabled:cursor-not-allowed disabled:opacity-45 motion-reduce:transition-none";
const primaryButtonClass = "inline-flex min-h-9.5 cursor-pointer items-center justify-center gap-1.75 rounded-lg border border-primary bg-primary px-3.25 text-xs font-bold text-surface transition-[background,border-color,color,opacity] duration-160 hover:not-disabled:border-primary-hover hover:not-disabled:bg-primary-hover disabled:cursor-not-allowed disabled:opacity-45 motion-reduce:transition-none";

interface ReviewListProps {
  canResolve: boolean;
  page: PaginatedResponse<ReviewItem>;
  status: ReviewItemStatus;
  workspaceId: number;
}

export function ReviewList({ canResolve, page, status, workspaceId }: ReviewListProps) {
  const [items, setItems] = useState(page.data);
  const [successMessage, setSuccessMessage] = useState<string | null>(null);

  function resolved(item: ReviewItem) {
    setItems((current) => current.filter((candidate) => candidate.id !== item.id));
    setSuccessMessage("The question was resolved and moved to Resolved.");
  }

  if (items.length === 0) {
    return (
      <>
        {successMessage && <SuccessNotice message={successMessage} />}
        <EmptyState status={status} />
      </>
    );
  }

  return (
    <>
      {successMessage && <SuccessNotice message={successMessage} />}
      <div className="grid gap-3">
        {items.map((item) => (
          <ReviewCard canResolve={canResolve} item={item} key={item.id} onResolved={resolved} workspaceId={workspaceId} />
        ))}
      </div>
      {page.meta.last_page > 1 && <Pagination page={page} status={status} />}
    </>
  );
}

function ReviewCard({ canResolve, item, onResolved, workspaceId }: {
  canResolve: boolean;
  item: ReviewItem;
  onResolved: (item: ReviewItem) => void;
  workspaceId: number;
}) {
  const router = useRouter();
  const [editing, setEditing] = useState(false);
  const [resolution, setResolution] = useState("");
  const [fieldError, setFieldError] = useState<string | null>(null);
  const [requestError, setRequestError] = useState<string | null>(null);
  const [resolving, setResolving] = useState(false);

  async function submit(event: FormEvent<HTMLFormElement>) {
    event.preventDefault();
    const normalizedResolution = resolution.trim();

    if (normalizedResolution === "") {
      setFieldError("Enter a human resolution before closing this item.");
      return;
    }

    if (normalizedResolution.length > 4000) {
      setFieldError("Keep the resolution under 4,000 characters.");
      return;
    }

    setFieldError(null);
    setRequestError(null);
    setResolving(true);

    try {
      const updatedItem = await resolveReviewItem(workspaceId, item.id, normalizedResolution);
      onResolved(updatedItem);
      router.refresh();
    } catch (error) {
      if (error instanceof ReviewApiError && error.resolutionError) {
        setFieldError(error.resolutionError);
      } else if (error instanceof ReviewApiError) {
        setRequestError(error.message);
      } else {
        setRequestError("Sift could not reach the API. Check your connection and try again.");
      }
    } finally {
      setResolving(false);
    }
  }

  return (
    <article className="rounded-[13px] border border-border bg-surface p-5.5 max-[620px]:p-4.5">
      <div className="flex items-center justify-between gap-4">
        <span className={`inline-flex items-center gap-1.5 rounded-full border px-2 py-1.25 text-[10px] font-bold tracking-[.03em] uppercase ${item.status === "pending" ? "border-[#ddc7a8] bg-warning-soft text-warning" : "border-[#b8d0c5] bg-success-soft text-success"}`}>
          {item.status === "pending" ? <Clock3 aria-hidden="true" size={13} /> : <CheckCircle2 aria-hidden="true" size={13} />}
          {item.status === "pending" ? "Pending" : "Resolved"}
        </span>
        <span className="font-mono text-[10px] text-muted">#{item.id}</span>
      </div>

      <div className="mt-4.5 flex items-start gap-3.25">
        <span className="grid size-9.25 shrink-0 place-items-center rounded-[9px] border border-border bg-elevated text-secondary"><MessageSquareText aria-hidden="true" size={18} /></span>
        <div className="[&_p]:m-0 [&_p]:text-[10px] [&_p]:font-bold [&_p]:tracking-[.09em] [&_p]:text-muted [&_p]:uppercase [&_h3]:mt-1.25 [&_h3]:mb-0 [&_h3]:text-[15px] [&_h3]:font-semibold [&_h3]:leading-[1.55]">
          <p>Question</p>
          <h3>{item.question}</h3>
        </div>
      </div>

      <dl className="mt-4.5 ml-12.5 flex flex-wrap gap-5.5 max-[620px]:ml-0 max-[620px]:flex-col max-[620px]:items-start max-[620px]:gap-1.75 [&>div]:flex [&>div]:items-baseline [&>div]:gap-1.75 [&_dt]:text-[10px] [&_dt]:text-muted [&_dd]:m-0 [&_dd]:font-mono [&_dd]:text-[9px] [&_dd]:text-secondary">
        <div><dt>First asked</dt><dd><time dateTime={item.created_at}>{formatDateTime(item.created_at)}</time></dd></div>
        {item.status === "pending"
          ? <div><dt>Last asked</dt><dd><time dateTime={item.last_asked_at}>{formatDateTime(item.last_asked_at)}</time></dd></div>
          : item.resolved_at && <div><dt>Resolved</dt><dd><time dateTime={item.resolved_at}>{formatDateTime(item.resolved_at)}</time></dd></div>}
      </dl>

      {item.status === "resolved" && item.resolution && (
        <div className="mt-5 ml-12.5 rounded-r-lg border-l-3 border-success bg-success-soft px-3.75 py-3.5 max-[620px]:ml-0 [&>p]:m-0 [&>p]:text-[10px] [&>p]:font-bold [&>p]:tracking-[.09em] [&>p]:text-success [&>p]:uppercase [&>div]:mt-1.75 [&>div]:whitespace-pre-wrap [&>div]:text-[13px] [&>div]:leading-[1.65] [&>div]:text-foreground">
          <p>Human resolution</p>
          <div>{item.resolution}</div>
        </div>
      )}

      {canResolve && item.status === "pending" && !editing && (
        <div className="mt-5 flex justify-end border-t border-border pt-4.25 max-[620px]:flex-col-reverse">
          <button className={`${primaryButtonClass} max-[620px]:w-full`} onClick={() => setEditing(true)} type="button">
            <Check aria-hidden="true" size={16} /> Resolve question
          </button>
        </div>
      )}

      {canResolve && item.status === "pending" && editing && (
        <form aria-busy={resolving} className="mt-5 rounded-[10px] border border-border bg-elevated p-4.5 [&>label]:block [&>label]:text-xs [&>label]:font-bold [&>label]:text-foreground [&>p]:mt-1.25 [&>p]:mb-2.75 [&>p]:text-[11px] [&>p]:leading-[1.5] [&>p]:text-secondary [&>textarea]:block [&>textarea]:min-h-28 [&>textarea]:w-full [&>textarea]:resize-y [&>textarea]:rounded-lg [&>textarea]:border [&>textarea]:border-border [&>textarea]:bg-surface [&>textarea]:px-3.25 [&>textarea]:py-3 [&>textarea]:text-[13px] [&>textarea]:leading-[1.6] [&>textarea]:text-foreground [&>textarea]:outline-none [&>textarea]:placeholder:text-muted [&>textarea]:focus:border-primary [&>textarea]:focus:shadow-[0_0_0_3px_var(--focus-ring)] [&>textarea]:disabled:cursor-wait [&>textarea]:disabled:opacity-68" onSubmit={submit}>
          <label htmlFor={`resolution-${item.id}`}>Human resolution</label>
          <p id={`resolution-help-${item.id}`}>Record the reliable answer or decision that closes this review item.</p>
          <textarea
            aria-describedby={`resolution-help-${item.id}${fieldError ? ` resolution-error-${item.id}` : ""}`}
            aria-invalid={fieldError ? "true" : undefined}
            disabled={resolving}
            id={`resolution-${item.id}`}
            maxLength={4000}
            onChange={(event) => {
              setResolution(event.target.value);
              if (fieldError) setFieldError(null);
            }}
            placeholder="Enter the human-approved resolution…"
            rows={4}
            value={resolution}
          />
          <div className="mt-1.75 flex min-h-6.25 items-start justify-between gap-4 [&_p]:m-0 [&>span]:font-mono [&>span]:text-[9px] [&>span]:text-muted">
            <div>
              {fieldError && <p className="text-[11px] text-danger" id={`resolution-error-${item.id}`} role="alert">{fieldError}</p>}
              {requestError && <p className="flex items-center gap-1.5 text-[11px] text-danger" role="alert"><AlertCircle aria-hidden="true" size={14} />{requestError}</p>}
            </div>
            <span>{resolution.length.toLocaleString()} / 4,000</span>
          </div>
          <div className="mt-3 flex justify-end gap-2 max-[620px]:flex-col-reverse">
            <button className={`${secondaryButtonClass} max-[620px]:w-full`} disabled={resolving} onClick={() => {
              setEditing(false);
              setFieldError(null);
              setRequestError(null);
            }} type="button">
              <X aria-hidden="true" size={15} /> Cancel
            </button>
            <button className={`${primaryButtonClass} max-[620px]:w-full`} disabled={resolving || resolution.trim() === ""} type="submit">
              {resolving
                ? <><LoaderCircle aria-hidden="true" className="animate-spin motion-reduce:animate-none" size={16} /> Resolving…</>
                : <><Check aria-hidden="true" size={16} /> Mark resolved</>}
            </button>
          </div>
        </form>
      )}
    </article>
  );
}

function SuccessNotice({ message }: { message: string }) {
  return <div className="mb-3 flex items-center gap-2.25 rounded-[9px] border border-[#b8d0c5] bg-success-soft px-3.25 py-2.75 text-xs text-success" role="status"><CheckCircle2 aria-hidden="true" size={17} /><span>{message}</span></div>;
}

function EmptyState({ status }: { status: ReviewItemStatus }) {
  return (
    <div className="flex min-h-80 flex-col items-center justify-center rounded-[13px] border border-dashed border-border bg-surface px-6 py-10 text-center [&_h3]:mt-4.25 [&_h3]:mb-0 [&_h3]:text-[17px] [&_p]:mt-2 [&_p]:mb-0 [&_p]:max-w-102.5 [&_p]:text-[13px] [&_p]:leading-[1.6] [&_p]:text-secondary">
      <span className="grid size-12 place-items-center rounded-xl border border-border bg-elevated text-secondary">{status === "pending" ? <Inbox aria-hidden="true" size={23} /> : <CheckCircle2 aria-hidden="true" size={23} />}</span>
      <h3>{status === "pending" ? "Review queue is clear" : "No resolved questions yet"}</h3>
      <p>{status === "pending" ? "Questions that need a reliable human response will appear here." : "Completed review items will remain available here for reference."}</p>
    </div>
  );
}

function Pagination({ page, status }: { page: PaginatedResponse<ReviewItem>; status: ReviewItemStatus }) {
  return (
    <nav aria-label="Review pagination" className="mt-4 flex items-center justify-between max-[620px]:flex-col max-[620px]:items-start max-[620px]:gap-3">
      <span className="font-mono text-[11px] text-secondary">Page {page.meta.current_page} of {page.meta.last_page}</span>
      <div className="flex gap-2">
        <PageLink disabled={!page.links.prev} href={`/review?status=${status}&page=${page.meta.current_page - 1}`} label="Previous" previous />
        <PageLink disabled={!page.links.next} href={`/review?status=${status}&page=${page.meta.current_page + 1}`} label="Next" />
      </div>
    </nav>
  );
}

function PageLink({ disabled, href, label, previous = false }: { disabled: boolean; href: string; label: string; previous?: boolean }) {
  const content = <>{previous && <ChevronLeft aria-hidden="true" size={16} />}{label}{!previous && <ChevronRight aria-hidden="true" size={16} />}</>;
  return disabled
    ? <span aria-disabled="true" className={`${secondaryButtonClass} min-h-9 cursor-not-allowed px-2.75 opacity-35`}>{content}</span>
    : <Link className={`${secondaryButtonClass} min-h-9 px-2.75`} href={href}>{content}</Link>;
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
