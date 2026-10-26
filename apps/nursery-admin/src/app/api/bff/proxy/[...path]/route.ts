import { proxyPath, requiresAuthHandler } from "@/lib/server/proxy-path";
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

  const path = proxyPath(pathParts);
  if (!path) {
    return NextResponse.json({ success: false, message: "Invalid proxy path", data: null,
      errors: null, meta: { error_code: "INVALID_PATH" } }, { status: 400 });
  }
  const url = new URL(req.url);
  const qs = url.search;

  if (requiresAuthHandler(path)) {
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
  for (const h of [
    "content-type",
    "accept",
    "x-request-id",
    "x-platform",
    "x-app-version",
  ]) {
    const v = req.headers.get(h);
    if (v) headers.set(h, v);
  }

  const method = req.method.toUpperCase();
  const hasBody = method !== "GET" && method !== "HEAD";
  const body = hasBody ? await req.arrayBuffer() : undefined;

  let upstream = await laravelFetch(`${path}${qs}`, {
    method,
    headers,
    body: body && body.byteLength > 0 ? body : undefined,
    accessToken: session.access,
  });

  if (upstream.status === 401 && session.refresh) {
    let tokens;
    try {
      tokens = await refreshUpstream(session.refresh);
    } catch {
      return NextResponse.json({ success: false, message: "Authentication service temporarily unavailable",
        data: null, errors: null, meta: { error_code: "AUTH_SERVICE_UNAVAILABLE" } }, { status: 503 });
    }
    if (tokens) {
      upstream = await laravelFetch(`${path}${qs}`, {
        method,
        headers,
        body: body && body.byteLength > 0 ? body : undefined,
        accessToken: tokens.access,
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
  return handle(req, (await ctx.params).path);
}
export async function POST(req: NextRequest, ctx: Ctx) {
  return handle(req, (await ctx.params).path);
}
export async function PUT(req: NextRequest, ctx: Ctx) {
  return handle(req, (await ctx.params).path);
}
export async function PATCH(req: NextRequest, ctx: Ctx) {
  return handle(req, (await ctx.params).path);
}
export async function DELETE(req: NextRequest, ctx: Ctx) {
  return handle(req, (await ctx.params).path);
}
