"use client";

import { FormEvent, useCallback, useEffect, useMemo, useState } from "react";
import { Breadcrumbs } from "@/components/layout/Breadcrumbs";
import { EmptyState, ErrorState, LoadingBlock, PageHeader } from "@/components/feedback/States";
import { Button } from "@/components/ui/Button";
import { FormSection } from "@/components/ui/FormSection";
import { Input } from "@/components/ui/Input";
import { ApiError } from "@/lib/api/client";
import { fetchSettings, updateSettings, type AdminSetting } from "@/lib/api/settings";
import { hasPermission } from "@/lib/auth/permissions";
import { useAuthStore } from "@/store/auth";
import { useToastStore } from "@/store/toast";

function valueToString(value: unknown): string {
  if (value == null) return "";
  if (typeof value === "string") return value;
  if (typeof value === "number" || typeof value === "boolean") return String(value);
  try {
    return JSON.stringify(value);
  } catch {
    return "";
  }
}

function parseValue(raw: string, type: string): unknown {
  const t = (type || "string").toLowerCase();
  if (t === "boolean") return raw === "true" || raw === "1";
  if (t === "integer" || t === "int" || t === "number") {
    const n = Number(raw);
    return Number.isFinite(n) ? n : raw;
  }
  if (t === "json" || t === "array" || t === "object") {
    try {
      return JSON.parse(raw);
    } catch {
      return raw;
    }
  }
  return raw;
}

export default function SettingsPage() {
  const user = useAuthStore((s) => s.user);
  const canManage = hasPermission(user, "users.manage");
  const push = useToastStore((s) => s.push);
  const [rows, setRows] = useState<AdminSetting[]>([]);
  const [edits, setEdits] = useState<Record<string, string>>({});
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);
  const [saving, setSaving] = useState(false);

  const load = useCallback(async () => {
    if (!canManage) {
      setLoading(false);
      setError("Missing permission: users.manage");
      return;
    }
    setLoading(true);
    setError(null);
    try {
      const res = await fetchSettings();
      setRows(res.data);
      const next: Record<string, string> = {};
      for (const s of res.data) {
        next[s.key] = valueToString(s.value);
      }
      setEdits(next);
    } catch (err) {
      setError(err instanceof ApiError || err instanceof Error ? err.message : "Failed to load");
    } finally {
      setLoading(false);
    }
  }, [canManage]);

  useEffect(() => {
    void load();
  }, [load]);

  const grouped = useMemo(() => {
    const map = new Map<string, AdminSetting[]>();
    for (const row of rows) {
      const g = row.group || "general";
      if (!map.has(g)) map.set(g, []);
      map.get(g)!.push(row);
    }
    return Array.from(map.entries()).sort(([a], [b]) => a.localeCompare(b));
  }, [rows]);

  async function onSave(e: FormEvent) {
    e.preventDefault();
    setSaving(true);
    try {
      const settings = rows.map((row) => ({
        key: row.key,
        value: parseValue(edits[row.key] ?? "", row.type),
        type: row.type,
        group: row.group,
      }));
      await updateSettings(settings);
      push("Settings saved", "success");
      await load();
    } catch (err) {
      push(err instanceof Error ? err.message : "Save failed", "error");
    } finally {
      setSaving(false);
    }
  }

  return (
    <div>
      <Breadcrumbs items={[{ label: "Admin", href: "/dashboard" }, { label: "Settings" }]} />
      <PageHeader
        title="Settings"
        description="Editable key/value settings from the Admin API. Do not invent secrets."
      />

      {loading ? <LoadingBlock /> : null}
      {error ? <ErrorState message={error} onRetry={() => void load()} /> : null}
      {!loading && !error && rows.length === 0 ? <EmptyState title="No settings" /> : null}

      {!loading && !error && rows.length > 0 ? (
        <form className="space-y-4" onSubmit={onSave}>
          {grouped.map(([group, items]) => (
            <FormSection key={group} title={group}>
              <div className="space-y-3">
                {items.map((item) => (
                  <div
                    key={item.key}
                    className="grid gap-2 border-b border-[var(--admin-border)]/50 pb-3 last:border-0 md:grid-cols-[220px_1fr]"
                  >
                    <div>
                      <div className="font-mono text-sm font-medium">{item.key}</div>
                      <div className="text-xs text-[var(--admin-muted)]">type: {item.type}</div>
                    </div>
                    <Input
                      value={edits[item.key] ?? ""}
                      onChange={(e) =>
                        setEdits((prev) => ({ ...prev, [item.key]: e.target.value }))
                      }
                    />
                  </div>
                ))}
              </div>
            </FormSection>
          ))}
          <Button type="submit" disabled={saving}>
            {saving ? "Saving…" : "Save settings"}
          </Button>
        </form>
      ) : null}
    </div>
  );
}
