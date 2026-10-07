"use client";

import { ArrowRight, LoaderCircle } from "lucide-react";
import { useRouter } from "next/navigation";
import { useState } from "react";
import { AuthApiError, enterDemo } from "@/lib/auth";

export function TryDemoButton() {
  const router = useRouter();
  const [submitting, setSubmitting] = useState(false);
  const [error, setError] = useState<string | null>(null);

  async function tryDemo() {
    setSubmitting(true);
    setError(null);

    try {
      await enterDemo();
      router.replace("/overview");
      router.refresh();
    } catch (requestError) {
      setError(requestError instanceof AuthApiError
        ? requestError.message
        : "Sift could not reach the API. Check your connection and try again.");
      setSubmitting(false);
    }
  }

  return (
    <div className="relative flex min-w-0 flex-col items-start">
      <button
        aria-busy={submitting}
        className="inline-flex min-h-10.5 cursor-pointer items-center justify-center gap-2 rounded-[9px] border border-primary bg-primary px-4 text-xs font-bold text-surface transition-[color,border-color,background,opacity] duration-160 hover:not-disabled:border-primary-hover hover:not-disabled:bg-primary-hover disabled:cursor-wait disabled:opacity-66"
        disabled={submitting}
        onClick={tryDemo}
        type="button"
      >
        {submitting
          ? <><LoaderCircle aria-hidden="true" className="animate-spin motion-reduce:animate-none" size={17} /> Entering Demo…</>
          : <>Try Demo <ArrowRight aria-hidden="true" size={17} /></>}
      </button>
      {error && <p className="mt-2 mb-0 max-w-85 text-[11px] leading-[1.45] text-danger" role="alert">{error}</p>}
    </div>
  );
}
