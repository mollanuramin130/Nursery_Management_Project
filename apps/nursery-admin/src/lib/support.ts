export const GREENLEAF_SUPPORT_PHONE = "8926627220";
export const GREENLEAF_SUPPORT_TEL = `tel:${GREENLEAF_SUPPORT_PHONE}`;

export function newErrorReference(): string {
  const hex = "0123456789ABCDEF";
  let out = "GL-";
  const bytes =
    typeof crypto !== "undefined" && "getRandomValues" in crypto
      ? crypto.getRandomValues(new Uint8Array(5))
      : Uint8Array.from({ length: 5 }, () => Math.floor(Math.random() * 16));
  for (const b of bytes) out += hex[b % 16];
  return out;
}

export function canUseTelHandler(): boolean {
  if (typeof navigator === "undefined") return false;
  return /Mobi|Android|iPhone|iPad|iPod/i.test(navigator.userAgent);
}

export function redactSecrets(raw: string): string {
  return raw
    .replace(/eyJ[A-Za-z0-9_-]+\.[A-Za-z0-9_-]+\.[A-Za-z0-9_-]+/g, "[redacted]")
    .replace(
      /(password|passwd|secret|token|jwt|cookie|authorization)[=:]\s*\S+/gi,
      "$1=[redacted]",
    )
    .replace(/rzp_(live|test)_[A-Za-z0-9]+/g, "[redacted]");
}

export async function copySupportNumber(): Promise<boolean> {
  try {
    await navigator.clipboard.writeText(GREENLEAF_SUPPORT_PHONE);
    return true;
  } catch {
    return false;
  }
}

export function looksLikeErrorReference(value: string): boolean {
  return /^GL-[0-9A-F]{5}$/.test(value);
}
