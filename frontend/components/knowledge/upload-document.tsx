"use client";

import { CheckCircle2, FileUp, Upload, X } from "lucide-react";
import { useRouter } from "next/navigation";
import { FormEvent, useEffect, useId, useRef, useState } from "react";
import { apiUrl } from "@/lib/api";
import { ensureCsrfCookie, getXsrfToken } from "@/lib/sanctum";
import type { ValidationErrorResponse } from "@/types/document";

interface UploadDocumentProps { workspaceId: number; label?: string }

export function UploadDocument({ workspaceId, label = "Upload document" }: UploadDocumentProps) {
  const router = useRouter();
  const inputId = useId();
  const requestRef = useRef<XMLHttpRequest | null>(null);
  const [open, setOpen] = useState(false);
  const [file, setFile] = useState<File | null>(null);
  const [progress, setProgress] = useState(0);
  const [uploading, setUploading] = useState(false);
  const [error, setError] = useState<string | null>(null);
  const [completedFilename, setCompletedFilename] = useState<string | null>(null);

  useEffect(() => () => requestRef.current?.abort(), []);

  function resetAndClose() {
    if (uploading) return;
    setOpen(false);
    setFile(null);
    setProgress(0);
    setError(null);
    setCompletedFilename(null);
  }

  function selectFile(selected: File | null) {
    setError(null);
    setCompletedFilename(null);
    if (!selected) { setFile(null); return; }

    const pdfType = selected.type === "application/pdf" || selected.type === "";
    if (!pdfType || !selected.name.toLowerCase().endsWith(".pdf")) {
      setFile(null);
      setError("Choose a PDF file to continue.");
      return;
    }
    setFile(selected);
  }

  async function submit(event: FormEvent<HTMLFormElement>) {
    event.preventDefault();
    if (!file || uploading) {
      if (!file) setError("Choose a PDF file to continue.");
      return;
    }

    setUploading(true);
    setProgress(0);
    setError(null);

    try {
      await ensureCsrfCookie();
    } catch {
      setUploading(false);
      setError("Sift could not initialize a secure upload. Please try again.");
      return;
    }

    const formData = new FormData();
    formData.append("file", file);
    const request = new XMLHttpRequest();
    requestRef.current = request;
    request.open("POST", apiUrl(`/workspaces/${workspaceId}/documents`));
    request.withCredentials = true;
    request.setRequestHeader("Accept", "application/json");
    const xsrfToken = getXsrfToken();
    if (xsrfToken) request.setRequestHeader("X-XSRF-TOKEN", xsrfToken);
    request.upload.addEventListener("progress", (progressEvent) => {
      if (progressEvent.lengthComputable) {
        setProgress(Math.round((progressEvent.loaded / progressEvent.total) * 100));
      }
    });
    request.addEventListener("load", () => {
      setUploading(false);
      requestRef.current = null;
      const response = parseResponse(request.responseText);
      if (request.status >= 200 && request.status < 300) {
        setProgress(100);
        setCompletedFilename(file.name);
        router.refresh();
        return;
      }
      setError(
        request.status === 422
          ? validationMessage(response) ?? "The selected file is not valid."
          : "The document could not be uploaded. Please try again.",
      );
    });
    request.addEventListener("error", () => {
      setUploading(false);
      requestRef.current = null;
      setError("The API could not be reached. Check your connection and try again.");
    });
    request.addEventListener("abort", () => {
      setUploading(false);
      requestRef.current = null;
    });
    request.send(formData);
  }

  return (
    <>
      <button className="inline-flex min-h-10.5 cursor-pointer items-center justify-center gap-2 rounded-[9px] border border-primary bg-primary px-4 text-[13px] font-bold text-surface transition-colors duration-160 hover:border-primary-hover hover:bg-primary-hover disabled:cursor-not-allowed disabled:opacity-45 max-[500px]:w-full motion-reduce:transition-none" onClick={() => setOpen(true)} type="button">
        <Upload aria-hidden="true" size={17} /> {label}
      </button>
      {open && (
        <div aria-labelledby={`${inputId}-title`} aria-modal="true" className="fixed inset-0 z-50 grid place-items-center bg-[rgba(33,29,30,.5)] p-5" role="dialog">
          <div className="w-[min(100%,510px)] rounded-[14px] border border-border bg-surface p-6 shadow-[0_24px_70px_rgba(54,43,46,.18)] max-[500px]:p-5">
            <div className="flex items-start justify-between gap-5 [&_h2]:m-0 [&_h2]:text-xl [&_h2]:tracking-[-.025em]">
              <div><p className="mt-0 mb-2.25 text-[11px] font-bold tracking-[.13em] text-primary uppercase">Add knowledge</p><h2 id={`${inputId}-title`}>Upload document</h2></div>
              <button aria-label="Close upload dialog" className="grid size-9 shrink-0 cursor-pointer place-items-center rounded-lg border border-border bg-elevated p-0 text-secondary disabled:cursor-not-allowed disabled:opacity-40" disabled={uploading} onClick={resetAndClose} type="button"><X aria-hidden="true" size={19} /></button>
            </div>

            {completedFilename ? (
              <div className="flex flex-col items-center px-4 pt-10 pb-3 text-center text-primary [&_h3]:mt-3.5 [&_h3]:mb-0 [&_h3]:text-[17px] [&_h3]:text-foreground [&_p]:mt-1.75 [&_p]:mb-5.5 [&_p]:text-[13px] [&_p]:text-secondary [&_strong]:text-foreground">
                <CheckCircle2 aria-hidden="true" size={28} />
                <h3>Upload complete</h3>
                <p><strong>{completedFilename}</strong> is now visible as processing.</p>
                <button className="inline-flex min-h-10.5 cursor-pointer items-center justify-center gap-2 rounded-[9px] border border-primary bg-primary px-4 text-[13px] font-bold text-surface transition-colors duration-160 hover:border-primary-hover hover:bg-primary-hover" onClick={resetAndClose} type="button">Done</button>
              </div>
            ) : (
              <form onSubmit={submit}>
                <label className={`mt-6.25 flex min-h-46.25 cursor-pointer flex-col items-center justify-center rounded-[11px] border border-dashed bg-elevated p-6 text-center text-secondary hover:border-primary [&_input]:absolute [&_input]:size-px [&_input]:overflow-hidden [&_input]:opacity-0 [&_strong]:mt-3.5 [&_strong]:block [&_strong]:max-w-full [&_strong]:overflow-hidden [&_strong]:text-ellipsis [&_strong]:whitespace-nowrap [&_strong]:text-sm [&_strong]:text-foreground [&>span:last-of-type]:mt-1.5 [&>span:last-of-type]:text-[11px] [&>span:last-of-type]:text-secondary ${error ? "border-danger" : "border-muted"}`} htmlFor={inputId}>
                  <span className="grid size-11 place-items-center rounded-[11px] border border-border bg-primary-soft text-primary"><FileUp aria-hidden="true" size={23} /></span>
                  <strong>{file ? file.name : "Choose a PDF document"}</strong>
                  <span>{file ? formatBytes(file.size) : "PDF only · upload limits are validated by Sift"}</span>
                  <input accept="application/pdf,.pdf" disabled={uploading} id={inputId} onChange={(event) => selectFile(event.target.files?.[0] ?? null)} type="file" />
                </label>
                {error && <p className="mt-2.5 mb-0 text-xs text-danger" role="alert">{error}</p>}
                {uploading && (
                  <div aria-live="polite" className="mt-4.5">
                    <div className="flex justify-between text-[11px] text-secondary"><span>Uploading document</span><strong className="font-mono text-primary">{progress}%</strong></div>
                    <div className="mt-2 h-1.25 overflow-hidden rounded-full bg-border"><span className="block h-full rounded-[inherit] bg-primary transition-[width] duration-160 motion-reduce:transition-none" style={{ width: `${progress}%` }} /></div>
                  </div>
                )}
                <div className="mt-5.5 flex justify-end gap-2.25">
                  <button className="inline-flex min-h-10.5 cursor-pointer items-center justify-center gap-2 rounded-[9px] border border-border bg-elevated px-4 text-[13px] font-bold text-secondary transition-colors duration-160 hover:not-disabled:border-primary hover:not-disabled:text-primary disabled:cursor-not-allowed disabled:opacity-45" disabled={uploading} onClick={resetAndClose} type="button">Cancel</button>
                  <button className="inline-flex min-h-10.5 cursor-pointer items-center justify-center gap-2 rounded-[9px] border border-primary bg-primary px-4 text-[13px] font-bold text-surface transition-colors duration-160 hover:not-disabled:border-primary-hover hover:not-disabled:bg-primary-hover disabled:cursor-not-allowed disabled:opacity-45" disabled={!file || uploading} type="submit">
                    <Upload aria-hidden="true" size={16} /> {uploading ? "Uploading…" : "Upload PDF"}
                  </button>
                </div>
              </form>
            )}
          </div>
        </div>
      )}
    </>
  );
}

function parseResponse(value: string): ValidationErrorResponse | null {
  try { return JSON.parse(value) as ValidationErrorResponse; } catch { return null; }
}

function validationMessage(response: ValidationErrorResponse | null): string | null {
  return response?.errors?.file?.[0] ?? response?.message ?? null;
}

function formatBytes(bytes: number): string {
  return bytes < 1024 ** 2 ? `${(bytes / 1024).toFixed(1)} KB` : `${(bytes / 1024 ** 2).toFixed(1)} MB`;
}
