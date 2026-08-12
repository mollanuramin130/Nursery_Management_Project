"use client";

import Link from "next/link";
import { usePathname } from "next/navigation";
import { visibleNav } from "@/lib/auth/navigation";
import { cn } from "@/lib/cn";
import { useAuthStore } from "@/store/auth";
import { useUiStore } from "@/store/ui";

export function Sidebar() {
  const user = useAuthStore((s) => s.user);
  const pathname = usePathname();
  const { sidebarCollapsed, mobileSidebarOpen, setMobileSidebarOpen } = useUiStore();
  const sections = visibleNav(user);

  const content = (
    <aside
      className={cn(
        "flex h-full flex-col bg-[var(--admin-sidebar)] text-[var(--admin-sidebar-text)] transition-[width]",
        sidebarCollapsed ? "w-[var(--admin-sidebar-collapsed)]" : "w-[var(--admin-sidebar-w)]",
      )}
    >
      <div className="flex h-[var(--admin-topbar-h)] items-center gap-2 border-b border-white/10 px-4">
        <div className="flex h-8 w-8 shrink-0 items-center justify-center rounded bg-[var(--admin-primary)] text-xs font-bold text-white">
          GL
        </div>
        {!sidebarCollapsed ? (
          <div className="min-w-0">
            <div className="truncate text-sm font-semibold text-white">GreenLeaf</div>
            <div className="truncate text-[11px] text-[var(--admin-sidebar-muted)]">
              Nursery Admin
            </div>
          </div>
        ) : null}
      </div>

      <nav className="admin-scroll flex-1 overflow-y-auto px-2 py-3">
        {sections.map((section) => (
          <div key={section.label ?? "root"} className="mb-3">
            {section.label && !sidebarCollapsed ? (
              <div className="mb-1 px-2 text-[10px] font-semibold uppercase tracking-[0.08em] text-[var(--admin-sidebar-muted)]">
                {section.label}
              </div>
            ) : null}
            <ul className="space-y-0.5">
              {section.items.map((item) => {
                const active =
                  pathname === item.href ||
                  (item.href !== "/dashboard" &&
                    !item.href.startsWith("/coming-soon") &&
                    pathname.startsWith(item.href));
                return (
                  <li key={item.href + item.label}>
                    <Link
                      href={item.href}
                      title={item.label}
                      onClick={() => setMobileSidebarOpen(false)}
                      className={cn(
                        "flex items-center gap-2 rounded-[var(--admin-radius)] px-2.5 py-2 text-sm transition",
                        active
                          ? "bg-[var(--admin-sidebar-active)] text-white"
                          : "hover:bg-[var(--admin-sidebar-hover)]",
                        sidebarCollapsed && "justify-center",
                      )}
                    >
                      <span
                        className={cn(
                          "inline-flex h-1.5 w-1.5 shrink-0 rounded-full",
                          active ? "bg-[var(--admin-primary-soft)]" : "bg-[var(--admin-sidebar-muted)]",
                        )}
                      />
                      {!sidebarCollapsed ? <span className="truncate">{item.label}</span> : null}
                    </Link>
                  </li>
                );
              })}
            </ul>
          </div>
        ))}
      </nav>
    </aside>
  );

  return (
    <>
      <div className="hidden md:block">{content}</div>
      {mobileSidebarOpen ? (
        <div className="fixed inset-0 z-40 md:hidden">
          <button
            type="button"
            aria-label="Close navigation"
            className="absolute inset-0 bg-black/40"
            onClick={() => setMobileSidebarOpen(false)}
          />
          <div className="absolute inset-y-0 left-0 shadow-xl">{content}</div>
        </div>
      ) : null}
    </>
  );
}
