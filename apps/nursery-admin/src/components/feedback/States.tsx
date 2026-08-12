import { Button } from "@/components/ui/Button";

export function EmptyState({
  title,
  description,
  actionLabel,
  onAction,
}: {
  title: string;
  description?: string;
  actionLabel?: string;
  onAction?: () => void;
}) {
  return (
    <div className="flex flex-col items-center justify-center gap-2 rounded-[var(--admin-radius-lg)] border border-dashed border-[var(--admin-border)] bg-white px-6 py-14 text-center">
      <h3 className="text-base font-semibold text-[var(--admin-ink)]">{title}</h3>
      {description ? (
        <p className="max-w-md text-sm text-[var(--admin-muted)]">{description}</p>
      ) : null}
      {actionLabel && onAction ? (
        <Button className="mt-2" onClick={onAction}>
          {actionLabel}
        </Button>
      ) : null}
    </div>
  );
}

export function ErrorState({
  title = "Something went wrong",
  message,
  onRetry,
}: {
  title?: string;
  message?: string;
  onRetry?: () => void;
}) {
  return (
    <div className="flex flex-col items-start gap-2 rounded-[var(--admin-radius-lg)] border border-[var(--admin-danger)]/30 bg-[var(--admin-danger-soft)] px-5 py-4">
      <h3 className="text-sm font-semibold text-[var(--admin-danger)]">{title}</h3>
      {message ? <p className="text-sm text-[var(--admin-ink-soft)]">{message}</p> : null}
      {onRetry ? (
        <Button variant="secondary" size="sm" onClick={onRetry}>
          Retry
        </Button>
      ) : null}
    </div>
  );
}

export function LoadingBlock({ label = "Loading…" }: { label?: string }) {
  return (
    <div className="flex items-center gap-3 rounded-[var(--admin-radius-lg)] border border-[var(--admin-border)] bg-white px-5 py-8 text-sm text-[var(--admin-muted)]">
      <span className="inline-block h-4 w-4 animate-spin rounded-full border-2 border-[var(--admin-border-strong)] border-t-[var(--admin-primary)]" />
      {label}
    </div>
  );
}

export function PageHeader({
  title,
  description,
  actions,
}: {
  title: string;
  description?: string;
  actions?: React.ReactNode;
}) {
  return (
    <div className="mb-5 flex flex-wrap items-start justify-between gap-3">
      <div>
        <h1 className="text-xl font-semibold tracking-tight text-[var(--admin-ink)]">{title}</h1>
        {description ? (
          <p className="mt-1 text-sm text-[var(--admin-muted)]">{description}</p>
        ) : null}
      </div>
      {actions ? <div className="flex flex-wrap items-center gap-2">{actions}</div> : null}
    </div>
  );
}
