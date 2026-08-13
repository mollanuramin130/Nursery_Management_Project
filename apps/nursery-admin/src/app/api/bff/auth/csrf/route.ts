import { NextResponse } from "next/server";
import {
  CSRF_COOKIE,
  csrfCookieOptions,
  readSessionCookies,
} from "@/lib/server/session-cookies";

function newCsrf(): string {
  if (typeof crypto !== "undefined" && "randomUUID" in crypto) {
    return crypto.randomUUID().replace(/-/g, "");
  }
  return `${Date.now().toString(36)}${Math.random().toString(36).slice(2)}`;
}

export async function GET() {
  const { csrf } = await readSessionCookies();
  const token = csrf && csrf.length >= 16 ? csrf : newCsrf();
  const res = NextResponse.json({
    success: true,
    message: "CSRF ready",
    data: { csrf_token: token },
    errors: null,
    meta: null,
  });
  res.cookies.set(CSRF_COOKIE, token, csrfCookieOptions());
  return res;
}
