const LEGACY_ACCESS = "gl_admin_access";
const LEGACY_REFRESH = "gl_admin_refresh";

export const storage = {
  /** @deprecated QA-33 — JWTs are HttpOnly cookies. */
  getAccess(): string | null {
    return null;
  },
  /** @deprecated QA-33 — JWTs are HttpOnly cookies. */
  getRefresh(): string | null {
    return null;
  },
  setTokens(_access: string, _refresh: string) {
    /* intentionally empty */
  },
  clearTokens() {
    if (typeof window === "undefined") return;
    localStorage.removeItem(LEGACY_ACCESS);
    localStorage.removeItem(LEGACY_REFRESH);
  },
  clearLegacyAuthTokens() {
    if (typeof window === "undefined") return;
    localStorage.removeItem(LEGACY_ACCESS);
    localStorage.removeItem(LEGACY_REFRESH);
  },
};
