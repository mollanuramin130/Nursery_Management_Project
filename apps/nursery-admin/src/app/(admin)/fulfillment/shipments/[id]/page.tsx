"use client";

import Link from "next/link";
import { FormEvent, useCallback, useEffect, useState } from "react";
import { useParams } from "next/navigation";
import { Breadcrumbs } from "@/components/layout/Breadcrumbs";
import { ErrorState, LoadingBlock, PageHeader } from "@/components/feedback/States";
import { Badge } from "@/components/ui/Badge";
import { Button } from "@/components/ui/Button";
import { FormSection } from "@/components/ui/FormSection";
import { Input, Select } from "@/components/ui/Input";
import { ApiError } from "@/lib/api/client";
import { addShipmentTracking, fetchShipment } from "@/lib/api/fulfillment";
import { hasPermission } from "@/lib/auth/permissions";
import { formatDateTime } from "@/lib/format";
import { useAuthStore } from "@/store/auth";
import { useToastStore } from "@/store/toast";

type ShipmentDetail = {
  id: number;
  order_id: number;
  order_number?: string;
  customer?: string;
  status: string;
  carrier?: string | null;
  tracking_number?: string | null;
  tracking_url?: string | null;
  eta_date?: string | null;
  shipping_address?: Record<string, unknown> | null;
  events?: Array<{
    id: number;
    status: string;
    description?: string | null;
    location?: string | null;
    event_at?: string | null;
  }>;
};

export default function ShipmentDetailPage() {
  const params = useParams();
  const id = Number(params.id);
  const user = useAuthStore((s) => s.user);
  const canView = hasPermission(user, "fulfillment.view");
  const canShip = hasPermission(user, "fulfillment.ship");
  const push = useToastStore((s) => s.push);

  const [shipment, setShipment] = useState<ShipmentDetail | null>(null);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);
  const [status, setStatus] = useState("IN_TRANSIT");
  const [description, setDescription] = useState("");
  const [location, setLocation] = useState("");
  const [saving, setSaving] = useState(false);

  const load = useCallback(async () => {
    if (!canView || !Number.isFinite(id)) {
      setLoading(false);
      setError(canView ? "Invalid shipment" : "Missing permission: fulfillment.view");
      return;
    }
    setLoading(true);
    setError(null);
    try {
      const res = await fetchShipment(id);
      setShipment(res.data as ShipmentDetail);
    } catch (err) {
      setError(err instanceof ApiError || err instanceof Error ? err.message : "Failed to load");
    } finally {
      setLoading(false);
    }
  }, [canView, id]);

  useEffect(() => {
    void load();
  }, [load]);

  async function onAddEvent(e: FormEvent) {
    e.preventDefault();
    if (!canShip) return;
    setSaving(true);
    try {
      await addShipmentTracking(id, {
        status,
        description: description || undefined,
        location: location || undefined,
      });
      push("Tracking event added", "success");
      setDescription("");
      await load();
    } catch (err) {
      push(err instanceof Error ? err.message : "Failed", "error");
    } finally {
      setSaving(false);
    }
  }

  if (loading) return <LoadingBlock />;
  if (error || !shipment) return <ErrorState message={error ?? "Not found"} onRetry={() => void load()} />;

  const addr = shipment.shipping_address as { line1?: string; city?: string; postal_code?: string } | null;

  return (
    <div>
      <Breadcrumbs
        items={[
          { label: "Admin", href: "/dashboard" },
          { label: "Shipments", href: "/fulfillment/shipments" },
          { label: `#${shipment.id}` },
        ]}
      />
      <PageHeader
        title={`Shipment #${shipment.id}`}
        description={`${shipment.order_number ?? ""} · ${shipment.customer ?? ""}`}
        actions={
          <Link href={`/fulfillment/orders/${shipment.order_id}`}>
            <Button type="button" variant="secondary">
              Fulfillment order
            </Button>
          </Link>
        }
      />

      <div className="mb-4 grid gap-3 rounded-[var(--admin-radius-lg)] border border-[var(--admin-border)] bg-white p-4 text-sm sm:grid-cols-3">
        <div>
          <div className="text-xs uppercase text-[var(--admin-muted)]">Status</div>
          <Badge>{shipment.status}</Badge>
        </div>
        <div>
          <div className="text-xs uppercase text-[var(--admin-muted)]">Carrier / Tracking</div>
          {shipment.carrier ?? "—"} · {shipment.tracking_number ?? "—"}
        </div>
        <div>
          <div className="text-xs uppercase text-[var(--admin-muted)]">ETA</div>
          {shipment.eta_date ?? "—"}
        </div>
        <div className="sm:col-span-3">
          <div className="text-xs uppercase text-[var(--admin-muted)]">Ship to</div>
          {addr ? `${addr.line1 ?? ""}, ${addr.city ?? ""} ${addr.postal_code ?? ""}` : "—"}
        </div>
      </div>

      <FormSection title="Tracking timeline">
        <ul className="space-y-2 text-sm">
          {(shipment.events ?? []).length === 0 ? (
            <li className="text-[var(--admin-muted)]">No events yet.</li>
          ) : (
            (shipment.events ?? []).map((ev) => (
              <li key={ev.id} className="border-b border-[var(--admin-border)]/60 pb-2">
                <div className="font-medium">
                  {ev.status} {ev.location ? `· ${ev.location}` : ""}
                </div>
                <div className="text-xs text-[var(--admin-muted)]">
                  {formatDateTime(ev.event_at)} {ev.description ? `· ${ev.description}` : ""}
                </div>
              </li>
            ))
          )}
        </ul>
      </FormSection>

      {canShip ? (
        <div className="mt-4">
          <FormSection title="Add tracking event">
            <form className="grid max-w-xl gap-3 sm:grid-cols-2" onSubmit={onAddEvent}>
              <Select label="Status" value={status} onChange={(e) => setStatus(e.target.value)}>
                <option value="PICKED_UP">Picked up</option>
                <option value="IN_TRANSIT">In transit</option>
                <option value="OUT_FOR_DELIVERY">Out for delivery</option>
                <option value="DELIVERED">Delivered</option>
                <option value="FAILED">Failed</option>
              </Select>
              <Input label="Location" value={location} onChange={(e) => setLocation(e.target.value)} />
              <div className="sm:col-span-2">
                <Input
                  label="Description"
                  value={description}
                  onChange={(e) => setDescription(e.target.value)}
                />
              </div>
              <Button type="submit" disabled={saving}>
                {saving ? "Saving…" : "Add event"}
              </Button>
            </form>
          </FormSection>
        </div>
      ) : null}
    </div>
  );
}
