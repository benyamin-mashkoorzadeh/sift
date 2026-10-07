import type { Metadata } from "next";
import type { ReactNode } from "react";

export const metadata: Metadata = {
  title: "Customer support · Sift",
  robots: { index: false, follow: false },
};

export default function WidgetLayout({ children }: { children: ReactNode }) {
  return <div className="widget-frame-root h-dvh w-full">{children}</div>;
}
