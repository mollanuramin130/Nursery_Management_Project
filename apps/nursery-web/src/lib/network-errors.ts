/**
 * QA-37 — shopper-safe HTTP / transport error mapping (Customer Web).
 */

export type NetworkKind =
  | "online"
  | "offline"
  | "connecting"
  | "reconnecting"
  | "apiUnavailable"
  | "apiTimeout"
  | "serverError"
  | "rateLimited"
  | "authExpired"
  | "unknownError";

export type ClassifiedNetworkError = {
  kind: NetworkKind;
  userMessage: string;
  statusCode?: number;
  retryable: boolean;
};

export function classifyHttpStatus(
  status?: number,
  apiMessage?: string | null,
): ClassifiedNetworkError {
  const msg = apiMessage?.trim();
  switch (status) {
    case 401:
      return {
        kind: "authExpired",
        userMessage: "Your session has expired. Please sign in again.",
        statusCode: status,
        retryable: false,
      };
    case 403:
      return {
        kind: "unknownError",
        userMessage: "You don't have permission to do that.",
        statusCode: status,
        retryable: false,
      };
    case 404:
      return {
        kind: "unknownError",
        userMessage: msg || "We could not find that item.",
        statusCode: status,
        retryable: false,
      };
    case 408:
      return {
        kind: "apiTimeout",
        userMessage: "The request took too long. Please try again.",
        statusCode: status,
        retryable: true,
      };
    case 409:
      return {
        kind: "unknownError",
        userMessage:
          msg || "This action conflicts with the current state. Please refresh.",
        statusCode: status,
        retryable: false,
      };
    case 422:
      return {
        kind: "unknownError",
        userMessage: msg || "Please check your details and try again.",
        statusCode: status,
        retryable: false,
      };
    case 429:
      return {
        kind: "rateLimited",
        userMessage:
          msg && !/^too many attempts\.?$/i.test(msg)
            ? msg
            : "You're doing that too quickly. Please wait a moment, then try again.",
        statusCode: status,
        retryable: true,
      };
    case 500:
      return {
        kind: "serverError",
        userMessage: "GreenLeaf server is temporarily unavailable. Please try again.",
        statusCode: status,
        retryable: true,
      };
    case 502:
    case 503:
    case 504:
      return {
        kind: "apiUnavailable",
        userMessage: "Unable to connect to GreenLeaf right now.",
        statusCode: status,
        retryable: true,
      };
    default:
      return {
        kind: "unknownError",
        userMessage: sanitizeTechnical(msg) || "Something went wrong. Please try again.",
        statusCode: status,
        retryable: true,
      };
  }
}

export function sanitizeTechnical(raw?: string | null): string {
  if (!raw) return "";
  const lower = raw.toLowerCase();
  if (
    lower.includes("php artisan") ||
    lower.includes("api_proxy_target")
  ) {
    return "Unable to connect to GreenLeaf right now.";
  }
  if (
    lower.includes("socketexception") ||
    lower.includes("failed host lookup") ||
    lower.includes("network error") ||
    lower.includes("econnrefused")
  ) {
    return "Connection temporarily unavailable.";
  }
  if (raw.length > 180) return "Something went wrong. Please try again.";
  return raw.trim();
}

export function bannerCopy(kind: NetworkKind): string {
  switch (kind) {
    case "offline":
      return "You're offline";
    case "reconnecting":
    case "connecting":
      return "Checking connection…";
    case "apiUnavailable":
      return "Unable to connect to GreenLeaf right now.";
    case "serverError":
      return "GreenLeaf server is temporarily unavailable.";
    case "apiTimeout":
      return "Connection is taking too long.";
    case "rateLimited":
      return "Too many requests · Please wait a moment";
    case "authExpired":
      return "Your session has expired";
    default:
      return "Unable to refresh.";
  }
}

/** Axios/transport: no response → offline or API unavailable. */
export function classifyTransport(opts: {
  timedOut?: boolean;
  browserOffline?: boolean;
}): ClassifiedNetworkError {
  if (opts.browserOffline) {
    return {
      kind: "offline",
      userMessage: "You're offline. Check your internet connection.",
      retryable: true,
    };
  }
  if (opts.timedOut) {
    return {
      kind: "apiTimeout",
      userMessage: "Connection is taking too long. Please try again.",
      retryable: true,
    };
  }
  return {
    kind: "apiUnavailable",
    userMessage: "Unable to connect to GreenLeaf right now.",
    retryable: true,
  };
}

export function isTransientAxiosFailure(error: {
  response?: unknown;
  code?: string;
}): boolean {
  if (error.response) return false;
  return (
    error.code === "ECONNABORTED" ||
    error.code === "ERR_NETWORK" ||
    error.code === "ECONNREFUSED" ||
    !error.code
  );
}
