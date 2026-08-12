import { Suspense } from "react";
import LoginPage from "./LoginClient";

export default function Page() {
  return (
    <Suspense
      fallback={
        <div className="flex min-h-screen items-center justify-center text-sm text-[var(--admin-muted)]">
          Loading…
        </div>
      }
    >
      <LoginPage />
    </Suspense>
  );
}
