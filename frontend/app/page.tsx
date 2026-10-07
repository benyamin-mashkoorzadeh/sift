import type { Metadata } from "next";
import { redirect } from "next/navigation";
import { LandingPage } from "@/components/landing/landing-page";
import { getAuthenticatedContext } from "@/lib/auth.server";

export const metadata: Metadata = {
  title: "Sift · Grounded support knowledge",
  description:
    "Turn company knowledge into cited support answers for teams and customers, with human review when the evidence is insufficient.",
};

export default async function Home() {
  const context = await getAuthenticatedContext();

  if (context) redirect("/overview");

  return <LandingPage />;
}
