import {
  AlertTriangle,
  ArrowRight,
  BookOpenText,
  Bot,
  CheckCircle2,
  Clock3,
  FileText,
  MessageSquareText,
  ShieldCheck,
  type LucideIcon,
} from "lucide-react";
import Link from "next/link";
import type { ReactNode } from "react";
import type { AssistantInteractionStatus } from "@/types/assistant-interaction";
import type { DocumentStatus } from "@/types/document";
import type { WorkspaceOverview as WorkspaceOverviewData } from "@/types/overview";

const summaryCardClass = "min-w-0 rounded-[13px] border border-border bg-surface p-5";
const cardHeadingClass = "flex items-center gap-2.5 [&>p]:m-0 [&>p]:text-xs [&>p]:font-bold [&>p]:tracking-[.04em] [&>p]:text-secondary [&>p]:uppercase";
const cardIconClass = "grid size-8.5 place-items-center rounded-[9px] border border-border bg-primary-soft text-primary";
const primaryMetricClass = "mt-5.5 flex items-baseline gap-2.25 [&>strong]:text-[32px] [&>strong]:font-bold [&>strong]:leading-none [&>strong]:tracking-[-.04em] [&>strong]:text-foreground [&>span]:text-xs [&>span]:text-secondary";
const cardLinkClass = "inline-flex items-center gap-1.25 text-[11px] font-bold text-primary transition-colors duration-160 hover:text-primary-hover motion-reduce:transition-none";
const activitySectionClass = "min-w-0 overflow-hidden rounded-[13px] border border-border bg-surface";
const activityListClass = "m-0 list-none p-0 [&_li]:flex [&_li]:min-w-0 [&_li]:items-center [&_li]:gap-2.75 [&_li]:border-b [&_li]:border-border [&_li]:px-5 [&_li]:py-3.5 [&_li:last-child]:border-b-0 max-[540px]:[&_li]:flex-wrap max-[540px]:[&_li]:items-start max-[540px]:[&_li]:px-4";

interface WorkspaceOverviewProps {
  canUseAssistant: boolean;
  canViewConversations: boolean;
  canViewKnowledge: boolean;
  canViewReview: boolean;
  overview: WorkspaceOverviewData;
}

export function WorkspaceOverview({ canUseAssistant, canViewConversations, canViewKnowledge, canViewReview, overview }: WorkspaceOverviewProps) {
  return (
    <section className="mx-auto w-full max-w-280">
      <header className="max-w-175 [&_h1]:m-0 [&_h1]:text-[clamp(31px,4vw,43px)] [&_h1]:leading-[1.08] [&_h1]:tracking-[-.045em]">
        <p className="mt-0 mb-2.25 overflow-hidden text-ellipsis whitespace-nowrap text-[11px] font-bold tracking-[.13em] text-primary uppercase">{overview.workspace.name}</p>
        <h1>Overview</h1>
        <p className="mt-3.25 mb-0 text-[15px] leading-[1.65] text-secondary">
          A current snapshot of workspace activity and questions awaiting review.
        </p>
      </header>

      <div aria-label="Workspace summary" className="mt-8.5 grid grid-cols-3 gap-3 max-[900px]:grid-cols-2 max-[900px]:[&>*:last-child]:col-span-full max-[540px]:grid-cols-1 max-[540px]:[&>*:last-child]:col-auto">
        {canViewKnowledge && <SummaryCard icon={BookOpenText} label="Knowledge" total={overview.documents.total} totalLabel={overview.documents.total === 1 ? "document" : "documents"}>
          <Metric label="Ready" tone="success" value={overview.documents.ready} />
          <Metric label="Processing" tone="warning" value={overview.documents.processing} />
          <Metric label="Failed" tone="danger" value={overview.documents.failed} />
        </SummaryCard>}

        {canUseAssistant && <SummaryCard icon={Bot} label="Assistant" total={overview.interactions.total} totalLabel={overview.interactions.total === 1 ? "interaction" : "interactions"}>
          <Metric label="Answered" tone="success" value={overview.interactions.answered} />
          <Metric label="Needs Review" tone="warning" value={overview.interactions.needs_review} />
        </SummaryCard>}

        {canViewReview && <article className={`${summaryCardClass} ${overview.reviews.pending > 0 ? "border-[#ddc7a8]" : ""}`}>
          <div className={cardHeadingClass}>
            <span className={`${cardIconClass} border-[#ddc7a8] bg-warning-soft text-warning`}><ShieldCheck aria-hidden="true" size={19} /></span>
            <p>Review queue</p>
          </div>
          <div className={primaryMetricClass}><strong>{overview.reviews.pending}</strong><span>{overview.reviews.pending === 1 ? "pending item" : "pending items"}</span></div>
          <p className="mt-4.5 mb-0 min-h-8 text-xs leading-[1.5] text-secondary">{overview.reviews.pending > 0 ? "Human attention is needed." : "The review queue is clear."}</p>
          <Link className={`${cardLinkClass} mt-2.25`} href="/review">Open Review <ArrowRight aria-hidden="true" size={14} /></Link>
        </article>}
      </div>

      {(canViewKnowledge || canViewConversations) && (
        <div className="mt-8.5 grid grid-cols-2 gap-3.5 max-[760px]:grid-cols-1">
          {canViewKnowledge && <RecentDocuments documents={overview.recent_documents} />}
          {canViewConversations && <RecentInteractions interactions={overview.recent_interactions} />}
        </div>
      )}
    </section>
  );
}

function SummaryCard({ children, icon: Icon, label, total, totalLabel }: { children: ReactNode; icon: LucideIcon; label: string; total: number; totalLabel: string }) {
  return (
    <article className={summaryCardClass}>
      <div className={cardHeadingClass}><span className={cardIconClass}><Icon aria-hidden="true" size={19} /></span><p>{label}</p></div>
      <div className={primaryMetricClass}><strong>{total}</strong><span>{totalLabel}</span></div>
      <div className="mt-5.25 flex flex-wrap gap-1.75">{children}</div>
    </article>
  );
}

function Metric({ label, tone, value }: { label: string; tone: "success" | "warning" | "danger"; value: number }) {
  const toneClass = tone === "success" ? "text-success" : tone === "warning" ? "text-warning" : "text-danger";
  return <span className={`inline-flex items-center gap-1.5 rounded-[7px] border border-border bg-elevated px-1.75 py-1.25 text-[10px] ${toneClass}`}><i className="size-1.25 rounded-full bg-current" />{label}<strong className="font-mono text-[10px] text-foreground">{value}</strong></span>;
}

function RecentDocuments({ documents }: { documents: WorkspaceOverviewData["recent_documents"] }) {
  return (
    <section className={activitySectionClass}>
      <div className="flex items-center justify-between gap-4 border-b border-border px-5 py-4.5 max-[540px]:px-4 [&_h2]:m-0 [&_h2]:text-[15px] [&_h2]:tracking-[-.01em] [&_h2]:text-foreground [&_p]:mt-1 [&_p]:mb-0 [&_p]:text-[11px] [&_p]:text-muted [&>a]:inline-flex [&>a]:items-center [&>a]:gap-1.25 [&>a]:text-[11px] [&>a]:font-bold [&>a]:text-primary [&>a]:transition-colors [&>a]:duration-160 hover:[&>a]:text-primary-hover motion-reduce:[&>a]:transition-none">
        <div><h2>Recent documents</h2><p>Latest knowledge uploads</p></div>
        <Link href="/knowledge">View all <ArrowRight aria-hidden="true" size={14} /></Link>
      </div>
      {documents.length === 0
        ? <EmptyActivity icon={FileText} link="/knowledge" linkLabel="Open Knowledge" message="Upload a PDF to begin building this workspace's knowledge base." title="No documents yet" />
        : <ul className={activityListClass}>{documents.map((document) => (
          <li key={document.id}>
            <span className="grid size-8.5 shrink-0 place-items-center rounded-lg border border-border bg-elevated text-secondary"><FileText aria-hidden="true" size={17} /></span>
            <span className="min-w-0 flex-1 max-[540px]:w-[calc(100%_-_46px)] max-[540px]:flex-none [&>strong]:block [&>strong]:overflow-hidden [&>strong]:text-ellipsis [&>strong]:whitespace-nowrap [&>strong]:text-xs [&>strong]:font-semibold [&>strong]:text-foreground [&>span]:mt-1.25 [&>span]:block [&>span]:overflow-hidden [&>span]:text-ellipsis [&>span]:whitespace-nowrap [&>span]:font-mono [&>span]:text-[9px] [&>span]:text-muted">
              <strong title={document.original_filename}>{document.original_filename}</strong>
              <span>{formatBytes(document.size_bytes)} · {document.page_count === null ? "Pages pending" : `${document.page_count} ${document.page_count === 1 ? "page" : "pages"}`} · {formatDate(document.created_at)}</span>
            </span>
            <DocumentBadge status={document.status} />
          </li>
        ))}</ul>}
    </section>
  );
}

function RecentInteractions({ interactions }: { interactions: WorkspaceOverviewData["recent_interactions"] }) {
  return (
    <section className={activitySectionClass}>
      <div className="flex items-center justify-between gap-4 border-b border-border px-5 py-4.5 max-[540px]:px-4 [&_h2]:m-0 [&_h2]:text-[15px] [&_h2]:tracking-[-.01em] [&_h2]:text-foreground [&_p]:mt-1 [&_p]:mb-0 [&_p]:text-[11px] [&_p]:text-muted [&>a]:inline-flex [&>a]:items-center [&>a]:gap-1.25 [&>a]:text-[11px] [&>a]:font-bold [&>a]:text-primary [&>a]:transition-colors [&>a]:duration-160 hover:[&>a]:text-primary-hover motion-reduce:[&>a]:transition-none">
        <div><h2>Recent Assistant activity</h2><p>Latest workspace questions</p></div>
        <Link href="/conversations">View all <ArrowRight aria-hidden="true" size={14} /></Link>
      </div>
      {interactions.length === 0
        ? <EmptyActivity icon={MessageSquareText} link="/assistant" linkLabel="Open Assistant" message="Ask a question to create the first Assistant interaction." title="No activity yet" />
        : <ul className={activityListClass}>{interactions.map((interaction) => (
          <li key={interaction.id}>
            <span className="grid size-8.5 shrink-0 place-items-center rounded-lg border border-border bg-elevated text-secondary"><MessageSquareText aria-hidden="true" size={17} /></span>
            <span className="min-w-0 flex-1 max-[540px]:w-[calc(100%_-_46px)] max-[540px]:flex-none [&>strong]:block [&>strong]:overflow-hidden [&>strong]:text-ellipsis [&>strong]:whitespace-nowrap [&>strong]:text-xs [&>strong]:font-semibold [&>strong]:text-foreground [&>span]:mt-1.25 [&>span]:block [&>span]:overflow-hidden [&>span]:text-ellipsis [&>span]:whitespace-nowrap [&>span]:font-mono [&>span]:text-[9px] [&>span]:text-muted">
              <strong title={interaction.question}>{interaction.question}</strong>
              <span>{formatDate(interaction.created_at)}</span>
            </span>
            <InteractionBadge status={interaction.status} />
          </li>
        ))}</ul>}
    </section>
  );
}

function EmptyActivity({ icon: Icon, link, linkLabel, message, title }: { icon: LucideIcon; link: string; linkLabel: string; message: string; title: string }) {
  return (
    <div className="flex min-h-67 flex-col items-center justify-center px-6 py-7.5 text-center [&>span]:grid [&>span]:size-11 [&>span]:place-items-center [&>span]:rounded-[11px] [&>span]:border [&>span]:border-border [&>span]:bg-elevated [&>span]:text-secondary [&>h3]:mt-3.75 [&>h3]:mb-0 [&>h3]:text-[15px] [&>p]:mt-1.75 [&>p]:mb-3.75 [&>p]:max-w-82.5 [&>p]:text-xs [&>p]:leading-[1.6] [&>p]:text-secondary">
      <span><Icon aria-hidden="true" size={20} /></span>
      <h3>{title}</h3>
      <p>{message}</p>
      <Link className={cardLinkClass} href={link}>{linkLabel} <ArrowRight aria-hidden="true" size={14} /></Link>
    </div>
  );
}

function DocumentBadge({ status }: { status: DocumentStatus }) {
  const icon = status === "ready"
    ? <CheckCircle2 aria-hidden="true" size={12} />
    : status === "processing"
      ? <Clock3 aria-hidden="true" size={12} />
      : <AlertTriangle aria-hidden="true" size={12} />;

  const tone = status === "ready" ? "border-[#b8d0c5] bg-success-soft text-success" : status === "processing" ? "border-[#ddc7a8] bg-warning-soft text-warning" : "border-[#dab9bd] bg-danger-soft text-danger";
  return <span className={`inline-flex shrink-0 items-center gap-1 rounded-full border px-1.5 py-1 text-[8px] font-bold tracking-[.04em] uppercase max-[540px]:ml-11.25 ${tone}`}>{icon}{status[0].toUpperCase() + status.slice(1)}</span>;
}

function InteractionBadge({ status }: { status: AssistantInteractionStatus }) {
  const tone = status === "answered" ? "border-[#b8d0c5] bg-success-soft text-success" : "border-[#ddc7a8] bg-warning-soft text-warning";
  return <span className={`inline-flex shrink-0 items-center gap-1 rounded-full border px-1.5 py-1 text-[8px] font-bold tracking-[.04em] uppercase max-[540px]:ml-11.25 ${tone}`}>{status === "answered" ? <CheckCircle2 aria-hidden="true" size={12} /> : <AlertTriangle aria-hidden="true" size={12} />}{status === "answered" ? "Answered" : "Needs Review"}</span>;
}

function formatBytes(bytes: number): string {
  if (bytes < 1024) return `${bytes} B`;
  if (bytes < 1024 ** 2) return `${(bytes / 1024).toFixed(1)} KB`;
  return `${(bytes / 1024 ** 2).toFixed(1)} MB`;
}

function formatDate(value: string): string {
  return new Intl.DateTimeFormat("en", { day: "2-digit", month: "short", year: "numeric", timeZone: "UTC" }).format(new Date(value));
}
