"use client";

import { AlertCircle, ArrowRight, Eye, LoaderCircle } from "lucide-react";
import Link from "next/link";
import { useRouter } from "next/navigation";
import { FormEvent, useState } from "react";
import { AuthApiError, enterDemo, login } from "@/lib/auth";

export function LoginForm() {
  const router = useRouter();
  const [email, setEmail] = useState("");
  const [password, setPassword] = useState("");
  const [errors, setErrors] = useState<Record<string, string[]>>({});
  const [requestError, setRequestError] = useState<string | null>(null);
  const [submitting, setSubmitting] = useState<"login" | "demo" | null>(null);

  async function submit(event: FormEvent<HTMLFormElement>) {
    event.preventDefault();
    setErrors({});
    setRequestError(null);
    setSubmitting("login");

    try {
      await login(email, password);
      router.replace("/overview");
      router.refresh();
    } catch (error) {
      if (error instanceof AuthApiError) {
        setErrors(error.errors);
        if (Object.keys(error.errors).length === 0) setRequestError(error.message);
      } else {
        setRequestError("Sift could not reach the API. Check your connection and try again.");
      }
      setSubmitting(null);
    }
  }

  async function tryDemo() {
    setErrors({});
    setRequestError(null);
    setSubmitting("demo");

    try {
      await enterDemo();
      router.replace("/overview");
      router.refresh();
    } catch (error) {
      setRequestError(error instanceof AuthApiError
        ? error.message
        : "Sift could not reach the API. Check your connection and try again.");
      setSubmitting(null);
    }
  }

  return (
    <div className="rounded-[14px] border border-border bg-surface p-8.5 max-[560px]:px-5 max-[560px]:py-6.5">
      <header className="mb-7 [&_h1]:m-0 [&_h1]:text-[29px] [&_h1]:leading-[1.2] [&_h1]:tracking-[-.04em] [&_p]:mt-0 [&_p]:mb-2.25 [&_p]:text-[11px] [&_p]:font-extrabold [&_p]:tracking-[.12em] [&_p]:text-primary [&_p]:uppercase [&_span]:mt-2.5 [&_span]:block [&_span]:text-sm [&_span]:leading-[1.6] [&_span]:text-secondary">
        <p>Welcome back</p>
        <h1>Log in to Sift</h1>
        <span>Continue to your company knowledge workspace.</span>
      </header>

      <form aria-busy={submitting !== null} className="grid gap-4.25" onSubmit={submit}>
        <Field
          autoComplete="email"
          error={errors.email?.[0]}
          id="email"
          label="Email"
          onChange={setEmail}
          type="email"
          value={email}
        />
        <Field
          autoComplete="current-password"
          error={errors.password?.[0]}
          id="password"
          label="Password"
          onChange={setPassword}
          type="password"
          value={password}
        />

        {requestError && (
          <p className="m-0 flex items-start gap-2 rounded-[9px] border border-[color-mix(in_srgb,var(--danger)_24%,var(--border))] bg-danger-soft px-3 py-2.75 text-xs leading-[1.5] text-danger [&_svg]:mt-px [&_svg]:shrink-0" role="alert">
            <AlertCircle aria-hidden="true" size={16} /> {requestError}
          </p>
        )}

        <button className="flex min-h-11 cursor-pointer items-center justify-center gap-2.25 rounded-[9px] border border-primary bg-primary px-4.5 text-[13px] font-bold text-surface transition-colors duration-160 hover:not-disabled:border-primary-hover hover:not-disabled:bg-primary-hover disabled:cursor-wait disabled:opacity-68" disabled={submitting !== null} type="submit">
          {submitting === "login"
            ? <><LoaderCircle aria-hidden="true" className="animate-spin motion-reduce:animate-none" size={17} /> Logging in…</>
            : <>Log in <ArrowRight aria-hidden="true" size={17} /></>}
        </button>
      </form>

      <div className="mt-5.5 grid gap-2.5">
        <div className="flex items-center gap-3 text-[10px] tracking-[.08em] text-muted uppercase before:h-px before:flex-1 before:bg-border before:content-[''] after:h-px after:flex-1 after:bg-border after:content-['']"><span>or explore first</span></div>
        <button className="flex min-h-11 cursor-pointer items-center justify-center gap-2.25 rounded-[9px] border border-primary-soft bg-primary-soft px-4.5 text-[13px] font-bold text-primary transition-colors duration-160 hover:not-disabled:border-primary hover:not-disabled:bg-primary hover:not-disabled:text-surface disabled:cursor-wait disabled:opacity-68" disabled={submitting !== null} onClick={tryDemo} type="button">
          {submitting === "demo"
            ? <><LoaderCircle aria-hidden="true" className="animate-spin motion-reduce:animate-none" size={17} /> Entering Guest Demo…</>
            : <><Eye aria-hidden="true" size={17} /> Try Demo</>}
        </button>
        <p className="m-0 text-center text-[11px] leading-[1.5] text-muted">Explore a prepared workspace and test Sift without creating an account.</p>
      </div>

      <p className="mt-5.5 mb-0 text-center text-xs text-secondary [&_a]:font-bold [&_a]:text-primary hover:[&_a]:text-primary-hover">New to Sift? <Link href="/register">Create a company</Link></p>
    </div>
  );
}

function Field({
  autoComplete,
  error,
  id,
  label,
  onChange,
  type,
  value,
}: {
  autoComplete: string;
  error?: string;
  id: string;
  label: string;
  onChange: (value: string) => void;
  type: "email" | "password";
  value: string;
}) {
  return (
    <div className="min-w-0">
      <label className="mb-1.75 block text-xs font-bold text-foreground" htmlFor={id}>{label}</label>
      <input
        className="h-11 w-full rounded-[9px] border border-border bg-background px-3 text-sm text-foreground outline-none transition-[border-color,box-shadow,background] duration-160 hover:border-muted focus:border-primary focus:bg-surface focus:shadow-[0_0_0_3px_var(--focus-ring)] aria-invalid:border-danger"
        aria-describedby={error ? `${id}-error` : undefined}
        aria-invalid={error ? "true" : undefined}
        autoComplete={autoComplete}
        id={id}
        onChange={(event) => onChange(event.target.value)}
        required
        type={type}
        value={value}
      />
      {error && <p className="mt-1.75 mb-0 text-[11px] leading-[1.45] text-danger" id={`${id}-error`} role="alert">{error}</p>}
    </div>
  );
}
