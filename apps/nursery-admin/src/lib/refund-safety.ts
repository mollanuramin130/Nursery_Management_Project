/**
 * Admin refund / local_stub UX guards (QA-08 / QA-PAY-002).
 * Backend remains authoritative — these helpers only prevent misleading UI in production builds.
 */

export function isAdminProductionSite(): boolean {
  return process.env.NODE_ENV === "production";
}

/** True when Admin UI may offer local_stub / recorded_local refund actions. */
export function canOfferLocalStubRefund(): boolean {
  return !isAdminProductionSite();
}

/**
 * Throws if production Admin UI attempts a stub refund path.
 * Call before opening confirm / submitting createRefund.
 */
export function assertLocalStubRefundAllowed(): void {
  if (isAdminProductionSite()) {
    throw new Error(
      "Live payment-provider refunds are not enabled in production. Use the offline ops process or wait for gateway integration.",
    );
  }
}
