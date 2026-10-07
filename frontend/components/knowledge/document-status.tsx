import { AlertCircle, CheckCircle2, LoaderCircle } from "lucide-react";
import type { DocumentStatus as Status } from "@/types/document";

const labels: Record<Status, string> = { processing: "Processing", ready: "Ready", failed: "Failed" };

export function DocumentStatus({ status }: { status: Status }) {
  const Icon = status === "ready" ? CheckCircle2 : status === "failed" ? AlertCircle : LoaderCircle;
  const tone = status === "ready"
    ? "border-[#b8d0c5] bg-success-soft text-success"
    : status === "failed"
      ? "border-[#dcb9bc] bg-danger-soft text-danger"
      : "border-[#ddc7a8] bg-warning-soft text-warning";
  return (
    <span className={`inline-flex items-center gap-1.5 rounded-full border px-2 py-1.25 text-[11px] font-bold ${tone}`}>
      <Icon aria-hidden="true" className={status === "processing" ? "animate-spin motion-reduce:animate-none" : undefined} size={14} />
      {labels[status]}
    </span>
  );
}
