const CART = "gl_cart_token";
const GUEST = "gl_guest_token";

/** Legacy JWT keys (QA-SEC-001) — must never be written again. */
const LEGACY_ACCESS = "gl_access_token";
const LEGACY_REFRESH = "gl_refresh_token";

function randomToken(): string {
  if (typeof crypto !== "undefined" && "randomUUID" in crypto) {
    return crypto.randomUUID().replace(/-/g, "");
  }
  return `${Date.now().toString(36)}${Math.random().toString(36).slice(2, 14)}`;
}

export const storage = {
  /** @deprecated QA-33 — JWTs are HttpOnly cookies; always null in JS. */
  getAccess(): string | null {
    return null;
  },
  /** @deprecated QA-33 — JWTs are HttpOnly cookies; always null in JS. */
  getRefresh(): string | null {
    return null;
  },
  getCartToken(): string | null {
    if (typeof window === "undefined") return null;
    return localStorage.getItem(CART);
  },
  getGuestToken(): string {
    if (typeof window === "undefined") return "";
    let t = localStorage.getItem(GUEST);
    if (!t || t.length < 8) {
      t = randomToken();
      localStorage.setItem(GUEST, t);
    }
    return t;
  },
  /** No-op — tokens must not be stored in JS-accessible storage. */
  setTokens(_access: string, _refresh: string) {
    /* QA-33: intentionally empty */
  },
  clearTokens() {
    if (typeof window === "undefined") return;
    localStorage.removeItem(LEGACY_ACCESS);
    localStorage.removeItem(LEGACY_REFRESH);
  },
  /** Wipe any pre-QA-33 JWT leftovers from localStorage. */
  clearLegacyAuthTokens() {
    if (typeof window === "undefined") return;
    localStorage.removeItem(LEGACY_ACCESS);
    localStorage.removeItem(LEGACY_REFRESH);
  },
  setCartToken(token: string | null) {
    if (typeof window === "undefined") return;
    if (!token) {
      localStorage.removeItem(CART);
      return;
    }
    localStorage.setItem(CART, token);
  },
};
