import { NextRequest, NextResponse } from "next/server";
import {
  forwardLaravelResponse,
  laravelFetch,
  refreshUpstream,
} from "@/lib/server/bff-upstream";
import {
  assertCsrf,
  clearAuthCookies,
  readSessionCookies,
  setAuthCookies,
} from "@/lib/server/session-cookies";

export const dynamic = "force-dynamic";

async function handle(req: NextRequest, pathParts: string[]) {
  const session = await readSessionCookies();
  const csrfFail = assertCsrf(req, session.csrf);
  if (csrfFail) return csrfFail;

  const path = `/${pathParts.join("/")}`;
  const url = new URL(req.url);
  const qs = url.search;

  // Never allow browser to hit auth token endpoints via proxy (BFF auth routes only).
  if (
    path === "/auth/login" ||
    path === "/auth/register" ||
    path === "/auth/refresh" ||
    path === "/auth/logout"
  ) {
    return NextResponse.json(
      {
        success: false,
        message: "Use /api/bff/auth/* for authentication",
        data: null,
        errors: null,
        meta: { error_code: "AUTH_BFF_REQUIRED" },
      },
      { status: 400 },
    );
  }

  const headers = new Headers();
  const forwardHeaders = [
    "content-type",
    "accept",
    "x-cart-token",
    "x-guest-token",
    "x-request-id",
    "x-platform",
    "x-app-version",
  ];
  for (const h of forwardHeaders) {
    const v = req.headers.get(h);
    if (v) headers.set(h, v);
  }

  const method = req.method.toUpperCase();
  const hasBody = method !== "GET" && method !== "HEAD";
  const body = hasBody ? await req.arrayBuffer() : undefined;

  let access = session.access;
  let upstream = await laravelFetch(`${path}${qs}`, {
    method,
    headers,
    body: body && body.byteLength > 0 ? body : undefined,
    accessToken: access,
  });

  // Server-side silent refresh — browser never sees JWTs.
  if (upstream.status === 401 && session.refresh) {
    const tokens = await refreshUpstream(session.refresh);
    if (tokens) {
      access = tokens.access;
      upstream = await laravelFetch(`${path}${qs}`, {
        method,
        headers,
        body: body && body.byteLength > 0 ? body : undefined,
        accessToken: access,
      });
      const res = await forwardLaravelResponse(upstream);
      setAuthCookies(res, tokens.access, tokens.refresh);
      return res;
    }
    const res = await forwardLaravelResponse(upstream);
    clearAuthCookies(res);
    return res;
  }

  return forwardLaravelResponse(upstream);
}

type Ctx = { params: Promise<{ path: string[] }> };

export async function GET(req: NextRequest, ctx: Ctx) {
  const { path } = await ctx.params;
  return handle(req, path);
}
export async function POST(req: NextRequest, ctx: Ctx) {
  const { path } = await ctx.params;
  return handle(req, path);
}
export async function PUT(req: NextRequest, ctx: Ctx) {
  const { path } = await ctx.params;
  return handle(req, path);
}
export async function PATCH(req: NextRequest, ctx: Ctx) {
  const { path } = await ctx.params;
  return handle(req, path);
}
export async function DELETE(req: NextRequest, ctx: Ctx) {
  const { path } = await ctx.params;
  return handle(req, path);
}
