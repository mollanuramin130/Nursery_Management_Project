import { NextResponse } from "next/server";
import { laravelFetch, forwardLaravelResponse } from "@/lib/server/bff-upstream";
import { assertCsrf, readSessionCookies } from "@/lib/server/session-cookies";

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

  const upstream = await laravelFetch("/auth/otp/verify", {
    method: "POST",
    headers: { "Content-Type": "application/json" },
    body: JSON.stringify(body),
  });

  return forwardLaravelResponse(upstream);
}
