import assert from "node:assert/strict";
import { test } from "node:test";
import { proxyPath, requiresAuthHandler } from "./proxy-path";
import { forwardLaravelResponse, refreshUpstream } from "./bff-upstream";

test("proxy preserves valid routes and encodes Unicode slugs", () => {
  assert.equal(proxyPath(["products", "snake-plant"]), "/products/snake-plant");
  assert.equal(proxyPath(["products", "তুলসী"]), `/products/${encodeURIComponent("তুলসী")}`);
});
test("proxy rejects decoded separators, traversal and nested encodings", () => {
  for (const parts of [[], [""], ["auth/login"], ["auth", "login/"], ["..", "auth"],
    ["."], ["auth", "%6cogin"], ["products", "a?b"], ["a#b"], ["a\\b"], ["a\n"]]) {
    assert.equal(proxyPath(parts), null, JSON.stringify(parts));
  }
});
test("all token issuing routes require dedicated auth handlers", () => {
  for (const path of ["/auth/login", "/auth/login/otp", "/auth/register", "/auth/refresh", "/auth/logout"])
    assert.equal(requiresAuthHandler(path), true, path);
  for (const path of ["/auth/me", "/auth/otp/send", "/auth/otp/verify", "/products"])
    assert.equal(requiresAuthHandler(path), false, path);
});
test("proxy forwards bodyless responses without Response constructor errors", async () => {
  for (const status of [204, 205, 304]) {
    const response = await forwardLaravelResponse(new Response(null, { status }));
    assert.equal(response.status, status);
    assert.equal(response.body, null);
  }
});
test("proxy preserves JSON errors and retry metadata", async () => {
  const body = JSON.stringify({ success: false, message: "Wait" });
  const response = await forwardLaravelResponse(new Response(body, {
    status: 429, headers: { "content-type": "application/json", "retry-after": "30", "x-request-id": "audit" },
  }));
  assert.equal(response.status, 429);
  assert.equal(await response.text(), body);
  assert.equal(response.headers.get("retry-after"), "30");
  assert.equal(response.headers.get("x-request-id"), "audit");
});
test("refresh only invalidates sessions for rejected credentials", async () => {
  const originalFetch = globalThis.fetch;
  try {
    for (const status of [401, 403]) {
      globalThis.fetch = async () => new Response(null, { status });
      assert.equal(await refreshUpstream("test-only"), null);
    }
    for (const status of [429, 500, 503]) {
      globalThis.fetch = async () => new Response(null, { status });
      await assert.rejects(refreshUpstream("test-only"));
    }
    globalThis.fetch = async () => Response.json({ data: {} });
    await assert.rejects(refreshUpstream("test-only"));
    globalThis.fetch = async () => Response.json({ data: { access_token: "a", refresh_token: "r" } });
    assert.deepEqual(await refreshUpstream("test-only"), { access: "a", refresh: "r" });
  } finally { globalThis.fetch = originalFetch; }
});


test("readiness probe uses the API contract and safe outage messages", async () => {
  const { probeApiHealth } = await import("../api-health");
  const originalFetch = globalThis.fetch;
  try {
    globalThis.fetch = async () => Response.json({ status: "ok", database: "healthy" });
    assert.deepEqual(await probeApiHealth("https://api.test/api/v1/"), { status: "ok" });
    for (const response of [new Response(null, {status: 503}), Response.json({status: "degraded", database: "healthy"}), new Response("not json")]) {
      globalThis.fetch = async () => response;
      const result = await probeApiHealth("https://api.test/api/v1");
      assert.equal(result.status, "down");
      if (result.status === "down") assert.doesNotMatch(result.detail, /artisan|Laravel|MYSQL|\.env|HTTP|API_BASE/i);
    }
    globalThis.fetch = async () => { throw new Error("network failed"); };
    assert.equal((await probeApiHealth("https://api.test/api/v1")).status, "down");
  } finally { globalThis.fetch = originalFetch; }
});
