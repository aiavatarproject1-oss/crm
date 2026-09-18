"use client";

import { FormEvent, useEffect, useMemo, useState } from "react";
import { useTranslations } from "next-intl";
import { api, apiWithMeta } from "@/lib/api";
import { useToast } from "@/lib/toast";

type Role = {
  id: string;
  slug: string;
  name: string;
  description?: string;
  permissions: string[];
  is_system?: boolean;
};

type PermGroup = Record<string, string[]>;

export default function RolesPage() {
  const t = useTranslations("roles");
  const common = useTranslations("common");
  const toast = useToast();
  const [roles, setRoles] = useState<Role[]>([]);
  const [groups, setGroups] = useState<PermGroup>({});
  const [selectedId, setSelectedId] = useState<string | null>(null);
  const [form, setForm] = useState({ slug: "", name: "", description: "", permissions: [] as string[] });
  const [mode, setMode] = useState<"create" | "edit">("create");
  const [error, setError] = useState("");
  const [busy, setBusy] = useState(false);

  async function load() {
    const [list, perms] = await Promise.all([
      apiWithMeta<Role[]>("/api/v1/admin/roles"),
      api<{ groups: PermGroup } | PermGroup>("/api/v1/admin/permissions"),
    ]);
    setRoles(list.data || []);
    const g = (perms as { groups?: PermGroup }).groups || (perms as PermGroup);
    setGroups(g || {});
  }

  useEffect(() => {
    load().catch((e: Error) => setError(e.message));
  }, []);

  const selected = useMemo(() => roles.find((r) => r.id === selectedId) || null, [roles, selectedId]);

  function togglePerm(p: string) {
    setForm((f) => ({
      ...f,
      permissions: f.permissions.includes(p) ? f.permissions.filter((x) => x !== p) : [...f.permissions, ...[p]],
    }));
  }

  function startCreate() {
    setMode("create");
    setSelectedId(null);
    setForm({ slug: "", name: "", description: "", permissions: [] });
    setError("");
  }

  function startEdit(role: Role) {
    setMode("edit");
    setSelectedId(role.id);
    setForm({
      slug: role.slug,
      name: role.name,
      description: role.description || "",
      permissions: [...(role.permissions || [])],
    });
    setError("");
  }

  async function onSubmit(e: FormEvent) {
    e.preventDefault();
    setBusy(true);
    setError("");
    try {
      if (mode === "create") {
        await api("/api/v1/admin/roles", { method: "POST", json: form });
        toast.success(t("created"));
        startCreate();
      } else if (selectedId) {
        await api(`/api/v1/admin/roles/${selectedId}`, {
          method: "PATCH",
          json: {
            name: form.name,
            description: form.description,
            permissions: form.permissions,
          },
        });
        toast.success(t("updated"));
      }
      await load();
    } catch (err) {
      const msg = err instanceof Error ? err.message : common("error");
      setError(msg);
      toast.error(common("error"), msg);
    } finally {
      setBusy(false);
    }
  }

  async function onDelete(role: Role) {
    if (role.is_system) return;
    if (!confirm(t("confirmDelete"))) return;
    try {
      await api(`/api/v1/admin/roles/${role.id}`, { method: "DELETE" });
      if (selectedId === role.id) startCreate();
      await load();
    } catch (err) {
      setError(err instanceof Error ? err.message : common("error"));
    }
  }

  return (
    <>
      <div className="topbar">
        <div>
          <h1>{t("title")}</h1>
          <p className="hint">{t("subtitle")}</p>
        </div>
        <button type="button" className="ghost" onClick={startCreate}>
          {t("create")}
        </button>
      </div>

      <div className="form-grid" style={{ alignItems: "start" }}>
        <div className="role-list">
          {roles.map((role) => (
            <div
              key={role.id}
              className="card role-card"
              style={{
                borderColor: selectedId === role.id ? "rgba(232,165,75,.5)" : undefined,
                cursor: "pointer",
              }}
              onClick={() => startEdit(role)}
            >
              <header>
                <div>
                  <strong>{role.name}</strong>
                  <div className="muted" style={{ fontSize: "0.85rem" }}>
                    {role.slug}
                  </div>
                </div>
                <div className="row">
                  {role.is_system ? <span className="badge warn">{t("system")}</span> : null}
                  <span className="badge">{(role.permissions || []).length} {t("permsCount")}</span>
                </div>
              </header>
              <div className="muted" style={{ fontSize: "0.88rem" }}>
                {role.description || t("noDescription")}
              </div>
              {!role.is_system ? (
                <div className="toolbar">
                  <button
                    type="button"
                    className="ghost danger"
                    onClick={(e) => {
                      e.stopPropagation();
                      void onDelete(role);
                    }}
                  >
                    {common("delete")}
                  </button>
                </div>
              ) : null}
            </div>
          ))}
        </div>

        <form className="card" onSubmit={onSubmit}>
          <h2>{mode === "create" ? t("create") : t("edit")}</h2>
          <div className="form-grid">
            <div className="field">
              <label>{t("slug")}</label>
              <input
                value={form.slug}
                disabled={mode === "edit"}
                onChange={(e) => setForm({ ...form, slug: e.target.value })}
                required
              />
            </div>
            <div className="field">
              <label>{t("name")}</label>
              <input value={form.name} onChange={(e) => setForm({ ...form, name: e.target.value })} required />
            </div>
          </div>
          <div className="field">
            <label>{t("description")}</label>
            <textarea value={form.description} onChange={(e) => setForm({ ...form, description: e.target.value })} />
          </div>

          <div className="field">
            <label>{t("permissions")}</label>
            <div className="perm-grid">
              {Object.entries(groups).map(([group, perms]) => (
                <div key={group} className="perm-group">
                  <h3>{group}</h3>
                  {(perms || []).map((p) => (
                    <label key={p} className="perm-item">
                      <input type="checkbox" checked={form.permissions.includes(p)} onChange={() => togglePerm(p)} />
                      <span>{p}</span>
                    </label>
                  ))}
                </div>
              ))}
            </div>
          </div>

          {error ? <p className="error">{error}</p> : null}
          <div className="toolbar" style={{ marginTop: "0.8rem" }}>
            <button type="submit" disabled={busy || (mode === "edit" && !!selected?.is_system && false)}>
              {common("save")}
            </button>
            {mode === "edit" ? (
              <button type="button" className="ghost" onClick={startCreate}>
                {common("cancel")}
              </button>
            ) : null}
          </div>
          {mode === "edit" && selected?.is_system ? (
            <p className="hint">{t("systemLocked")}</p>
          ) : null}
        </form>
      </div>
    </>
  );
}
