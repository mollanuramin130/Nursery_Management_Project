"use client";

import { useEffect } from "react";
import { useRouter } from "next/navigation";
import { useAuthStore } from "@/store/auth";

export default function HomePage() {
  const router = useRouter();
  const { user, bootstrapped } = useAuthStore();

  useEffect(() => {
    if (!bootstrapped) return;
    router.replace(user ? "/dashboard" : "/login");
  }, [bootstrapped, user, router]);

  return (
    <div className="flex min-h-screen items-center justify-center text-sm text-[var(--admin-muted)]">
      Redirecting…
    </div>
  );
}
