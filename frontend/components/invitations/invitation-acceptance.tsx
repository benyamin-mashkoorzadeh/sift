"use client";

import {
  AlertCircle,
  ArrowRight,
  Building2,
  CalendarClock,
  CheckCircle2,
  LoaderCircle,
  LockKeyhole,
  Mail,
  ScanSearch,
  ShieldCheck,
} from "lucide-react";
import { useRouter } from "next/navigation";
import { FormEvent, useState } from "react";
import type { ReactNode } from "react";
import { acceptInvitation, InvitationApiError } from "@/lib/invitations";
import type { InvitationPreview } from "@/types/team";

interface InvitationAcceptanceProps {
  authenticatedEmail: string | null;
  preview: InvitationPreview | null;
  token: string;
}

export function InvitationAcceptance({ authenticatedEmail, preview, token }: InvitationAcceptanceProps) {
  const router = useRouter();
  const [name, setName] = useState("");
  const [password, setPassword] = useState("");
  const [passwordConfirmation, setPasswordConfirmation] = useState("");
  const [errors, setErrors] = useState<Record<string, string[]>>({});
  const [requestError, setRequestError] = useState<string | null>(null);
  const [submitting, setSubmitting] = useState(false);

  if (!preview) return <UnavailableState />;

  const authenticatedMatch = authenticatedEmail?.toLowerCase() === preview.email.toLowerCase();
  const authenticatedMismatch = authenticatedEmail !== null && !authenticatedMatch;

  async function submit(event: FormEvent<HTMLFormElement>) {
    event.preventDefault();
    if (!preview || authenticatedMismatch) return;

    const payload: Record<string, string> = authenticatedMatch
      ? {}
      : preview.account_state === "existing_account"
        ? { password }
        : { name, password, password_confirmation: passwordConfirmation };

    setErrors({});
    setRequestError(null);
    setSubmitting(true);

    try {
      await acceptInvitation(token, payload);
      router.replace("/overview");
      router.refresh();
    } catch (error) {
      if (error instanceof InvitationApiError) {
        setErrors(error.errors);
        setRequestError(Object.keys(error.errors).length === 0 ? error.message : null);
      } else {
        setRequestError("Sift could not reach the API. Check your connection and try again.");
      }
      setSubmitting(false);
    }
  }

  return (
    <section className="w-[min(100%,580px)]">
      <Brand />
      <article className="rounded-[14px] border border-border bg-surface p-8 max-[560px]:px-4.75 max-[560px]:py-6">
        <header className="mb-5.75 [&_h1]:m-0 [&_h1]:text-[28px] [&_h1]:leading-[1.2] [&_h1]:tracking-[-.04em] [&>p]:mt-0 [&>p]:mb-2 [&>p]:text-[10px] [&>p]:font-extrabold [&>p]:tracking-[.13em] [&>p]:text-primary [&>p]:uppercase [&>span]:mt-2.25 [&>span]:block [&>span]:text-[13px] [&>span]:leading-[1.55] [&>span]:text-secondary">
          <p>Workspace invitation</p>
          <h1>Join {preview.workspace.name}</h1>
          <span>You have been invited to collaborate in Sift.</span>
        </header>

        <dl className="m-0 grid grid-cols-2 rounded-[10px] border border-border bg-background max-[560px]:grid-cols-1 [&>div]:min-w-0 [&>div]:border-r [&>div]:border-b [&>div]:border-border [&>div]:p-3.25 [&>div:nth-child(2n)]:border-r-0 [&>div:nth-last-child(-n+2)]:border-b-0 max-[560px]:[&>div]:border-r-0 max-[560px]:[&>div:nth-last-child(2)]:border-b [&_dt]:flex [&_dt]:items-center [&_dt]:gap-1.5 [&_dt]:text-[9px] [&_dt]:font-bold [&_dt]:tracking-[.06em] [&_dt]:text-muted [&_dt]:uppercase [&_dd]:mt-1.5 [&_dd]:mb-0 [&_dd]:overflow-hidden [&_dd]:text-ellipsis [&_dd]:whitespace-nowrap [&_dd]:text-[11px] [&_dd]:font-semibold [&_dd]:text-foreground">
          <div><dt><Building2 aria-hidden="true" size={16} />Workspace</dt><dd>{preview.workspace.name}</dd></div>
          <div><dt><Mail aria-hidden="true" size={16} />Invited email</dt><dd>{preview.email}</dd></div>
          <div><dt><ShieldCheck aria-hidden="true" size={16} />Role</dt><dd>{capitalize(preview.role)}</dd></div>
          <div><dt><CalendarClock aria-hidden="true" size={16} />Expires</dt><dd>{formatDateTime(preview.expires_at)}</dd></div>
        </dl>

        {authenticatedMismatch ? (
          <div className="mt-5.5 flex items-start gap-2.5 rounded-[9px] border border-[#dcb9bc] bg-danger-soft p-3 [&>svg]:mt-px [&>svg]:shrink-0 [&>svg]:text-danger [&_h2]:m-0 [&_h2]:text-xs [&_p]:mt-1 [&_p]:mb-0 [&_p]:text-[10px] [&_p]:leading-[1.55] [&_p]:text-secondary" role="alert">
            <AlertCircle aria-hidden="true" size={19} />
            <div><h2>Different account signed in</h2><p>This invitation belongs to a different account. Sign out before accepting it with the invited email.</p></div>
          </div>
        ) : (
          <form aria-busy={submitting} className="mt-5.5 grid gap-3.75" onSubmit={submit}>
            {authenticatedMatch ? (
              <Notice icon={<CheckCircle2 aria-hidden="true" size={19} />} title="Ready to join">You are signed in as the invited account, {preview.email}.</Notice>
            ) : preview.account_state === "existing_account" ? (
              <>
                <Notice icon={<LockKeyhole aria-hidden="true" size={19} />} title="Verify your account">Enter the password for {preview.email} to join this workspace.</Notice>
                <Field autoComplete="current-password" error={errors.password?.[0]} id="invitation-password" label="Password" onChange={setPassword} type="password" value={password} />
              </>
            ) : (
              <>
                <Notice icon={<CheckCircle2 aria-hidden="true" size={19} />} title="Create your Sift account">Your account will join this existing workspace. It will not create another company.</Notice>
                <Field autoComplete="name" error={errors.name?.[0]} id="invitation-name" label="Name" onChange={setName} type="text" value={name} />
                <Field autoComplete="new-password" error={errors.password?.[0]} id="invitation-password" label="Password" onChange={setPassword} type="password" value={password} />
                <Field autoComplete="new-password" error={errors.password_confirmation?.[0]} id="invitation-password-confirmation" label="Confirm password" onChange={setPasswordConfirmation} type="password" value={passwordConfirmation} />
                <p className="-mt-1.25 mb-0 text-[9px] leading-[1.5] text-muted">Use at least 8 characters with uppercase, lowercase, and a number.</p>
              </>
            )}

            {requestError && <p className="m-0 flex items-start gap-2 rounded-[9px] border border-[#dcb9bc] bg-danger-soft px-3 py-2.75 text-[11px] leading-[1.5] text-danger" role="alert"><AlertCircle aria-hidden="true" size={16} />{requestError}</p>}

            <button className="flex min-h-10.75 cursor-pointer items-center justify-center gap-2 rounded-[9px] border border-primary bg-primary px-4 text-xs font-bold text-surface transition-colors duration-160 hover:not-disabled:border-primary-hover hover:not-disabled:bg-primary-hover disabled:cursor-wait disabled:opacity-65" disabled={submitting} type="submit">
              {submitting ? <><LoaderCircle aria-hidden="true" className="animate-spin motion-reduce:animate-none" size={17} /> Joining workspace…</> : <>Accept invitation <ArrowRight aria-hidden="true" size={17} /></>}
            </button>
          </form>
        )}
      </article>
      <p className="mt-4 mb-0 text-center text-[9px] text-muted">Invitation links are private and expire automatically.</p>
    </section>
  );
}

function UnavailableState() {
  return (
    <section className="w-[min(100%,580px)]">
      <Brand />
      <div className="grid min-h-75 place-items-center content-center rounded-[14px] border border-border bg-surface p-8 text-center max-[560px]:px-4.75 max-[560px]:py-6 [&_h1]:mt-3.75 [&_h1]:mb-0 [&_h1]:text-xl [&_p]:mt-1.75 [&_p]:mb-0 [&_p]:max-w-97.5 [&_p]:text-xs [&_p]:leading-[1.6] [&_p]:text-secondary">
        <span className="grid size-12 place-items-center rounded-xl bg-danger-soft text-danger"><AlertCircle aria-hidden="true" size={23} /></span>
        <h1>Invitation unavailable</h1>
        <p>This link may have expired, been cancelled, already been used, or been replaced by a newer link.</p>
      </div>
    </section>
  );
}

function Brand() {
  return <div className="mb-6 flex items-center justify-center gap-2.5 text-xl font-extrabold tracking-[-.04em] text-foreground"><span className="grid size-8.75 place-items-center rounded-[9px] bg-primary text-surface"><ScanSearch aria-hidden="true" size={20} /></span>Sift</div>;
}

function Notice({ children, icon, title }: { children: ReactNode; icon: ReactNode; title: string }) {
  return <div className="flex items-start gap-2.5 rounded-[9px] border border-border bg-elevated p-3 [&>svg]:mt-px [&>svg]:shrink-0 [&>svg]:text-primary [&_h2]:m-0 [&_h2]:text-xs [&_p]:mt-1 [&_p]:mb-0 [&_p]:text-[10px] [&_p]:leading-[1.55] [&_p]:text-secondary">{icon}<div><h2>{title}</h2><p>{children}</p></div></div>;
}

function Field({ autoComplete, error, id, label, onChange, type, value }: {
  autoComplete: string;
  error?: string;
  id: string;
  label: string;
  onChange: (value: string) => void;
  type: "password" | "text";
  value: string;
}) {
  return (
    <label className="grid gap-1.75 text-[11px] font-bold text-foreground" htmlFor={id}>
      <span>{label}</span>
      <input className="min-h-10.75 w-full rounded-[9px] border border-border bg-background px-3 text-[13px] text-foreground outline-none focus:border-primary focus:shadow-[0_0_0_3px_var(--focus-ring)] aria-invalid:border-danger" aria-describedby={error ? `${id}-error` : undefined} aria-invalid={error ? "true" : undefined} autoComplete={autoComplete} id={id} onChange={(event) => onChange(event.target.value)} required type={type} value={value} />
      {error && <small className="text-[10px] font-medium leading-[1.45] text-danger" id={`${id}-error`} role="alert">{error}</small>}
    </label>
  );
}

function capitalize(value: string): string {
  return value.charAt(0).toUpperCase() + value.slice(1);
}

function formatDateTime(value: string): string {
  return `${new Intl.DateTimeFormat("en", { day: "2-digit", month: "short", year: "numeric", hour: "2-digit", minute: "2-digit", timeZone: "UTC" }).format(new Date(value))} UTC`;
}
