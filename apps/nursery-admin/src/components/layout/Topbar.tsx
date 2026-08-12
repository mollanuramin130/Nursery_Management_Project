"use client";

import { primaryRoleLabel } from "@/lib/auth/permissions";
import { Button } from "@/components/ui/Button";
import { useAuthStore } from "@/store/auth";
import { useUiStore } from "@/store/ui";

export function Topbar({ title }: { title?: string }) {
  const user = useAuthStore((s) => s.user);
  const logout = useAuthStore((s) => s.logout);
  const { toggleSidebar, setMobileSidebarOpen } = useUiStore();

  return (
    <header className="flex h-[var(--admin-topbar-h)] items-center justify-between gap-3 border-b border-[var(--admin-border)] bg-white px-4">
      <div className="flex min-w-0 items-center gap-2">
        <Button
          variant="ghost"
          size="sm"
          className="md:hidden"
          aria-label="Open navigation"
          onClick={() => setMobileSidebarOpen(true)}
        >
          ☰
        </Button>
        <Button
          variant="ghost"
          size="sm"
          className="hidden md:inline-flex"
          aria-label="Collapse sidebar"
          onClick={toggleSidebar}
        >
          ☰
        </Button>
        <div className="min-w-0">
          <div className="truncate text-sm font-semibold text-[var(--admin-ink)]">
            {title ?? "GreenLeaf Nursery Admin"}
          </div>
        </div>
      </div>

      <div className="flex items-center gap-3">
        <div className="hidden text-right sm:block">
          <div className="text-sm font-medium text-[var(--admin-ink)]">{user?.name}</div>
          <div className="text-[11px] text-[var(--admin-muted)]">{primaryRoleLabel(user)}</div>
        </div>
        <Button variant="secondary" size="sm" onClick={() => void logout()}>
          Logout
        </Button>
      </div>
    </header>
  );
}
