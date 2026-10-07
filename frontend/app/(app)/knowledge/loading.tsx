export default function KnowledgeLoading() {
  return (
    <section aria-busy="true" aria-label="Loading documents" className="mx-auto w-[min(100%,1180px)]">
      <header className="flex items-end justify-between gap-7">
        <div>
          <div className="h-2.5 w-26.25 animate-pulse rounded-[7px] bg-elevated motion-reduce:animate-none" />
          <div className="mt-3.5 h-10.5 w-52.5 animate-pulse rounded-[7px] bg-elevated motion-reduce:animate-none" />
          <div className="mt-4.25 h-4.25 w-[min(540px,78vw)] animate-pulse rounded-[7px] bg-elevated motion-reduce:animate-none" />
        </div>
      </header>
      <div className="mt-12.75 mb-4 h-4.25 w-27.5 animate-pulse rounded-[7px] bg-elevated motion-reduce:animate-none" />
      <div className="overflow-hidden rounded-[13px] border border-border bg-surface px-4.5">
        {Array.from({ length: 5 }, (_, index) => <div className="h-17.5 border-b border-border bg-transparent last:border-b-0" key={index} />)}
      </div>
    </section>
  );
}
