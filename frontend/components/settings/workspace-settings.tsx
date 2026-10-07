"use client";

import {
  AlertCircle,
  CheckCircle2,
  FileText,
  LoaderCircle,
  Save,
  Upload,
} from "lucide-react";
import { FormEvent, useState } from "react";
import { useRouter } from "next/navigation";
import {
  updateWorkspaceSettings,
  WorkspaceSettingsApiError,
} from "@/lib/settings";
import type { WorkspaceSettings as WorkspaceSettingsData } from "@/types/settings";
import { CustomerWidgetSettings } from "./customer-widget-settings";

const sectionClass = "grid grid-cols-[minmax(190px,.75fr)_minmax(0,1.45fr)] gap-[clamp(24px,5vw,58px)] rounded-[13px] border border-border bg-surface p-6 max-[680px]:grid-cols-1 max-[680px]:gap-6 max-[680px]:p-5";
const sectionIntroClass = "[&_h2]:m-0 [&_h2]:text-[15px] [&_h2]:tracking-[-.01em] [&_p]:mt-1.75 [&_p]:mb-0 [&_p]:text-xs [&_p]:leading-[1.6] [&_p]:text-secondary";

interface WorkspaceSettingsProps {
  canUpdate: boolean;
  settings: WorkspaceSettingsData;
  workspaceId: number;
}

export function WorkspaceSettings({ canUpdate, settings, workspaceId }: WorkspaceSettingsProps) {
  const router = useRouter();
  const [name, setName] = useState(settings.workspace.name);
  const [savedName, setSavedName] = useState(settings.workspace.name);
  const [fieldError, setFieldError] = useState<string | null>(null);
  const [requestError, setRequestError] = useState<string | null>(null);
  const [successMessage, setSuccessMessage] = useState<string | null>(null);
  const [saving, setSaving] = useState(false);
  const normalizedName = name.trim();
  const hasChanges = normalizedName !== savedName;

  async function submit(event: FormEvent<HTMLFormElement>) {
    event.preventDefault();

    if (!canUpdate) return;

    if (normalizedName === "") {
      setFieldError("Enter a workspace name.");
      return;
    }

    if (normalizedName.length > 255) {
      setFieldError("Keep the workspace name under 256 characters.");
      return;
    }

    setFieldError(null);
    setRequestError(null);
    setSuccessMessage(null);
    setSaving(true);

    try {
      const updatedSettings = await updateWorkspaceSettings(workspaceId, normalizedName);
      setName(updatedSettings.workspace.name);
      setSavedName(updatedSettings.workspace.name);
      setSuccessMessage("Workspace name updated.");
      router.refresh();
    } catch (error) {
      if (error instanceof WorkspaceSettingsApiError && error.nameError) {
        setFieldError(error.nameError);
      } else if (error instanceof WorkspaceSettingsApiError) {
        setRequestError(error.message);
      } else {
        setRequestError("Sift could not reach the API. Check your connection and try again.");
      }
    } finally {
      setSaving(false);
    }
  }

  return (
    <div className="mt-8.5 grid gap-3.5">
      {successMessage && (
        <div className="flex items-center gap-2.25 rounded-[9px] border border-[#b8d0c5] bg-success-soft px-3.25 py-2.75 text-xs text-success" role="status">
          <CheckCircle2 aria-hidden="true" size={17} />
          <span>{successMessage}</span>
        </div>
      )}

      <section className={sectionClass}>
        <div className={sectionIntroClass}>
          <h2>Workspace profile</h2>
          <p>{canUpdate
            ? "Update the name used to identify this workspace throughout Sift."
            : "Workspace details are read-only for your current role."}</p>
        </div>

        {!canUpdate && (
          <p className="mt-4.5 mr-6 mb-0 ml-6 rounded-[9px] border border-border bg-elevated px-3.25 py-2.75 text-xs leading-[1.55] text-secondary">Only workspace owners can update these settings.</p>
        )}

        <form aria-busy={saving} className="min-w-0" onSubmit={submit}>
          <div>
            <label className="mb-2 block text-xs font-bold text-foreground" htmlFor="workspace-name">Workspace name</label>
            <input
              className="min-h-10.5 w-full rounded-[9px] border border-border bg-background px-3 text-[13px] text-foreground outline-none transition-[border-color,box-shadow] duration-160 hover:border-muted focus:border-primary focus:shadow-[0_0_0_3px_var(--focus-ring)] disabled:cursor-wait disabled:opacity-62 motion-reduce:transition-none"
              aria-describedby={fieldError ? "workspace-name-help workspace-name-error" : "workspace-name-help"}
              aria-invalid={fieldError ? "true" : undefined}
              disabled={saving}
              id="workspace-name"
              maxLength={255}
              onChange={(event) => {
                setName(event.target.value);
                setFieldError(null);
                setRequestError(null);
                setSuccessMessage(null);
              }}
              readOnly={!canUpdate}
              type="text"
              value={name}
            />
            <div className="mt-1.75 flex justify-between gap-4 [&_p]:m-0 [&_p]:text-[10px] [&_p]:leading-[1.5] [&_p]:text-muted [&>span]:shrink-0 [&>span]:font-mono [&>span]:text-[10px] [&>span]:leading-[1.5] [&>span]:text-muted">
              <p id="workspace-name-help">This name appears in workspace navigation and summaries.</p>
              <span>{name.length} / 255</span>
            </div>
            {fieldError && <p className="mt-2 mb-0 text-[11px] leading-[1.5] text-danger" id="workspace-name-error" role="alert">{fieldError}</p>}
          </div>

          {requestError && (
            <p className="mt-2 mb-0 flex items-center gap-1.75 rounded-lg border border-[#dcb9bc] bg-danger-soft px-2.75 py-2.5 text-[11px] leading-[1.5] text-danger" role="alert">
              <AlertCircle aria-hidden="true" size={15} />
              {requestError}
            </p>
          )}

          <div className="mt-5.5 flex items-center justify-between gap-5 border-t border-border pt-4.25 max-[480px]:flex-col max-[480px]:items-stretch [&>p]:m-0 [&>p]:font-mono [&>p]:text-[9px] [&>p]:text-muted [&>button]:inline-flex [&>button]:min-h-9.5 [&>button]:cursor-pointer [&>button]:items-center [&>button]:justify-center [&>button]:gap-1.75 [&>button]:rounded-lg [&>button]:border [&>button]:border-primary [&>button]:bg-primary [&>button]:px-3.25 [&>button]:text-xs [&>button]:font-bold [&>button]:text-surface [&>button]:transition-colors [&>button]:duration-160 hover:[&>button:not(:disabled)]:border-primary-hover hover:[&>button:not(:disabled)]:bg-primary-hover [&>button:disabled]:cursor-not-allowed [&>button:disabled]:opacity-48 max-[480px]:[&>button]:w-full motion-reduce:[&>button]:transition-none">
            <p>Created <time dateTime={settings.workspace.created_at}>{formatDate(settings.workspace.created_at)}</time></p>
            {canUpdate && (
              <button disabled={saving || !hasChanges || normalizedName === ""} type="submit">
                {saving
                  ? <><LoaderCircle aria-hidden="true" className="animate-spin motion-reduce:animate-none" size={16} /> Saving…</>
                  : <><Save aria-hidden="true" size={16} /> Save changes</>}
              </button>
            )}
          </div>
        </form>
      </section>

      <section className={sectionClass}>
        <div className={sectionIntroClass}>
          <h2>Knowledge uploads</h2>
          <p>Current document requirements for this Sift installation.</p>
        </div>

        <dl className="m-0 [&>div]:flex [&>div]:items-center [&>div]:justify-between [&>div]:gap-4.5 [&>div]:border-b [&>div]:border-border [&>div]:py-3.25 [&>div:first-child]:pt-0 [&>div:last-child]:border-b-0 [&>div:last-child]:pb-0 max-[480px]:[&>div]:flex-col max-[480px]:[&>div]:items-start max-[480px]:[&>div]:gap-2 [&_dt]:flex [&_dt]:items-center [&_dt]:gap-2.25 [&_dt]:text-xs [&_dt]:text-secondary [&_dt_span]:grid [&_dt_span]:size-7.75 [&_dt_span]:place-items-center [&_dt_span]:rounded-lg [&_dt_span]:border [&_dt_span]:border-border [&_dt_span]:bg-primary-soft [&_dt_span]:text-primary [&_dd]:m-0 [&_dd]:font-mono [&_dd]:text-[11px] [&_dd]:font-semibold [&_dd]:text-foreground max-[480px]:[&_dd]:pl-10">
          <div>
            <dt><span><FileText aria-hidden="true" size={17} /></span>Supported format</dt>
            <dd>{settings.knowledge.supported_document_types.map((type) => type.label).join(", ")}</dd>
          </div>
          <div>
            <dt><span><Upload aria-hidden="true" size={17} /></span>Maximum PDF size</dt>
            <dd>{formatBytes(settings.knowledge.maximum_upload_size_bytes)}</dd>
          </div>
        </dl>
      </section>

      <CustomerWidgetSettings
        canUpdate={canUpdate}
        initialWidget={settings.widget}
        workspaceId={workspaceId}
      />
    </div>
  );
}

function formatDate(value: string): string {
  return new Intl.DateTimeFormat("en", {
    day: "2-digit",
    month: "short",
    year: "numeric",
    timeZone: "UTC",
  }).format(new Date(value));
}

function formatBytes(bytes: number): string {
  if (bytes < 1024 ** 2) return `${Math.round(bytes / 1024).toLocaleString()} KB`;

  const megabytes = bytes / 1024 ** 2;

  return `${Number.isInteger(megabytes) ? megabytes : megabytes.toFixed(1)} MB`;
}
