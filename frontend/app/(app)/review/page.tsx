import Link from "next/link";
import { ReviewList } from "@/components/review/review-list";
import { requirePermission } from "@/lib/auth.server";
import { getReviewItems } from "@/lib/review-items.server";
import { hasPermission } from "@/lib/permissions";
import type { ReviewItemStatus } from "@/types/review-item";

export const dynamic = "force-dynamic";

interface ReviewPageProps {
  searchParams: Promise<{ page?: string; status?: string }>;
}

export default async function ReviewPage({ searchParams }: ReviewPageProps) {
  const context = await requirePermission("review.view");
  const parameters = await searchParams;
  const status: ReviewItemStatus = parameters.status === "resolved" ? "resolved" : "pending";
  const requestedPage = Number(parameters.page ?? 1);
  const page = Number.isSafeInteger(requestedPage) && requestedPage > 0 ? requestedPage : 1;
  const workspaceId = context.workspace.id;
  const canResolve = hasPermission(context, "review.resolve");
  const reviewItems = await getReviewItems(workspaceId, status, page);

  return (
    <section className="mx-auto w-full max-w-245">
      <header className="max-w-170 [&_h1]:m-0 [&_h1]:text-[clamp(31px,4vw,43px)] [&_h1]:leading-[1.08] [&_h1]:tracking-[-.045em]">
        <p className="mt-0 mb-2.25 text-[11px] font-bold tracking-[.13em] text-primary uppercase">Human review</p>
        <h1>Review</h1>
        <p className="mt-3.25 mb-0 text-[15px] leading-[1.65] text-secondary">
          Resolve questions Sift could not answer reliably from the current knowledge base.
        </p>
      </header>

      <nav aria-label="Review status" className="mt-8.5 inline-flex gap-0.75 rounded-[10px] border border-border bg-elevated p-1 max-[620px]:flex [&_a]:min-w-23.5 [&_a]:rounded-[7px] [&_a]:px-3.25 [&_a]:py-2 [&_a]:text-center [&_a]:text-xs [&_a]:font-bold [&_a]:text-secondary [&_a]:transition-[color,background] [&_a]:duration-160 hover:[&_a]:text-primary max-[620px]:[&_a]:flex-1 motion-reduce:[&_a]:transition-none">
        <Link aria-current={status === "pending" ? "page" : undefined} className={status === "pending" ? "bg-surface text-primary shadow-[0_1px_2px_rgba(54,43,46,.08)]" : ""} href="/review?status=pending">
          Pending
        </Link>
        <Link aria-current={status === "resolved" ? "page" : undefined} className={status === "resolved" ? "bg-surface text-primary shadow-[0_1px_2px_rgba(54,43,46,.08)]" : ""} href="/review?status=resolved">
          Resolved
        </Link>
      </nav>

      <div className="mt-8.5 mb-3.5 flex items-center justify-between [&_h2]:m-0 [&_h2]:text-base [&_h2]:tracking-[-.01em] [&_p]:mt-1 [&_p]:mb-0 [&_p]:font-mono [&_p]:text-[11px] [&_p]:text-secondary">
        <div>
          <h2>{status === "pending" ? "Questions awaiting review" : "Resolved questions"}</h2>
          <p>{reviewItems.meta.total} {reviewItems.meta.total === 1 ? "item" : "items"}</p>
        </div>
      </div>

      <ReviewList canResolve={canResolve} key={`${status}-${page}`} page={reviewItems} status={status} workspaceId={workspaceId} />
    </section>
  );
}
