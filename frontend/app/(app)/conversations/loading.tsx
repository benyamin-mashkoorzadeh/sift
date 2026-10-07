export default function ConversationsLoading() {
  return (
    <section aria-busy="true" aria-label="Loading Assistant history" className="mx-auto w-full max-w-245">
      <header className="max-w-170">
        <div className="h-2.5 w-31.25 animate-pulse rounded-[7px] bg-elevated motion-reduce:animate-none" />
        <div className="mt-3.5 h-10.5 w-57.5 animate-pulse rounded-[7px] bg-elevated motion-reduce:animate-none" />
        <div className="mt-4.25 h-4.25 w-[min(570px,78vw)] animate-pulse rounded-[7px] bg-elevated motion-reduce:animate-none" />
      </header>
      <div className="mt-8.5 h-11 w-77.5 animate-pulse rounded-[7px] bg-elevated motion-reduce:animate-none" />
      <div className="mt-9 mb-3.75 h-4.25 w-42.5 animate-pulse rounded-[7px] bg-elevated motion-reduce:animate-none" />
      {Array.from({ length: 4 }, (_, index) => <div className="mb-2.5 h-25.5 rounded-[13px] border border-border bg-surface" key={index} />)}
    </section>
  );
}
