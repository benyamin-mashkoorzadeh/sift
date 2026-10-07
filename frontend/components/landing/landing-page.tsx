import {
  BookOpenText,
  CheckCircle2,
  ScanSearch,
  ShieldCheck,
} from "lucide-react";
import Link from "next/link";
import { LandingDemoWidget } from "./landing-demo-widget";
import { TryDemoButton } from "./try-demo-button";

export function LandingPage() {
  return (
    <div className="flex min-h-dvh flex-col bg-background">
      <a className="fixed top-2.5 left-3 z-100 -translate-y-[160%] rounded-lg bg-primary px-3 py-2.25 text-xs font-bold text-surface transition-transform duration-150 focus:translate-y-0 motion-reduce:transition-none" href="#main-content">Skip to content</a>

      <header className="sticky top-0 z-40 shrink-0 border-b border-[color-mix(in_srgb,var(--border)_78%,transparent)] bg-background">
        <div className="mx-auto flex min-h-17.5 w-[min(calc(100%_-_48px),1160px)] items-center justify-between gap-7 max-[620px]:min-h-16 max-[620px]:w-[min(calc(100%_-_30px),1160px)] max-[620px]:gap-3">
          <Brand />
          <div className="relative flex items-center gap-2.25 max-[620px]:gap-1.75">
            <Link className="text-xs font-semibold text-secondary transition-colors duration-160 hover:text-primary max-[620px]:text-[11px]" href="/login">Log in</Link>
            <Link className="inline-flex min-h-10.5 items-center justify-center gap-2 rounded-[9px] border border-border bg-surface px-4 text-xs font-bold text-foreground transition-colors duration-160 hover:border-primary hover:text-primary max-[620px]:hidden" href="/register">Create Account</Link>
          </div>
        </div>
      </header>

      <main className="flex flex-1" id="main-content">
        <section className="mx-auto grid w-[min(calc(100%_-_48px),1160px)] grid-cols-[minmax(0,1.03fr)_minmax(380px,.97fr)] items-center gap-[clamp(44px,7vw,88px)] py-[clamp(44px,7vh,68px)] max-[900px]:grid-cols-1 max-[900px]:gap-13 max-[900px]:py-16 max-[900px]:pb-20.5 max-[620px]:w-[min(calc(100%_-_30px),1160px)] max-[620px]:gap-10.5 max-[620px]:py-12 max-[620px]:pb-17.5">
          <div className="max-w-156.25">
            <p className="mt-0 mb-3.25 text-[11px] font-extrabold tracking-[.13em] text-primary uppercase">Company knowledge, made answerable</p>
            <h1 className="m-0 max-w-162.5 text-[clamp(41px,5vw,61px)] font-[720] leading-[1.04] tracking-[-.055em] max-[620px]:text-[clamp(38px,12vw,50px)]">Support answers grounded in the knowledge your company trusts.</h1>
            <p className="mt-5.5 mb-0 max-w-152.5 text-[clamp(15px,1.4vw,17px)] leading-[1.7] text-secondary">
              Turn company PDFs into clear, cited answers for teams and customers. When the evidence is not enough, Sift asks for human review instead of guessing.
            </p>
            <div className="mt-7 flex items-start gap-2.5 max-[620px]:flex-col max-[620px]:items-stretch [&_button]:min-h-11.5 [&_button]:px-4.75 [&_button]:text-[13px] max-[620px]:[&>*]:w-full max-[620px]:[&_button]:w-full">
              <TryDemoButton />
              <Link className="inline-flex min-h-11.5 items-center justify-center gap-2 rounded-[9px] border border-border bg-surface px-4.75 text-[13px] font-bold text-foreground transition-colors duration-160 hover:border-primary hover:text-primary" href="/register">Create Account</Link>
            </div>
            <p className="mt-3.25 mb-0 text-[11px] text-muted">Explore a prepared workspace. No account setup required.</p>
          </div>
          <AssistantVignette />
        </section>
      </main>

      <footer className="shrink-0 border-t border-border bg-surface">
        <div className="mx-auto flex min-h-13 w-[min(calc(100%_-_48px),1160px)] items-center justify-between gap-6 pr-20 text-[10px] text-muted max-[620px]:min-h-17 max-[620px]:w-[min(calc(100%_-_30px),1160px)] max-[620px]:flex-col max-[620px]:items-start max-[620px]:justify-center max-[620px]:gap-1.5 [&>p]:m-0">
          <p>© {new Date().getFullYear()} Sift · Grounded support knowledge.</p>
        </div>
      </footer>

      <LandingDemoWidget />
    </div>
  );
}

function Brand() {
  return (
    <Link aria-label="Sift home" className="inline-flex shrink-0 items-center gap-2.75 text-xl font-extrabold tracking-[-.04em] text-foreground max-[620px]:gap-2 max-[620px]:text-lg" href="/">
      <span className="grid size-8.5 place-items-center rounded-[9px] bg-primary text-surface max-[620px]:size-7.75"><ScanSearch aria-hidden="true" size={20} strokeWidth={2} /></span>
      <span>Sift</span>
    </Link>
  );
}

function AssistantVignette() {
  return (
    <figure aria-label="Illustration of a grounded Sift answer" className="relative m-0 rounded-[15px] border border-border bg-surface p-4.5 shadow-[0_24px_65px_rgba(54,43,46,.1)] before:absolute before:-z-1 before:inset-[32px_-18px_-20px_34px] before:rounded-[15px] before:border before:border-border before:bg-elevated before:content-[''] max-[900px]:w-[min(100%,610px)] max-[620px]:p-3.25 max-[620px]:before:inset-[25px_-10px_-14px_20px]">
      <figcaption className="mt-0 mb-3.5 text-[9px] font-bold tracking-[.11em] text-muted uppercase">Illustrative Assistant response</figcaption>
      <div className="rounded-[11px] border border-border bg-background p-4.25 [&>span]:text-[9px] [&>span]:font-bold [&>span]:tracking-[.09em] [&>span]:text-muted [&>span]:uppercase [&>p]:mt-1.75 [&>p]:mb-0 [&>p]:text-[13px] [&>p]:font-semibold [&>p]:leading-[1.55] [&>p]:text-foreground">
        <span>Question</span>
        <p>How long do customers have to return an unused product?</p>
      </div>
      <div className="mt-2.75 rounded-[11px] border border-border bg-surface p-4.5 max-[620px]:p-3.75 [&>div:first-child]:flex [&>div:first-child]:items-center [&>div:first-child]:gap-2 [&>div:first-child]:text-[10px] [&>div:first-child]:tracking-[.05em] [&>div:first-child]:text-success [&>div:first-child]:uppercase [&>div:first-child>span]:grid [&>div:first-child>span]:size-6.75 [&>div:first-child>span]:place-items-center [&>div:first-child>span]:rounded-[7px] [&>div:first-child>span]:bg-success-soft [&>p]:my-4 [&>p]:text-[15px] [&>p]:font-medium [&>p]:leading-[1.65] [&>p]:text-foreground">
        <div><span><CheckCircle2 aria-hidden="true" size={15} /></span><strong>Grounded answer</strong></div>
        <p>Unused products can be returned within 30 days of delivery.</p>
        <div className="inline-flex w-full items-center gap-1.75 rounded-lg border border-border bg-elevated p-2.5 text-[10px] text-secondary [&_span]:flex-1 [&_span]:overflow-hidden [&_span]:text-ellipsis [&_span]:whitespace-nowrap [&_b]:font-mono [&_b]:text-[9px] [&_b]:text-primary">
          <BookOpenText aria-hidden="true" size={14} />
          <span>Returns Policy.pdf</span>
          <b>p.1</b>
        </div>
      </div>
      <div className="mt-2.75 flex items-center gap-2.5 rounded-[10px] border border-[#ddc7a8] bg-warning-soft px-3.5 py-3 [&>span]:grid [&>span]:size-7 [&>span]:shrink-0 [&>span]:place-items-center [&>span]:rounded-[7px] [&>span]:bg-surface [&>span]:text-warning [&_strong]:text-[10px] [&_strong]:text-warning [&_strong]:uppercase [&_p]:mt-0.75 [&_p]:mb-0 [&_p]:text-[10px] [&_p]:leading-[1.45] [&_p]:text-secondary">
        <span><ShieldCheck aria-hidden="true" size={15} /></span>
        <div><strong>Needs Review</strong><p>Not enough supporting knowledge to answer safely.</p></div>
      </div>
    </figure>
  );
}
