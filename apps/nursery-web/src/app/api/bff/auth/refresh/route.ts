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

  const tokens = await refreshUpstream(session.refresh);
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
