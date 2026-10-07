export default function SettingsLoading() {
  return (
    <section aria-busy="true" aria-label="Loading workspace settings" className="mx-auto w-full max-w-230">
      <header className="max-w-165">
        <div className="h-2.5 w-33.5 animate-pulse rounded-[7px] bg-elevated motion-reduce:animate-none" />
        <div className="mt-3.5 h-10.5 w-45 animate-pulse rounded-[7px] bg-elevated motion-reduce:animate-none" />
        <div className="mt-4.25 h-4.25 w-[min(570px,78vw)] animate-pulse rounded-[7px] bg-elevated motion-reduce:animate-none" />
      </header>
      <div className="mt-8.5 grid gap-3.5">
        {Array.from({ length: 2 }, (_, index) => <div className="h-47.5 animate-pulse rounded-[13px] border border-border bg-surface motion-reduce:animate-none" key={index} />)}
      </div>
    </section>
  );
}
