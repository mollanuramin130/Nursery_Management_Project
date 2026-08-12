"use client";

import { FormEvent, useCallback, useEffect, useMemo, useState } from "react";
import { Breadcrumbs } from "@/components/layout/Breadcrumbs";
import { EmptyState, ErrorState, LoadingBlock, PageHeader } from "@/components/feedback/States";
import { Badge } from "@/components/ui/Badge";
import { Button } from "@/components/ui/Button";
import { Input, Select, TextArea } from "@/components/ui/Input";
import { ApiError } from "@/lib/api/client";
import {
  createCategory,
  deleteCategory,
  fetchCategories,
  updateCategory,
} from "@/lib/api/categories";
import { hasPermission } from "@/lib/auth/permissions";
import type { AdminCategory } from "@/lib/types";
import { useAuthStore } from "@/store/auth";
import { useToastStore } from "@/store/toast";

type TreeNode = AdminCategory & { children: TreeNode[] };

function buildTree(rows: AdminCategory[]): TreeNode[] {
  const map = new Map<number, TreeNode>();
  rows.forEach((row) => map.set(row.id, { ...row, children: [] }));
  const roots: TreeNode[] = [];
  map.forEach((node) => {
    if (node.parent_id && map.has(node.parent_id)) {
      map.get(node.parent_id)!.children.push(node);
    } else {
      roots.push(node);
    }
  });
  const sortRec = (nodes: TreeNode[]) => {
    nodes.sort((a, b) => a.sort_order - b.sort_order || a.name.localeCompare(b.name));
    nodes.forEach((n) => sortRec(n.children));
  };
  sortRec(roots);
  return roots;
}

function CategoryRows({
  nodes,
  depth,
  onEdit,
  onDelete,
}: {
  nodes: TreeNode[];
  depth: number;
  onEdit: (row: AdminCategory) => void;
  onDelete: (row: AdminCategory) => void;
}) {
  return (
    <>
      {nodes.map((node) => (
        <CategoryNodeRows
          key={node.id}
          node={node}
          depth={depth}
          onEdit={onEdit}
          onDelete={onDelete}
        />
      ))}
    </>
  );
}

function CategoryNodeRows({
  node,
  depth,
  onEdit,
  onDelete,
}: {
  node: TreeNode;
  depth: number;
  onEdit: (row: AdminCategory) => void;
  onDelete: (row: AdminCategory) => void;
}) {
  return (
    <>
      <tr className="border-b border-[var(--admin-border)]/70">
        <td className="px-3 py-2.5">
          <span style={{ paddingLeft: depth * 16 }} className="inline-flex items-center gap-2">
            {depth > 0 ? <span className="text-[var(--admin-muted)]">└</span> : null}
            <span className="font-medium">{node.name}</span>
          </span>
          <div className="text-xs text-[var(--admin-muted)]" style={{ paddingLeft: depth * 16 }}>
            {node.slug}
          </div>
        </td>
        <td className="px-3 py-2.5 text-sm">{node.parent_id ?? "—"}</td>
        <td className="px-3 py-2.5">
          <Badge tone={node.status === "active" ? "success" : "neutral"}>{node.status}</Badge>
        </td>
        <td className="px-3 py-2.5 text-sm">{node.sort_order}</td>
        <td className="px-3 py-2.5">
          <div className="flex gap-2 text-sm">
            <button
              type="button"
              className="text-[var(--admin-primary)] hover:underline"
              onClick={() => onEdit(node)}
            >
              Edit
            </button>
            <button
              type="button"
              className="text-[var(--admin-danger)] hover:underline"
              onClick={() => onDelete(node)}
            >
              Delete
            </button>
          </div>
        </td>
      </tr>
      <CategoryRows
        nodes={node.children}
        depth={depth + 1}
        onEdit={onEdit}
        onDelete={onDelete}
      />
    </>
  );
}

export default function CategoriesPage() {
  const user = useAuthStore((s) => s.user);
  const canManage = hasPermission(user, "products.write");
  const push = useToastStore((s) => s.push);

  const [rows, setRows] = useState<AdminCategory[]>([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);
  const [editingId, setEditingId] = useState<number | null>(null);
  const [name, setName] = useState("");
  const [slug, setSlug] = useState("");
  const [parentId, setParentId] = useState("");
  const [status, setStatus] = useState("active");
  const [sortOrder, setSortOrder] = useState("0");
  const [description, setDescription] = useState("");
  const [saving, setSaving] = useState(false);

  const tree = useMemo(() => buildTree(rows), [rows]);

  const load = useCallback(async () => {
    if (!canManage) {
      setLoading(false);
      setError("Missing permission: products.write (Admin categories API requires it).");
      return;
    }
    setLoading(true);
    setError(null);
    try {
      const res = await fetchCategories();
      setRows(res.data);
    } catch (err) {
      setError(err instanceof ApiError || err instanceof Error ? err.message : "Failed to load");
    } finally {
      setLoading(false);
    }
  }, [canManage]);

  useEffect(() => {
    void load();
  }, [load]);

  function resetForm() {
    setEditingId(null);
    setName("");
    setSlug("");
    setParentId("");
    setStatus("active");
    setSortOrder("0");
    setDescription("");
  }

  function onEdit(row: AdminCategory) {
    setEditingId(row.id);
    setName(row.name);
    setSlug(row.slug);
    setParentId(row.parent_id ? String(row.parent_id) : "");
    setStatus(row.status);
    setSortOrder(String(row.sort_order ?? 0));
    setDescription("");
  }

  async function onDelete(row: AdminCategory) {
    if (!window.confirm(`Delete category “${row.name}”?`)) return;
    try {
      await deleteCategory(row.id);
      push("Category deleted", "success");
      if (editingId === row.id) resetForm();
      await load();
    } catch (err) {
      push(err instanceof Error ? err.message : "Delete failed", "error");
    }
  }

  async function onSubmit(e: FormEvent) {
    e.preventDefault();
    setSaving(true);
    try {
      const payload = {
        name: name.trim(),
        slug: slug.trim() || undefined,
        parent_id: parentId ? Number(parentId) : null,
        status,
        sort_order: Number(sortOrder || 0),
        description: description || null,
      };
      if (editingId) {
        await updateCategory(editingId, payload);
        push("Category updated", "success");
      } else {
        await createCategory(payload);
        push("Category created", "success");
      }
      resetForm();
      await load();
    } catch (err) {
      push(err instanceof Error ? err.message : "Save failed", "error");
    } finally {
      setSaving(false);
    }
  }

  return (
    <div>
      <Breadcrumbs items={[{ label: "Admin", href: "/dashboard" }, { label: "Categories" }]} />
      <PageHeader
        title="Categories"
        description="Hierarchical category management via Admin API."
      />

      <div className="grid gap-4 xl:grid-cols-[1fr_360px]">
        <div>
          {loading ? <LoadingBlock /> : null}
          {error ? <ErrorState message={error} onRetry={() => void load()} /> : null}
          {!loading && !error && rows.length === 0 ? (
            <EmptyState title="No categories" description="Create the first category." />
          ) : null}
          {!loading && !error && rows.length > 0 ? (
            <div className="overflow-x-auto rounded-[var(--admin-radius-lg)] border border-[var(--admin-border)] bg-white">
              <table className="min-w-full text-left text-sm">
                <thead className="border-b border-[var(--admin-border)] bg-[var(--admin-surface-muted)] text-xs uppercase text-[var(--admin-muted)]">
                  <tr>
                    <th className="px-3 py-2.5 font-medium">Category</th>
                    <th className="px-3 py-2.5 font-medium">Parent ID</th>
                    <th className="px-3 py-2.5 font-medium">Status</th>
                    <th className="px-3 py-2.5 font-medium">Sort</th>
                    <th className="px-3 py-2.5 font-medium">Actions</th>
                  </tr>
                </thead>
                <tbody>
                  <CategoryRows
                    nodes={tree}
                    depth={0}
                    onEdit={onEdit}
                    onDelete={(row) => void onDelete(row)}
                  />
                </tbody>
              </table>
              <p className="px-3 py-2 text-xs text-[var(--admin-muted)]">
                Product count is not returned by GET /admin/categories.
              </p>
            </div>
          ) : null}
        </div>

        {canManage ? (
          <form
            onSubmit={onSubmit}
            className="h-fit space-y-3 rounded-[var(--admin-radius-lg)] border border-[var(--admin-border)] bg-white p-4"
          >
            <h2 className="text-sm font-semibold">
              {editingId ? `Edit category #${editingId}` : "Create category"}
            </h2>
            <Input label="Name" value={name} onChange={(e) => setName(e.target.value)} required />
            <Input label="Slug" value={slug} onChange={(e) => setSlug(e.target.value)} />
            <Select label="Parent" value={parentId} onChange={(e) => setParentId(e.target.value)}>
              <option value="">None (root)</option>
              {rows
                .filter((r) => r.id !== editingId)
                .map((r) => (
                  <option key={r.id} value={r.id}>
                    {r.name}
                  </option>
                ))}
            </Select>
            <Select label="Status" value={status} onChange={(e) => setStatus(e.target.value)}>
              <option value="active">active</option>
              <option value="inactive">inactive</option>
            </Select>
            <Input
              label="Sort order"
              type="number"
              value={sortOrder}
              onChange={(e) => setSortOrder(e.target.value)}
            />
            <TextArea
              label="Description"
              value={description}
              onChange={(e) => setDescription(e.target.value)}
            />
            <div className="flex gap-2">
              <Button type="submit" disabled={saving}>
                {saving ? "Saving…" : editingId ? "Update" : "Create"}
              </Button>
              {editingId ? (
                <Button type="button" variant="secondary" onClick={resetForm}>
                  Cancel
                </Button>
              ) : null}
            </div>
          </form>
        ) : null}
      </div>
    </div>
  );
}
