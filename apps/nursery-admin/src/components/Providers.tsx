"use client";

import { useEffect } from "react";
import { useAuthStore } from "@/store/auth";
import { AppErrorBoundary } from "@/components/error/AppErrorBoundary";

export function Providers({ children }: { children: React.ReactNode }) {
  const bootstrap = useAuthStore((s) => s.bootstrap);

  useEffect(() => {
    void bootstrap();
  }, [bootstrap]);

  return <AppErrorBoundary>{children}</AppErrorBoundary>;
}
