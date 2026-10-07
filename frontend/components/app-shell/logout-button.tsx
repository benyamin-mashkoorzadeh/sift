"use client";

import { LogOut } from "lucide-react";
import { useRouter } from "next/navigation";
import { useState } from "react";
import { logout } from "@/lib/auth";

export function LogoutButton() {
  const router = useRouter();
  const [submitting, setSubmitting] = useState(false);
  const [error, setError] = useState<string | null>(null);

  async function handleLogout() {
    setSubmitting(true);
    setError(null);

    try {
      await logout();
      router.replace("/login");
      router.refresh();
    } catch {
      setError("Logout failed. Please try again.");
      setSubmitting(false);
    }
  }

  return (
    <div className="px-2">
      <button className="flex min-h-9 w-full cursor-pointer items-center gap-2.25 rounded-lg border border-transparent bg-transparent px-2.25 text-xs font-semibold text-secondary transition-colors duration-160 hover:not-disabled:border-border hover:not-disabled:bg-elevated hover:not-disabled:text-primary disabled:cursor-wait disabled:opacity-62 motion-reduce:transition-none" disabled={submitting} onClick={handleLogout} type="button">
        <LogOut aria-hidden="true" size={16} />
        {submitting ? "Logging out…" : "Log out"}
      </button>
      {error && <p className="mx-2.25 mt-1.5 mb-0 text-[10px] leading-[1.4] text-danger" role="alert">{error}</p>}
    </div>
  );
}
