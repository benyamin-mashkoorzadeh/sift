import { FileText, FolderOpen, ChevronLeft, ChevronRight } from "lucide-react";
import Link from "next/link";
import type { Document, PaginatedResponse } from "@/types/document";
import { DocumentStatus } from "./document-status";
import { UploadDocument } from "./upload-document";

interface DocumentListProps {
  canManage: boolean;
  documents: PaginatedResponse<Document>;
  workspaceId: number;
}

export function DocumentList({ canManage, documents, workspaceId }: DocumentListProps) {
  if (documents.data.length === 0) {
    return (
      <div className="flex min-h-90 flex-col items-center justify-center rounded-[13px] border border-dashed border-border bg-surface px-6 py-10.5 text-center [&_h3]:mt-4.25 [&_h3]:mb-0 [&_h3]:text-[17px] [&_p]:mt-2 [&_p]:mb-5 [&_p]:max-w-97.5 [&_p]:text-[13px] [&_p]:leading-[1.6] [&_p]:text-secondary">
        <span className="grid size-12 place-items-center rounded-xl border border-border bg-elevated text-secondary"><FolderOpen aria-hidden="true" size={24} strokeWidth={1.7} /></span>
        <h3>No documents yet</h3>
        <p>{canManage
          ? "Upload a PDF to begin building this workspace's knowledge base."
          : "No prepared documents are available in this workspace yet."}</p>
        {canManage && <UploadDocument label="Upload your first document" workspaceId={workspaceId} />}
      </div>
    );
  }

  return (
    <>
      <div className="overflow-hidden rounded-[13px] border border-border bg-surface max-[760px]:overflow-visible max-[760px]:border-0 max-[760px]:bg-transparent">
        <table className="w-full table-fixed border-collapse max-[760px]:block max-[760px]:[&_tbody]:block max-[760px]:[&_thead]:hidden [&_th]:bg-elevated [&_th]:px-4.5 [&_th]:py-3.25 [&_th]:text-left [&_th]:text-[10px] [&_th]:font-bold [&_th]:tracking-[.1em] [&_th]:text-secondary [&_th]:uppercase [&_th:first-child]:w-2/5 [&_td]:border-t [&_td]:border-border [&_td]:px-4.5 [&_td]:py-4.25 [&_td]:text-[13px] [&_td]:text-secondary [&_td]:align-middle [&_tbody_tr]:transition-colors [&_tbody_tr]:duration-150 hover:[&_tbody_tr]:bg-elevated motion-reduce:[&_tbody_tr]:transition-none max-[760px]:[&_tr]:mb-3 max-[760px]:[&_tr]:grid max-[760px]:[&_tr]:grid-cols-2 max-[760px]:[&_tr]:gap-x-3 max-[760px]:[&_tr]:gap-y-4 max-[760px]:[&_tr]:rounded-xl max-[760px]:[&_tr]:border max-[760px]:[&_tr]:border-border max-[760px]:[&_tr]:bg-surface max-[760px]:[&_tr]:p-4.5 max-[500px]:[&_tr]:grid-cols-1 max-[760px]:[&_td]:flex max-[760px]:[&_td]:min-w-0 max-[760px]:[&_td]:items-center max-[760px]:[&_td]:justify-between max-[760px]:[&_td]:gap-3 max-[760px]:[&_td]:border-0 max-[760px]:[&_td]:p-0 max-[760px]:[&_td]:before:text-[10px] max-[760px]:[&_td]:before:font-bold max-[760px]:[&_td]:before:tracking-[.08em] max-[760px]:[&_td]:before:text-secondary max-[760px]:[&_td]:before:uppercase max-[760px]:[&_td]:before:content-[attr(data-label)]">
          <thead><tr><th>Document</th><th>Status</th><th>Pages</th><th>File size</th><th>Uploaded</th></tr></thead>
          <tbody>
            {documents.data.map((document) => (
              <tr key={document.id}>
                <td className="flex items-center gap-3 max-[760px]:col-span-full max-[760px]:justify-start max-[760px]:border-b! max-[760px]:border-border! max-[760px]:pb-3.5! max-[760px]:before:hidden max-[500px]:col-span-1" data-label="Document">
                  <span className="grid size-8.75 shrink-0 place-items-center rounded-lg border border-border bg-elevated text-secondary"><FileText aria-hidden="true" size={18} /></span>
                  <span className="min-w-0 max-[760px]:flex-1 [&_strong]:block [&_strong]:overflow-hidden [&_strong]:text-ellipsis [&_strong]:whitespace-nowrap [&_strong]:text-[13px] [&_strong]:font-semibold [&_strong]:text-foreground [&_small]:mt-1 [&_small]:block [&_small]:max-w-97.5 [&_small]:overflow-hidden [&_small]:text-ellipsis [&_small]:whitespace-nowrap [&_small]:text-[11px] [&_small]:text-danger">
                    <strong title={document.original_filename}>{document.original_filename}</strong>
                    {document.status === "failed" && document.processing_error && <small>{document.processing_error}</small>}
                  </span>
                </td>
                <td data-label="Status"><DocumentStatus status={document.status} /></td>
                <td className="font-mono text-[11px]!" data-label="Pages">{document.page_count ?? "—"}</td>
                <td className="font-mono text-[11px]!" data-label="File size">{formatBytes(document.size_bytes)}</td>
                <td className="text-xs!" data-label="Uploaded">{formatDate(document.created_at)}</td>
              </tr>
            ))}
          </tbody>
        </table>
      </div>
      {documents.meta.last_page > 1 && (
        <nav aria-label="Document pagination" className="mt-4 flex items-center justify-between max-[500px]:flex-col max-[500px]:items-start max-[500px]:gap-3">
          <span className="font-mono text-[11px] text-secondary">Page {documents.meta.current_page} of {documents.meta.last_page}</span>
          <div className="flex gap-2">
            <PaginationLink disabled={!documents.links.prev} href={`/knowledge?page=${documents.meta.current_page - 1}`} label="Previous" icon="previous" />
            <PaginationLink disabled={!documents.links.next} href={`/knowledge?page=${documents.meta.current_page + 1}`} label="Next" icon="next" />
          </div>
        </nav>
      )}
    </>
  );
}

function PaginationLink({ disabled, href, label, icon }: { disabled: boolean; href: string; label: string; icon: "previous" | "next" }) {
  const content = <>{icon === "previous" && <ChevronLeft aria-hidden="true" size={16} />}{label}{icon === "next" && <ChevronRight aria-hidden="true" size={16} />}</>;
  const className = "inline-flex min-h-9 items-center justify-center gap-2 rounded-[9px] border border-border bg-elevated px-2.75 text-xs font-bold text-secondary transition-colors duration-160 hover:border-primary hover:text-primary motion-reduce:transition-none";
  return disabled ? <span aria-disabled="true" className={`${className} cursor-not-allowed opacity-35`}>{content}</span> : <Link className={className} href={href}>{content}</Link>;
}

function formatBytes(bytes: number): string {
  if (bytes < 1024) return `${bytes} B`;
  if (bytes < 1024 ** 2) return `${(bytes / 1024).toFixed(1)} KB`;
  return `${(bytes / 1024 ** 2).toFixed(1)} MB`;
}

function formatDate(value: string): string {
  return new Intl.DateTimeFormat("en", { day: "2-digit", month: "short", year: "numeric" }).format(new Date(value));
}
