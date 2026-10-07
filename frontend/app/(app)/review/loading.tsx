export default function ReviewLoading() {
  return (
    <section aria-busy="true" aria-label="Loading review items" className="mx-auto w-full max-w-245">
      <header className="max-w-170">
        <div className="h-2.5 w-26.25 animate-pulse rounded-[7px] bg-elevated motion-reduce:animate-none" />
        <div className="mt-3.5 h-10.5 w-45 animate-pulse rounded-[7px] bg-elevated motion-reduce:animate-none" />
        <div className="mt-4.25 h-4.25 w-[min(560px,78vw)] animate-pulse rounded-[7px] bg-elevated motion-reduce:animate-none" />
      </header>
      <div className="mt-8.5 h-11 w-51.25 animate-pulse rounded-[7px] bg-elevated motion-reduce:animate-none" />
      <div className="mt-9 mb-3.75 h-4.25 w-45 animate-pulse rounded-[7px] bg-elevated motion-reduce:animate-none" />
      {Array.from({ length: 3 }, (_, index) => <div className="mb-3 h-46.25 rounded-[13px] border border-border bg-surface" key={index} />)}
    </section>
  );
}
