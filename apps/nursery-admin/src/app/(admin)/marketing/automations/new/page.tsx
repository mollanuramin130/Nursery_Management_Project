"use client";

import { useState } from "react";
import { useRouter } from "next/navigation";
import { Breadcrumbs } from "@/components/layout/Breadcrumbs";
import { PageHeader } from "@/components/feedback/States";
import { Button } from "@/components/ui/Button";
import { FormSection } from "@/components/ui/FormSection";
import { Input, Select, TextArea } from "@/components/ui/Input";
import { createAutomation } from "@/lib/api/marketing";
import { hasPermission } from "@/lib/auth/permissions";
import { useAuthStore } from "@/store/auth";
import { useToastStore } from "@/store/toast";

export default function NewAutomationPage() {
  const router = useRouter();
  const user = useAuthStore((s) => s.user);
  const canManage = hasPermission(user, "marketing.manage");
  const push = useToastStore((s) => s.push);
  const [busy, setBusy] = useState(false);
  const [name, setName] = useState("");
  const [type, setType] = useState("manual_blast");
  const [segmentId, setSegmentId] = useState("");
  const [title, setTitle] = useState("");
  const [body, setBody] = useState("");

  async function onSubmit(e: React.FormEvent) {
    e.preventDefault();
    if (!canManage) return;
    setBusy(true);
    try {
      const res = await createAutomation({
        name,
        type,
        status: "draft",
        segment_id: segmentId ? Number(segmentId) : null,
        channels: ["in_app", "email"],
        title_template: title,
        body_template: body,
      });
      push("Automation created as draft", "success");
      router.push(`/marketing/automations/${res.data.id}`);
    } catch (err) {
      push(err instanceof Error ? err.message : "Create failed", "error");
    } finally {
      setBusy(false);
    }
  }

  if (!canManage) {
    return <p className="p-4 text-sm">Missing permission: marketing.manage</p>;
  }

  return (
    <div>
      <Breadcrumbs
        items={[
          { label: "Admin", href: "/dashboard" },
          { label: "Marketing", href: "/marketing" },
          { label: "Automations", href: "/marketing/automations" },
          { label: "New" },
        ]}
      />
      <PageHeader title="Create automation" description="Saved as draft. Activate only after review." />

      <form onSubmit={onSubmit} className="max-w-2xl space-y-4">
        <FormSection title="Campaign information">
          <div className="grid gap-3">
            <Input required placeholder="Name" value={name} onChange={(e) => setName(e.target.value)} />
            <Select value={type} onChange={(e) => setType(e.target.value)}>
              <option value="manual_blast">Manual blast</option>
              <option value="abandoned_cart">Abandoned cart</option>
              <option value="welcome">Welcome</option>
              <option value="post_purchase">Post-purchase</option>
              <option value="reactivation">Reactivation</option>
            </Select>
            <Input
              placeholder="Segment ID (required for blast / reactivation)"
              value={segmentId}
              onChange={(e) => setSegmentId(e.target.value)}
            />
          </div>
        </FormSection>
        <FormSection title="Content">
          <div className="grid gap-3">
            <Input required placeholder="Title template" value={title} onChange={(e) => setTitle(e.target.value)} />
            <TextArea
              required
              rows={5}
              placeholder="Body template"
              value={body}
              onChange={(e) => setBody(e.target.value)}
            />
          </div>
        </FormSection>
        <Button type="submit" disabled={busy}>
          Save draft
        </Button>
      </form>
    </div>
  );
}
