"use client";

import { useRouter } from "next/navigation";
import { useEffect, useTransition } from "react";

interface DocumentStatusRefreshProps {
  hasProcessingDocuments: boolean;
}

const REFRESH_INTERVAL_MS = 4_000;

export function DocumentStatusRefresh({
  hasProcessingDocuments,
}: DocumentStatusRefreshProps) {
  const router = useRouter();
  const [isPending, startTransition] = useTransition();

  useEffect(() => {
    if (!hasProcessingDocuments) return;

    function refresh() {
      if (document.hidden || isPending) return;

      startTransition(() => {
        router.refresh();
      });
    }

    const interval = window.setInterval(refresh, REFRESH_INTERVAL_MS);

    function handleVisibilityChange() {
      if (!document.hidden) refresh();
    }

    document.addEventListener("visibilitychange", handleVisibilityChange);

    return () => {
      window.clearInterval(interval);
      document.removeEventListener("visibilitychange", handleVisibilityChange);
    };
  }, [hasProcessingDocuments, isPending, router]);

  return null;
}
