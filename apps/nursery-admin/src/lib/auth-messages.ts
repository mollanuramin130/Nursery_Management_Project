/** Map API/auth failures to safe, user-facing copy (never raw Axios/stack). */
export function authUserMessage(error: unknown, fallback = "Something went wrong. Please try again."): string {
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
    lower.includes("wait about a minute")
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
