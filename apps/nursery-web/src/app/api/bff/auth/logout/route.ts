import { NextResponse } from "next/server";
import { laravelFetch, refreshUpstream } from "@/lib/server/bff-upstream";
import {
  assertCsrf,
  clearAuthCookies,
  readSessionCookies,
} from "@/lib/server/session-cookies";

export async function POST(req: Request) {
  const session = await readSessionCookies();
  const csrfFail = assertCsrf(req, session.csrf);
  if (csrfFail) return csrfFail;

  let access = session.access;
  let refresh = session.refresh;

  if (refresh && !access) {
    // Local logout must still succeed if the upstream service is unavailable.
    const rotated = await refreshUpstream(refresh).catch(() => null);
    if (rotated) {
      access = rotated.access;
      refresh = rotated.refresh;
    }
  }

  if (access && refresh) {
    try {
      await laravelFetch("/auth/logout", {
        method: "POST",
        accessToken: access,
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify({ refresh_token: refresh }),
      });
    } catch {
      /* still clear cookies */
    }
  }

  const res = NextResponse.json({
    success: true,
    message: "Logged out successfully",
    data: null,
    errors: null,
    meta: null,
  });
  clearAuthCookies(res);
  return res;
}
