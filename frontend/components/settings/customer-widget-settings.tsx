"use client";

import {
  AlertCircle,
  Check,
  Code2,
  Copy,
  ExternalLink,
  LoaderCircle,
  MessageCircleMore,
  Power,
  RotateCcw,
} from "lucide-react";
import { useRouter } from "next/navigation";
import { useMemo, useState } from "react";
import {
  provisionWorkspaceWidget,
  rotateWorkspaceWidgetKey,
  setWorkspaceWidgetEnabled,
  WorkspaceSettingsApiError,
} from "@/lib/settings";
import type { WorkspaceWidgetSettings } from "@/types/settings";

const sectionClass = "grid grid-cols-[minmax(190px,.75fr)_minmax(0,1.45fr)] items-start gap-[clamp(24px,5vw,58px)] rounded-[13px] border border-border bg-surface p-6 max-[680px]:grid-cols-1 max-[680px]:gap-6 max-[680px]:p-5";
const buttonClass = "inline-flex min-h-9 cursor-pointer items-center justify-center gap-1.75 rounded-lg border border-border bg-elevated px-2.75 text-[11px] font-bold text-secondary transition-[color,border-color,background,opacity] duration-160 hover:not-disabled:border-primary hover:not-disabled:text-primary disabled:cursor-not-allowed disabled:opacity-50 motion-reduce:transition-none";
const primaryButtonClass = `${buttonClass} border-primary bg-primary text-surface hover:not-disabled:border-primary-hover hover:not-disabled:bg-primary-hover hover:not-disabled:text-surface`;
const statusMessageClass = "mt-4 flex items-start gap-1.75 rounded-lg border px-2.75 py-2.5 text-[10px] leading-[1.5]";

interface CustomerWidgetSettingsProps {
  canUpdate: boolean;
  initialWidget: WorkspaceWidgetSettings | null;
  workspaceId: number;
}

type WidgetAction = "provision" | "toggle" | "rotate" | null;

export function CustomerWidgetSettings({
  canUpdate,
  initialWidget,
  workspaceId,
}: CustomerWidgetSettingsProps) {
  const router = useRouter();
  const [widget, setWidget] = useState(initialWidget);
  const [action, setAction] = useState<WidgetAction>(null);
  const [confirmingRotation, setConfirmingRotation] = useState(false);
  const [copied, setCopied] = useState(false);
  const [error, setError] = useState<string | null>(null);
  const [notice, setNotice] = useState<string | null>(null);
  const embedCode = useMemo(() => widget ? buildEmbedCode(widget) : "", [widget]);
  const busy = action !== null;

  async function provision() {
    if (!canUpdate || busy) return;
    await runAction("provision", async () => {
      const provisioned = await provisionWorkspaceWidget(workspaceId);
      setWidget(provisioned);
      setNotice("Customer Widget set up. Enable it when you are ready to publish the embed code.");
    });
  }

  async function toggleEnabled() {
    if (!canUpdate || !widget || busy) return;
    await runAction("toggle", async () => {
      const updated = await setWorkspaceWidgetEnabled(workspaceId, !widget.enabled);
      setWidget(updated);
      setNotice(updated.enabled ? "Customer Widget enabled." : "Customer Widget disabled.");
    });
  }

  async function rotateIdentifier() {
    if (!canUpdate || !widget || busy || !confirmingRotation) return;
    await runAction("rotate", async () => {
      const updated = await rotateWorkspaceWidgetKey(workspaceId);
      setWidget(updated);
      setConfirmingRotation(false);
      setCopied(false);
      setNotice("Widget identifier rotated. Copy the updated embed code to your website.");
    });
  }

  async function copyEmbedCode() {
    if (!embedCode) return;
    setError(null);

    try {
      await navigator.clipboard.writeText(embedCode);
      setCopied(true);
      setNotice("Embed code copied.");
      window.setTimeout(() => setCopied(false), 2_000);
    } catch {
      setError("The embed code could not be copied. Select and copy it manually.");
    }
  }

  async function runAction(nextAction: Exclude<WidgetAction, null>, operation: () => Promise<void>) {
    setAction(nextAction);
    setError(null);
    setNotice(null);

    try {
      await operation();
      router.refresh();
    } catch (requestError) {
      setError(requestError instanceof WorkspaceSettingsApiError
        ? requestError.message
        : "Sift could not reach the API. Check your connection and try again.");
    } finally {
      setAction(null);
    }
  }

  return (
    <section className={sectionClass}>
      <div className="[&_h2]:m-0 [&_h2]:text-[15px] [&_h2]:tracking-[-.01em] [&_p]:mt-1.75 [&_p]:mb-0 [&_p]:text-xs [&_p]:leading-[1.6] [&_p]:text-secondary">
        <span className="mb-3.25 grid size-8.5 place-items-center rounded-[9px] border border-border bg-primary-soft text-primary"><MessageCircleMore aria-hidden="true" size={18} /></span>
        <h2>Customer Widget</h2>
        <p>Add Sift to your website so customers can ask questions using your company knowledge.</p>
      </div>

      <div className="min-w-0">
        {!widget && (
          <div className="rounded-[10px] border border-dashed border-border bg-background p-4.5 [&_h3]:m-0 [&_h3]:text-xs [&_p]:mt-1.25 [&_p]:mb-0 [&_p]:text-[11px] [&_p]:leading-[1.55] [&_p]:text-secondary">
            <h3>Widget not configured</h3>
            <p>{canUpdate
              ? "Set up the Widget to generate an embed snippet. It starts disabled."
              : "A workspace owner has not configured the Customer Widget yet."}</p>
            {canUpdate && (
              <button className={`${primaryButtonClass} mt-4`} disabled={busy} onClick={provision} type="button">
                {action === "provision"
                  ? <><LoaderCircle aria-hidden="true" className="animate-spin motion-reduce:animate-none" size={16} /> Setting up…</>
                  : <><Code2 aria-hidden="true" size={16} /> Set up widget</>}
              </button>
            )}
          </div>
        )}

        {widget && (
          <>
            <div className="flex items-start justify-between gap-4.5 max-[480px]:flex-col max-[480px]:items-stretch [&_p]:mt-1.25 [&_p]:mb-0 [&_p]:text-[11px] [&_p]:leading-[1.55] [&_p]:text-secondary">
              <div>
                <span className={`inline-flex rounded-full border px-2 py-1 text-[9px] font-extrabold tracking-[.06em] uppercase ${widget.enabled ? "border-[#b8d0c5] bg-success-soft text-success" : "border-border bg-elevated text-secondary"}`}>
                  {widget.enabled ? "Enabled" : "Disabled"}
                </span>
                <p>{widget.enabled
                  ? "Customers can use the Widget wherever this snippet is installed."
                  : "The embed remains unavailable to customers until enabled."}</p>
              </div>
              {canUpdate && (
                <button className={`${buttonClass} max-[480px]:w-full`} disabled={busy} onClick={toggleEnabled} type="button">
                  {action === "toggle"
                    ? <><LoaderCircle aria-hidden="true" className="animate-spin motion-reduce:animate-none" size={15} /> Updating…</>
                    : <><Power aria-hidden="true" size={15} /> {widget.enabled ? "Disable Widget" : "Enable Widget"}</>}
                </button>
              )}
            </div>

            <div className="mt-5 border-t border-border pt-4.5">
              <div className="flex items-start justify-between gap-4 max-[480px]:flex-col max-[480px]:items-stretch [&_h3]:m-0 [&_h3]:text-xs [&_p]:mt-1.25 [&_p]:mb-0 [&_p]:text-[11px] [&_p]:leading-[1.55] [&_p]:text-secondary">
                <div>
                  <h3>Preview customer experience</h3>
                  <p>Open the customer-facing Widget in a new tab to test it.</p>
                </div>
                <a
                  className={`${buttonClass} min-h-8 shrink-0 text-[10px] max-[480px]:w-full`}
                  href={`/widget/${encodeURIComponent(widget.public_key)}`}
                  rel="noopener noreferrer"
                  target="_blank"
                >
                  <ExternalLink aria-hidden="true" size={14} />
                  Open Widget
                </a>
              </div>
            </div>

            <div className="mt-5 border-t border-border pt-4.5">
              <div className="flex items-start justify-between gap-4 max-[480px]:flex-col max-[480px]:items-stretch [&_h3]:m-0 [&_h3]:text-xs [&_p]:mt-1.25 [&_p]:mb-0 [&_p]:text-[11px] [&_p]:leading-[1.55] [&_p]:text-secondary">
                <div><h3>Embed code</h3><p>Paste this before the closing body tag on your website.</p></div>
                <button className={`${buttonClass} min-h-8 shrink-0 text-[10px] max-[480px]:w-full`} onClick={copyEmbedCode} type="button">
                  {copied ? <Check aria-hidden="true" size={14} /> : <Copy aria-hidden="true" size={14} />}
                  {copied ? "Copied" : "Copy embed code"}
                </button>
              </div>
              <pre className="mt-3 overflow-x-auto rounded-[9px] border border-border bg-background px-3.5 py-3.25 font-mono text-[10px] leading-[1.65] whitespace-pre text-foreground focus:outline-2 focus:outline-offset-2 focus:outline-primary" tabIndex={0}><code>{embedCode}</code></pre>
            </div>

            {widget.key_rotated_at && (
              <p className="mt-2.5 mb-0 font-mono text-[9px] text-muted">
                Identifier last rotated <time dateTime={widget.key_rotated_at}>{formatDateTime(widget.key_rotated_at)}</time>
              </p>
            )}

            {canUpdate && (
              <div className="mt-5.25 flex items-start justify-between gap-4.5 border-t border-border pt-4.5 max-[480px]:flex-col max-[480px]:items-stretch [&_h3]:m-0 [&_h3]:text-xs [&_p]:mt-1.25 [&_p]:mb-0 [&_p]:text-[11px] [&_p]:leading-[1.55] [&_p]:text-secondary">
                <div><h3>Advanced</h3><p>Rotation immediately stops existing embed snippets and older browser sessions.</p></div>
                {!confirmingRotation ? (
                  <button className={`${buttonClass} shrink-0 text-danger hover:not-disabled:border-danger hover:not-disabled:bg-danger-soft hover:not-disabled:text-danger max-[480px]:w-full`} disabled={busy} onClick={() => setConfirmingRotation(true)} type="button">
                    <RotateCcw aria-hidden="true" size={15} /> Rotate widget identifier
                  </button>
                ) : (
                  <div className="max-w-82.5 rounded-[9px] border border-[#dcb9bc] bg-danger-soft p-3 max-[480px]:max-w-none [&>p]:m-0 [&>p]:text-[10px] [&>p]:leading-[1.55] [&>p]:text-secondary [&>p_strong]:text-foreground [&>div]:mt-2.75 [&>div]:flex [&>div]:justify-end [&>div]:gap-1.75">
                    <p><strong>Update your website after rotating.</strong> Existing snippets will stop working immediately. Copy the newly generated embed code afterward.</p>
                    <div>
                      <button className={buttonClass} disabled={busy} onClick={() => setConfirmingRotation(false)} type="button">Cancel</button>
                      <button className={`${buttonClass} border-danger bg-danger text-surface`} disabled={busy} onClick={rotateIdentifier} type="button">
                        {action === "rotate"
                          ? <><LoaderCircle aria-hidden="true" className="animate-spin motion-reduce:animate-none" size={14} /> Rotating…</>
                          : "Confirm rotation"}
                      </button>
                    </div>
                  </div>
                )}
              </div>
            )}

            {!canUpdate && <p className={`${statusMessageClass} border-border bg-elevated text-secondary`}>Widget configuration is read-only for your current role.</p>}
          </>
        )}

        {notice && <p className={`${statusMessageClass} border-[#b8d0c5] bg-success-soft text-success`} role="status"><Check aria-hidden="true" size={14} />{notice}</p>}
        {error && <p className={`${statusMessageClass} border-[#dcb9bc] bg-danger-soft text-danger`} role="alert"><AlertCircle aria-hidden="true" size={14} />{error}</p>}
      </div>
    </section>
  );
}

function buildEmbedCode(widget: WorkspaceWidgetSettings): string {
  return `<script\n  async\n  src="${widget.script_url}"\n  data-widget-key="${widget.public_key}"\n></script>`;
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
