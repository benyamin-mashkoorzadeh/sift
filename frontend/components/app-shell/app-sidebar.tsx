"use client";

import {
  BookOpenText,
  Bot,
  LayoutDashboard,
  Menu,
  MessagesSquare,
  ScanSearch,
  Settings,
  ShieldCheck,
  X,
  Users,
  type LucideIcon,
} from "lucide-react";
import Link from "next/link";
import { usePathname } from "next/navigation";
import { useState } from "react";
import { hasPermission } from "@/lib/permissions";
import type { AuthenticatedContext, WorkspacePermission } from "@/types/auth";
import { LogoutButton } from "./logout-button";

interface NavigationItem {
  href: string;
  label: string;
  icon: LucideIcon;
  permission: WorkspacePermission;
}

const navigation: NavigationItem[] = [
  { href: "/overview", label: "Overview", icon: LayoutDashboard, permission: "overview.view" },
  { href: "/knowledge", label: "Knowledge", icon: BookOpenText, permission: "knowledge.view" },
  { href: "/assistant", label: "Assistant", icon: Bot, permission: "assistant.use" },
  { href: "/conversations", label: "Conversations", icon: MessagesSquare, permission: "conversations.view" },
  { href: "/review", label: "Review", icon: ShieldCheck, permission: "review.view" },
  { href: "/settings", label: "Settings", icon: Settings, permission: "settings.view" },
  { href: "/team", label: "Team", icon: Users, permission: "team.view" },
];

export function AppSidebar({ context }: { context: AuthenticatedContext }) {
  const pathname = usePathname();
  const [isOpen, setIsOpen] = useState(false);
  const visibleNavigation = navigation.filter((item) => hasPermission(context, item.permission));

  return (
    <>
      <header className="fixed inset-x-0 top-0 z-15 hidden h-17 items-center justify-between border-b border-border bg-surface px-5 max-[860px]:flex">
        <Brand />
        <div className="flex items-center gap-2.25">
          {context.access_mode === "demo" && <span className="rounded-[7px] border border-primary-soft bg-primary-soft px-2 py-1.25 text-[10px] font-extrabold text-primary">Guest Demo</span>}
          <button aria-controls="sift-navigation" aria-expanded={isOpen} aria-label="Open navigation" className="hidden size-9.5 cursor-pointer items-center justify-center rounded-[9px] border border-border bg-elevated p-0 text-secondary max-[860px]:flex" onClick={() => setIsOpen(true)} type="button">
            <Menu aria-hidden="true" size={21} />
          </button>
        </div>
      </header>
      {isOpen && <button aria-label="Close navigation" className="fixed inset-0 z-20 hidden size-full border-0 bg-[rgba(33,29,30,.42)] p-0 max-[860px]:block" onClick={() => setIsOpen(false)} type="button" />}
      <aside className={`fixed inset-y-0 left-0 z-30 flex w-(--sidebar-width) flex-col border-r border-border bg-surface px-4.5 pt-7 pb-5 max-[860px]:w-[min(290px,86vw)] max-[860px]:-translate-x-full max-[860px]:shadow-[20px_0_45px_rgba(54,43,46,.14)] max-[860px]:transition-transform max-[860px]:duration-190 motion-reduce:transition-none ${isOpen ? "max-[860px]:translate-x-0" : ""}`} id="sift-navigation">
        <div className="flex items-center justify-between px-2">
          <Brand />
          <button aria-label="Close navigation" className="hidden size-9.5 cursor-pointer items-center justify-center rounded-[9px] border border-border bg-elevated p-0 text-secondary max-[860px]:flex" onClick={() => setIsOpen(false)} type="button">
            <X aria-hidden="true" size={20} />
          </button>
        </div>
        <nav aria-label="Primary" className="mt-11.25 flex flex-1 flex-col gap-1.25">
          <p className="mx-2.5 mt-0 mb-2.5 text-[11px] font-bold tracking-[.13em] text-secondary uppercase">Workspace</p>
          {visibleNavigation.map((item) => {
            const Icon = item.icon;
            const active = pathname === item.href || pathname.startsWith(`${item.href}/`);
            return (
              <Link aria-current={active ? "page" : undefined} className={`relative flex min-h-10.75 items-center gap-3 rounded-[9px] border px-3 text-sm font-semibold transition-colors duration-160 motion-reduce:transition-none ${active ? "border-primary-soft bg-primary-soft text-primary before:absolute before:-left-px before:h-4.5 before:w-0.5 before:rounded-r-sm before:bg-primary before:content-['']" : "border-transparent text-secondary hover:bg-elevated hover:text-foreground"}`} href={item.href} key={item.href} onClick={() => setIsOpen(false)}>
                <Icon aria-hidden="true" size={18} strokeWidth={1.8} />
                <span>{item.label}</span>
              </Link>
            );
          })}
        </nav>
        <div className="flex items-center gap-2.75 border-t border-border px-2 pt-3.5 pb-2.5">
          <span aria-hidden="true" className="grid size-8.5 shrink-0 place-items-center rounded-[9px] border border-primary-soft bg-primary-soft text-[11px] font-extrabold tracking-[.04em] text-primary">{initials(context.user.name)}</span>
          <div className="min-w-0 [&_p]:m-0 [&_p]:block [&_p]:overflow-hidden [&_p]:text-ellipsis [&_p]:whitespace-nowrap [&_p]:text-xs [&_p]:font-bold [&_p]:text-foreground [&_span]:mt-0.75 [&_span]:block [&_span]:overflow-hidden [&_span]:text-ellipsis [&_span]:whitespace-nowrap [&_span]:text-[10px] [&_span]:text-secondary">
            <p title={context.user.name}>{context.user.name}</p>
            <span title={context.workspace.name}>
              {context.workspace.name} · {context.access_mode === "demo" ? "Guest access" : formatRole(context.workspace.role)}
            </span>
          </div>
        </div>
        <LogoutButton />
      </aside>
    </>
  );
}

function Brand() {
  return (
    <Link aria-label="Sift overview" className="inline-flex items-center gap-2.75 text-xl font-bold tracking-[-.04em] text-foreground" href="/overview">
      <span className="grid size-8.5 place-items-center rounded-[9px] bg-primary text-surface"><ScanSearch aria-hidden="true" size={20} strokeWidth={2} /></span>
      <span>Sift</span>
    </Link>
  );
}

function initials(name: string): string {
  return name
    .trim()
    .split(/\s+/)
    .slice(0, 2)
    .map((part) => part.charAt(0).toUpperCase())
    .join("") || "S";
}

function formatRole(role: AuthenticatedContext["workspace"]["role"]): string {
  return role.charAt(0).toUpperCase() + role.slice(1);
}
