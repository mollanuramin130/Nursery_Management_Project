import type { NetworkKind } from "./network-errors";

export type ErrorCategory =
  | "temporaryNetwork"
  | "apiUnavailable"
  | "authentication"
  | "validation"
  | "payment"
  | "unexpected"
  | "rateLimited";

export function categoryForNetwork(
  kind: NetworkKind,
  statusCode?: number,
): ErrorCategory {
  switch (kind) {
    case "offline":
    case "connecting":
    case "reconnecting":
    case "apiTimeout":
      return "temporaryNetwork";
    case "apiUnavailable":
    case "serverError":
      return "apiUnavailable";
    case "authExpired":
      return "authentication";
    case "rateLimited":
      return "rateLimited";
    case "unknownError":
      if (statusCode === 404 || statusCode === 403 || statusCode === 409 || statusCode === 422) {
        return "validation";
      }
      return "unexpected";
    default:
      return "temporaryNetwork";
  }
}

export function shouldOfferSupport(
  category: ErrorCategory,
  opts: { retryCount?: number; userRequested?: boolean } = {},
): boolean {
  if (opts.userRequested) return true;
  if (category === "unexpected" || category === "payment") return true;
  if (category === "apiUnavailable") return (opts.retryCount ?? 0) >= 2;
  return false;
}

const records: Array<{
  category: ErrorCategory;
  reference: string;
  at: string;
  screen?: string;
  statusCode?: number;
}> = [];

export function recordClientError(input: {
  category: ErrorCategory;
  reference: string;
  screen?: string;
  statusCode?: number;
}): void {
  records.push({ ...input, at: new Date().toISOString() });
  if (records.length > 50) records.shift();
}

export function recentErrorRecords() {
  return records.slice();
}
