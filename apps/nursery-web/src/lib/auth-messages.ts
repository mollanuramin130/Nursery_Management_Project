/** Map API/auth failures to safe, user-facing copy (never raw Axios/stack). */

export class AuthApiError extends Error {
  readonly statusCode?: number;
  readonly retryAfterSeconds?: number;

  constructor(
    message: string,
    opts?: { statusCode?: number; retryAfterSeconds?: number },
  ) {
    super(message);
    this.name = "AuthApiError";
    this.statusCode = opts?.statusCode;
    this.retryAfterSeconds = opts?.retryAfterSeconds;
  }
}

/** Parse Laravel/HTTP Retry-After (seconds or HTTP-date) into whole seconds. */
export function parseRetryAfterSeconds(
  value: string | number | null | undefined,
): number | undefined {
  if (value == null || value === "") return undefined;
  if (typeof value === "number" && Number.isFinite(value)) {
    return Math.max(1, Math.ceil(value));
  }
  const raw = String(value).trim();
  if (/^\d+$/.test(raw)) {
    return Math.max(1, Number.parseInt(raw, 10));
  }
  const when = Date.parse(raw);
  if (!Number.isNaN(when)) {
    const secs = Math.ceil((when - Date.now()) / 1000);
    return secs > 0 ? secs : undefined;
  }
  return undefined;
}

export function getAuthRetryAfterSeconds(error: unknown): number | undefined {
  if (error instanceof AuthApiError && error.retryAfterSeconds != null) {
    return Math.max(1, Math.ceil(error.retryAfterSeconds));
  }
  return undefined;
}

export function isAuthRateLimited(error: unknown): boolean {
  if (error instanceof AuthApiError && error.statusCode === 429) return true;
  const text =
    error instanceof Error
      ? error.message
      : typeof error === "string"
        ? error
        : "";
  const lower = text.toLowerCase();
  return (
    lower.includes("too many") ||
    lower.includes("rate_limited") ||
    lower.includes("wait about a minute") ||
    lower.includes("too quickly")
  );
}

/** mm:ss for countdown UI (e.g. 65 → "1:05"). */
export function formatAuthCountdown(totalSeconds: number): string {
  const s = Math.max(0, Math.ceil(totalSeconds));
  const m = Math.floor(s / 60);
  const rem = s % 60;
  return `${m}:${rem.toString().padStart(2, "0")}`;
}

export function authRateLimitMessage(seconds: number): string {
  const s = Math.max(1, Math.ceil(seconds));
  return `Too many attempts. Please wait ${formatAuthCountdown(s)}, then try again.`;
}

export function authUserMessage(
  error: unknown,
  fallback = "Something went wrong. Please try again.",
): string {
  if (error instanceof AuthApiError && error.statusCode === 429) {
    const secs = error.retryAfterSeconds ?? 60;
    return authRateLimitMessage(secs);
  }

  const raw =
    error instanceof Error
      ? error.message
      : typeof error === "string"
        ? error
        : "";
  const text = raw.trim();
  const lower = text.toLowerCase();

  if (
    !text ||
    lower.includes("network error") ||
    lower.includes("unable to reach") ||
    lower.includes("cannot reach") ||
    lower.includes("econnrefused") ||
    lower.includes("failed to fetch")
  ) {
    return "Unable to connect to the server. Please try again.";
  }

  if (lower.includes("timed out") || lower.includes("timeout")) {
    return "The request timed out. Please try again.";
  }

  if (
    lower.includes("account is blocked") ||
    lower.includes("auth_account_blocked") ||
    lower.includes("currently unavailable")
  ) {
    return "Your account is currently unavailable. Please contact support.";
  }

  if (
    lower.includes("invalid email or password") ||
    lower.includes("invalid credentials") ||
    lower.includes("auth_invalid_credentials") ||
    lower === "unauthenticated"
  ) {
    return "Invalid email or password.";
  }

  if (
    lower.includes("too many") ||
    lower.includes("rate_limited") ||
    lower.includes("wait about a minute") ||
    lower.includes("too quickly")
  ) {
    return "Too many attempts. Please wait about a minute, then try again.";
  }

  if (lower.includes("does not have admin") || lower.includes("staff")) {
    return text;
  }

  if (lower.includes("sqlstate") || lower.includes("exception") || lower.includes("stack")) {
    return fallback;
  }

  if (text.length > 160) return fallback;
  return text || fallback;
}
