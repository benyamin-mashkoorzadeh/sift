"use client";

import { AlertCircle, ArrowRight, Check, LoaderCircle } from "lucide-react";
import Link from "next/link";
import { useRouter } from "next/navigation";
import { FormEvent, useState } from "react";
import { AuthApiError, register } from "@/lib/auth";

const initialFields = {
  name: "",
  company_name: "",
  email: "",
  password: "",
  password_confirmation: "",
};

export function RegisterForm() {
  const router = useRouter();
  const [fields, setFields] = useState(initialFields);
  const [errors, setErrors] = useState<Record<string, string[]>>({});
  const [requestError, setRequestError] = useState<string | null>(null);
  const [submitting, setSubmitting] = useState(false);

  async function submit(event: FormEvent<HTMLFormElement>) {
    event.preventDefault();
    setErrors({});
    setRequestError(null);
    setSubmitting(true);

    try {
      await register(fields);
      router.replace("/overview");
      router.refresh();
    } catch (error) {
      if (error instanceof AuthApiError) {
        setErrors(error.errors);
        if (Object.keys(error.errors).length === 0) setRequestError(error.message);
      } else {
        setRequestError("Sift could not reach the API. Check your connection and try again.");
      }
      setSubmitting(false);
    }
  }

  function update(field: keyof typeof fields, value: string) {
    setFields((current) => ({ ...current, [field]: value }));
    setErrors((current) => ({ ...current, [field]: [] }));
  }

  return (
    <div className="rounded-[14px] border border-border bg-surface p-8.5 max-[560px]:px-5 max-[560px]:py-6.5">
      <header className="mb-7 [&_h1]:m-0 [&_h1]:text-[29px] [&_h1]:leading-[1.2] [&_h1]:tracking-[-.04em] [&_p]:mt-0 [&_p]:mb-2.25 [&_p]:text-[11px] [&_p]:font-extrabold [&_p]:tracking-[.12em] [&_p]:text-primary [&_p]:uppercase [&_span]:mt-2.5 [&_span]:block [&_span]:text-sm [&_span]:leading-[1.6] [&_span]:text-secondary">
        <p>Start a workspace</p>
        <h1>Create your company</h1>
        <span>Set up a private knowledge workspace for your support team.</span>
      </header>

      <form aria-busy={submitting} className="grid gap-4.25" onSubmit={submit}>
        <div className="grid grid-cols-2 gap-3.5 max-[560px]:grid-cols-1">
          <Field autoComplete="name" error={errors.name?.[0]} id="name" label="Your name" onChange={(value) => update("name", value)} value={fields.name} />
          <Field autoComplete="organization" error={errors.company_name?.[0]} id="company-name" label="Company name" onChange={(value) => update("company_name", value)} value={fields.company_name} />
        </div>
        <Field autoComplete="email" error={errors.email?.[0]} id="register-email" label="Email" onChange={(value) => update("email", value)} type="email" value={fields.email} />
        <Field autoComplete="new-password" error={errors.password?.[0]} id="register-password" label="Password" onChange={(value) => update("password", value)} type="password" value={fields.password} />
        <ul className="-mt-1.75 mb-0 grid list-none grid-cols-2 gap-x-2.5 gap-y-1.5 p-0 text-[10px] text-muted max-[560px]:grid-cols-1 [&_li]:flex [&_li]:items-center [&_li]:gap-1.25 [&_svg]:text-primary" aria-label="Password requirements">
          <li><Check aria-hidden="true" size={13} /> At least 8 characters</li>
          <li><Check aria-hidden="true" size={13} /> Uppercase and lowercase letters</li>
          <li><Check aria-hidden="true" size={13} /> At least one number</li>
        </ul>
        <Field autoComplete="new-password" id="password-confirmation" label="Confirm password" onChange={(value) => update("password_confirmation", value)} type="password" value={fields.password_confirmation} />

        {requestError && (
          <p className="m-0 flex items-start gap-2 rounded-[9px] border border-[color-mix(in_srgb,var(--danger)_24%,var(--border))] bg-danger-soft px-3 py-2.75 text-xs leading-[1.5] text-danger [&_svg]:mt-px [&_svg]:shrink-0" role="alert">
            <AlertCircle aria-hidden="true" size={16} /> {requestError}
          </p>
        )}

        <button className="flex min-h-11 cursor-pointer items-center justify-center gap-2.25 rounded-[9px] border border-primary bg-primary px-4.5 text-[13px] font-bold text-surface transition-colors duration-160 hover:not-disabled:border-primary-hover hover:not-disabled:bg-primary-hover disabled:cursor-wait disabled:opacity-68" disabled={submitting} type="submit">
          {submitting
            ? <><LoaderCircle aria-hidden="true" className="animate-spin motion-reduce:animate-none" size={17} /> Creating workspace…</>
            : <>Create company <ArrowRight aria-hidden="true" size={17} /></>}
        </button>
      </form>

      <p className="mt-5.5 mb-0 text-center text-xs text-secondary [&_a]:font-bold [&_a]:text-primary hover:[&_a]:text-primary-hover">Already have an account? <Link href="/login">Log in</Link></p>
    </div>
  );
}

function Field({
  autoComplete,
  error,
  id,
  label,
  onChange,
  type = "text",
  value,
}: {
  autoComplete: string;
  error?: string;
  id: string;
  label: string;
  onChange: (value: string) => void;
  type?: "email" | "password" | "text";
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
