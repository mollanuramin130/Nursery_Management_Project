/**
 * Optional Web FCM token helper (QA-22).
 *
 * LIVE browser push requires installing `firebase` and setting
 * NEXT_PUBLIC_FIREBASE_* + VAPID key. Until then this returns null so
 * builds and runtime stay healthy; device registration still works
 * without a push token (in-app inbox remains authoritative).
 */
export async function getWebFcmToken(): Promise<string | null> {
  const configured = Boolean(
    process.env.NEXT_PUBLIC_FIREBASE_API_KEY &&
      process.env.NEXT_PUBLIC_FIREBASE_PROJECT_ID &&
      process.env.NEXT_PUBLIC_FIREBASE_MESSAGING_SENDER_ID &&
      process.env.NEXT_PUBLIC_FIREBASE_APP_ID &&
      process.env.NEXT_PUBLIC_FIREBASE_VAPID_KEY,
  );
  if (!configured) return null;

  // Intentionally not importing `firebase/*` here — that package is not a
  // hard dependency. Ops can replace this function with a real getToken()
  // implementation once the Firebase Web SDK is added to package.json.
  return null;
}
