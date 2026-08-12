const ACCESS = "gl_access_token";
const REFRESH = "gl_refresh_token";
const CART = "gl_cart_token";
const GUEST = "gl_guest_token";

function randomToken(): string {
  if (typeof crypto !== "undefined" && "randomUUID" in crypto) {
    return crypto.randomUUID().replace(/-/g, "");
  }
  return `${Date.now().toString(36)}${Math.random().toString(36).slice(2, 14)}`;
}

export const storage = {
  getAccess(): string | null {
    if (typeof window === "undefined") return null;
    return localStorage.getItem(ACCESS);
  },
  getRefresh(): string | null {
    if (typeof window === "undefined") return null;
    return localStorage.getItem(REFRESH);
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
  setTokens(access: string, refresh: string) {
    localStorage.setItem(ACCESS, access);
    localStorage.setItem(REFRESH, refresh);
  },
  clearTokens() {
    localStorage.removeItem(ACCESS);
    localStorage.removeItem(REFRESH);
  },
  setCartToken(token: string | null) {
    if (!token) {
      localStorage.removeItem(CART);
      return;
    }
    localStorage.setItem(CART, token);
  },
};
