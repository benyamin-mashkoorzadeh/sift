export default function TeamLoading() {
  return (
    <section aria-busy="true" aria-label="Loading Team" className="mx-auto w-full max-w-280">
      <div className="h-22.5 w-75 animate-pulse rounded-[10px] bg-elevated motion-reduce:animate-none" />
      <div className="mt-5 h-60 animate-pulse rounded-[10px] bg-elevated motion-reduce:animate-none" />
      <div className="mt-5 h-60 animate-pulse rounded-[10px] bg-elevated motion-reduce:animate-none" />
    </section>
  );
}
