"use client";

import { useState } from "react";
import { useRouter } from "next/navigation";
import { Breadcrumbs } from "@/components/layout/Breadcrumbs";
import { PageHeader } from "@/components/feedback/States";
import { Button } from "@/components/ui/Button";
import { FormSection } from "@/components/ui/FormSection";
import { Input, Select } from "@/components/ui/Input";
import { createSegment } from "@/lib/api/segments";
import { hasPermission } from "@/lib/auth/permissions";
import { useAuthStore } from "@/store/auth";
import { useToastStore } from "@/store/toast";

const FIELDS = [
  "order_count",
  "total_spent",
  "last_order_days",
  "registered_days",
  "inactive_days",
  "has_subscription",
  "loyalty_balance",
  "marketing_opt_in",
  "has_wishlist",
  "has_open_cart",
  "cart_inactive_hours",
];

const OPS = ["eq", "neq", "gt", "gte", "lt", "lte"];

export default function NewSegmentPage() {
  const router = useRouter();
  const user = useAuthStore((s) => s.user);
  const can = hasPermission(user, "customers.segment");
  const push = useToastStore((s) => s.push);
  const [busy, setBusy] = useState(false);
  const [name, setName] = useState("");
  const [description, setDescription] = useState("");
  const [field, setField] = useState("order_count");
  const [op, setOp] = useState("gte");
  const [value, setValue] = useState("1");

  async function onSubmit(e: React.FormEvent) {
    e.preventDefault();
    if (!can) return;
    setBusy(true);
    try {
      const numericFields = new Set([
        "order_count",
        "total_spent",
        "last_order_days",
        "registered_days",
        "inactive_days",
        "loyalty_balance",
        "cart_inactive_hours",
      ]);
      const boolFields = new Set(["has_subscription", "marketing_opt_in", "has_wishlist", "has_open_cart"]);
      let parsed: number | boolean | string = value;
      if (numericFields.has(field)) parsed = Number(value);
      if (boolFields.has(field)) parsed = value === "true" || value === "1";

      const res = await createSegment({
        name,
        description: description || undefined,
        criteria_json: { all: [{ field, op, value: parsed }] },
      });
      push("Segment created", "success");
      router.push(`/customers/segments/${res.data.id}`);
    } catch (err) {
      push(err instanceof Error ? err.message : "Create failed", "error");
    } finally {
      setBusy(false);
    }
  }

  if (!can) {
    return <p className="p-4 text-sm">Missing permission: customers.segment</p>;
  }

  return (
    <div>
      <Breadcrumbs
        items={[
          { label: "Admin", href: "/dashboard" },
          { label: "Customers", href: "/customers" },
          { label: "Segments", href: "/customers/segments" },
          { label: "New" },
        ]}
      />
      <PageHeader title="Create segment" description="AND rules evaluated server-side." />

      <form onSubmit={onSubmit} className="max-w-xl space-y-4">
        <FormSection title="Segment">
          <div className="grid gap-3">
            <Input required placeholder="Name" value={name} onChange={(e) => setName(e.target.value)} />
            <Input
              placeholder="Description"
              value={description}
              onChange={(e) => setDescription(e.target.value)}
            />
          </div>
        </FormSection>
        <FormSection title="First rule (AND)">
          <div className="grid gap-3 sm:grid-cols-3">
            <Select value={field} onChange={(e) => setField(e.target.value)}>
              {FIELDS.map((f) => (
                <option key={f} value={f}>
                  {f}
                </option>
              ))}
            </Select>
            <Select value={op} onChange={(e) => setOp(e.target.value)}>
              {OPS.map((o) => (
                <option key={o} value={o}>
                  {o}
                </option>
              ))}
            </Select>
            <Input required value={value} onChange={(e) => setValue(e.target.value)} placeholder="Value" />
          </div>
          <p className="mt-2 text-xs text-[var(--admin-muted)]">
            Add more rules via API/edit later. Boolean fields: true/false. See PHASE_17_CRM_DESIGN.md.
          </p>
        </FormSection>
        <Button type="submit" disabled={busy}>
          Create
        </Button>
      </form>
    </div>
  );
}
