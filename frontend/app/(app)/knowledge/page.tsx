import { DocumentList } from "@/components/knowledge/document-list";
import { DocumentStatusRefresh } from "@/components/knowledge/document-status-refresh";
import { UploadDocument } from "@/components/knowledge/upload-document";
import { requirePermission } from "@/lib/auth.server";
import { getDocuments } from "@/lib/documents";
import { hasPermission } from "@/lib/permissions";

export const dynamic = "force-dynamic";

interface KnowledgePageProps {
  searchParams: Promise<{ page?: string }>;
}

export default async function KnowledgePage({ searchParams }: KnowledgePageProps) {
  const parameters = await searchParams;
  const requestedPage = Number(parameters.page ?? 1);
  const page = Number.isSafeInteger(requestedPage) && requestedPage > 0 ? requestedPage : 1;
  const context = await requirePermission("knowledge.view");
  const workspaceId = context.workspace.id;
  const canManageKnowledge = hasPermission(context, "knowledge.manage");
  const documents = await getDocuments(workspaceId, page);
  const hasProcessingDocuments = documents.data.some(
    (document) => document.status === "processing",
  );

  return (
    <section className="mx-auto w-[min(100%,1180px)]">
      <DocumentStatusRefresh hasProcessingDocuments={hasProcessingDocuments} />
      <header className="flex items-end justify-between gap-7 max-[760px]:flex-col max-[760px]:items-start [&_h1]:m-0 [&_h1]:text-[clamp(31px,4vw,43px)] [&_h1]:leading-[1.08] [&_h1]:tracking-[-.045em]">
        <div>
          <p className="mt-0 mb-2.25 text-[11px] font-bold tracking-[.13em] text-primary uppercase">Knowledge base</p>
          <h1>Knowledge</h1>
          <p className="mt-3.25 mb-0 max-w-157.5 text-[15px] leading-[1.65] text-secondary">
            {canManageKnowledge
              ? "Upload the source documents Sift will use to ground support answers."
              : "Browse the source documents Sift uses to ground support answers."}
          </p>
        </div>
        {canManageKnowledge && <UploadDocument workspaceId={workspaceId} />}
      </header>

      <div className="mt-11.5 mb-3.5 flex items-center justify-between [&_h2]:m-0 [&_h2]:text-base [&_h2]:tracking-[-.01em] [&_p]:mt-1 [&_p]:mb-0 [&_p]:font-mono [&_p]:text-[11px] [&_p]:text-secondary">
        <div>
          <h2>Documents</h2>
          <p>{documents.meta.total} {documents.meta.total === 1 ? "document" : "documents"}</p>
        </div>
      </div>

      <DocumentList canManage={canManageKnowledge} documents={documents} workspaceId={workspaceId} />
    </section>
  );
}
