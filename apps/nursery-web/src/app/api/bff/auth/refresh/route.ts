import { NextResponse } from "next/server";
import { refreshUpstream } from "@/lib/server/bff-upstream";
import {
  assertCsrf,
  clearAuthCookies,
  ensureCsrfToken,
  readSessionCookies,
  setAuthCookies,
} from "@/lib/server/session-cookies";

export async function POST(req: Request) {
  const session = await readSessionCookies();
  const csrfFail = assertCsrf(req, session.csrf);
  if (csrfFail) return csrfFail;

  if (!session.refresh) {
    const res = NextResponse.json(
      {
        success: false,
        message: "Unauthenticated",
        data: null,
        errors: null,
        meta: { error_code: "UNAUTHENTICATED" },
      },
      { status: 401 },
    );
    clearAuthCookies(res);
    return res;
  }

  let tokens;
    try {
      tokens = await refreshUpstream(session.refresh);
    } catch {
      return NextResponse.json({ success: false, message: "Authentication service temporarily unavailable",
        data: null, errors: null, meta: { error_code: "AUTH_SERVICE_UNAVAILABLE" } }, { status: 503 });
    }
  if (!tokens) {
    const res = NextResponse.json(
      {
        success: false,
        message: "Invalid or expired refresh token",
        data: null,
        errors: null,
        meta: { error_code: "UNAUTHENTICATED" },
      },
      { status: 401 },
    );
    clearAuthCookies(res);
    return res;
  }

  const res = NextResponse.json({
    success: true,
    message: "Token refreshed",
    data: { expires_in: 3600 },
    errors: null,
    meta: null,
  });
  setAuthCookies(res, tokens.access, tokens.refresh);
  ensureCsrfToken(res, session.csrf);
  return res;
}
