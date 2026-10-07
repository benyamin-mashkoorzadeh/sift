export default function OverviewLoading() {
  return (
    <section aria-busy="true" aria-label="Loading workspace overview" className="mx-auto w-full max-w-280">
      <header className="max-w-175">
        <div className="h-2.5 w-31.5 animate-pulse rounded-[7px] bg-elevated motion-reduce:animate-none" />
        <div className="mt-3.5 h-10.5 w-49 animate-pulse rounded-[7px] bg-elevated motion-reduce:animate-none" />
        <div className="mt-4.25 h-4.25 w-[min(610px,78vw)] animate-pulse rounded-[7px] bg-elevated motion-reduce:animate-none" />
      </header>
      <div className="mt-8.5 grid grid-cols-3 gap-3 max-[900px]:grid-cols-2 max-[900px]:[&>*:last-child]:col-span-full max-[540px]:grid-cols-1 max-[540px]:[&>*:last-child]:col-auto">
        {Array.from({ length: 3 }, (_, index) => <div className="h-43.5 animate-pulse rounded-[13px] border border-border bg-surface motion-reduce:animate-none" key={index} />)}
      </div>
      <div className="mt-8.5 grid grid-cols-2 gap-3.5 max-[760px]:grid-cols-1">
        {Array.from({ length: 2 }, (_, index) => <div className="h-97.5 animate-pulse rounded-[13px] border border-border bg-surface motion-reduce:animate-none" key={index} />)}
      </div>
    </section>
  );
}
