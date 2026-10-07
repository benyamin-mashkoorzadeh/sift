import Link from "next/link";
import { ConversationHistory } from "@/components/conversations/conversation-history";
import { getAssistantInteractions } from "@/lib/assistant-interactions";
import { requirePermission } from "@/lib/auth.server";
import type { AssistantInteractionFilter } from "@/types/assistant-interaction";

export const dynamic = "force-dynamic";

interface ConversationsPageProps {
  searchParams: Promise<{ page?: string; status?: string }>;
}

export default async function ConversationsPage({ searchParams }: ConversationsPageProps) {
  const context = await requirePermission("conversations.view");
  const parameters = await searchParams;
  const status: AssistantInteractionFilter = parameters.status === "answered" || parameters.status === "needs_review"
    ? parameters.status
    : "all";
  const requestedPage = Number(parameters.page ?? 1);
  const page = Number.isSafeInteger(requestedPage) && requestedPage > 0 ? requestedPage : 1;
  const interactions = await getAssistantInteractions(context.workspace.id, status, page);

  return (
    <section className="mx-auto w-full max-w-245">
      <header className="max-w-170 [&_h1]:m-0 [&_h1]:text-[clamp(31px,4vw,43px)] [&_h1]:leading-[1.08] [&_h1]:tracking-[-.045em]">
        <p className="mt-0 mb-2.25 text-[11px] font-bold tracking-[.13em] text-primary uppercase">Interaction history</p>
        <h1>Conversations</h1>
        <p className="mt-3.25 mb-0 text-[15px] leading-[1.65] text-secondary">
          Revisit internal Assistant and Customer Widget questions, answers, sources, and review outcomes.
        </p>
      </header>

      <nav aria-label="History outcome" className="mt-8.5 inline-flex gap-0.75 rounded-[10px] border border-border bg-elevated p-1 max-[620px]:flex [&_a]:min-w-21.5 [&_a]:rounded-[7px] [&_a]:px-3.25 [&_a]:py-2 [&_a]:text-center [&_a]:text-xs [&_a]:font-bold [&_a]:text-secondary [&_a]:transition-[color,background] [&_a]:duration-160 hover:[&_a]:text-primary max-[620px]:[&_a]:min-w-0 max-[620px]:[&_a]:flex-1 max-[620px]:[&_a]:px-2.25 motion-reduce:[&_a]:transition-none">
        <FilterLink active={status === "all"} href="/conversations" label="All" />
        <FilterLink active={status === "answered"} href="/conversations?status=answered" label="Answered" />
        <FilterLink active={status === "needs_review"} href="/conversations?status=needs_review" label="Needs Review" />
      </nav>

      <div className="mt-8.5 mb-3.5 [&_h2]:m-0 [&_h2]:text-base [&_h2]:tracking-[-.01em] [&_p]:mt-1 [&_p]:mb-0 [&_p]:font-mono [&_p]:text-[11px] [&_p]:text-secondary">
        <div>
          <h2>Interactions</h2>
          <p>{interactions.meta.total} {interactions.meta.total === 1 ? "interaction" : "interactions"}</p>
        </div>
      </div>

      <ConversationHistory interactions={interactions} status={status} />
    </section>
  );
}

function FilterLink({ active, href, label }: { active: boolean; href: string; label: string }) {
  return <Link aria-current={active ? "page" : undefined} className={active ? "bg-surface text-primary shadow-[0_1px_2px_rgba(54,43,46,.08)]" : ""} href={href}>{label}</Link>;
}
