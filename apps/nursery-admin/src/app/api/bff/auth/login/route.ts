import { NextResponse } from "next/server";
import { laravelFetch } from "@/lib/server/bff-upstream";
import {
  assertCsrf,
  ensureCsrfToken,
  readSessionCookies,
  setAuthCookies,
  stripTokensFromAuthData,
} from "@/lib/server/session-cookies";

export async function POST(req: Request) {
  const session = await readSessionCookies();
  const csrfFail = assertCsrf(req, session.csrf);
  if (csrfFail) return csrfFail;

  let body: unknown;
  try {
    body = await req.json();
  } catch {
    return NextResponse.json(
      { success: false, message: "Invalid JSON", data: null, errors: null, meta: null },
      { status: 400 },
    );
  }

  const upstream = await laravelFetch("/auth/login", {
    method: "POST",
    headers: { "Content-Type": "application/json" },
    body: JSON.stringify(body),
  });

  const json = (await upstream.json()) as {
    success?: boolean;
    message?: string;
    data?: Record<string, unknown> | null;
    errors?: unknown;
    meta?: unknown;
  };

  if (!upstream.ok || !json.success || !json.data) {
    return NextResponse.json(json, { status: upstream.status });
  }

  const access = json.data.access_token;
  const refresh = json.data.refresh_token;
  if (typeof access !== "string" || typeof refresh !== "string") {
    return NextResponse.json(
      {
        success: false,
        message: "Login response missing tokens",
        data: null,
        errors: null,
        meta: { error_code: "AUTH_BFF_ERROR" },
      },
      { status: 502 },
    );
  }

  const res = NextResponse.json(
    {
      success: true,
      message: json.message ?? "Logged in",
      data: stripTokensFromAuthData(json.data),
      errors: null,
      meta: json.meta ?? null,
    },
    { status: 200 },
  );
  setAuthCookies(res, access, refresh);
  ensureCsrfToken(res, session.csrf);
  return res;
}
