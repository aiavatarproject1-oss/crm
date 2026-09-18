"use client";

import { FormEvent, useEffect, useState } from "react";
import { useTranslations } from "next-intl";
import { api, apiWithMeta } from "@/lib/api";

type AdminRow = {
  id: string;
  username: string;
  name: string;
  email: string | null;
  is_super_admin: boolean;
  status: string;
};

export default function AdminsPage() {
  const t = useTranslations("admins");
  const [items, setItems] = useState<AdminRow[]>([]);
  const [form, setForm] = useState({ username: "", name: "", password: "", is_super_admin: false });
  const [error, setError] = useState("");

  async function load() {
    const res = await apiWithMeta<AdminRow[]>("/api/v1/admin/admins?per_page=50");
    setItems(res.data || []);
  }

  useEffect(() => {
    load().catch((e: Error) => setError(e.message));
  }, []);

  async function onCreate(e: FormEvent) {
    e.preventDefault();
    setError("");
    try {
      await api("/api/v1/admin/admins", { method: "POST", json: form });
      setForm({ username: "", name: "", password: "", is_super_admin: false });
      await load();
    } catch (err) {
      setError(err instanceof Error ? err.message : "Error");
    }
  }

  async function toggle(id: string, status: string) {
    const next = status === "active" ? "suspended" : "active";
    await api(`/api/v1/admin/admins/${id}`, { method: "PATCH", json: { status: next } });
    await load();
  }

  async function remove(id: string) {
    if (!confirm("Delete this admin?")) return;
    await api(`/api/v1/admin/admins/${id}`, { method: "DELETE" });
    await load();
  }

  return (
    <>
      <div className="topbar">
        <h1>{t("title")}</h1>
      </div>
      <form className="card" onSubmit={onCreate} style={{ marginBottom: "1rem" }}>
        <div className="form-grid">
          <input placeholder={t("username")} value={form.username} onChange={(e) => setForm({ ...form, username: e.target.value })} />
          <input placeholder={t("name")} value={form.name} onChange={(e) => setForm({ ...form, name: e.target.value })} />
          <input type="password" placeholder={t("password")} value={form.password} onChange={(e) => setForm({ ...form, password: e.target.value })} />
          <label className="row">
            <input type="checkbox" checked={form.is_super_admin} onChange={(e) => setForm({ ...form, is_super_admin: e.target.checked })} style={{ width: "auto" }} />
            {t("super")}
          </label>
        </div>
        {error ? <p className="error">{error}</p> : null}
        <button type="submit" style={{ marginTop: "0.8rem" }}>{t("save")}</button>
      </form>
      <table className="table">
        <thead>
          <tr>
            <th>{t("username")}</th>
            <th>{t("name")}</th>
            <th>{t("status")}</th>
            <th></th>
          </tr>
        </thead>
        <tbody>
          {items.map((a) => (
            <tr key={a.id}>
              <td>{a.username} {a.is_super_admin ? <span className="badge">super</span> : null}</td>
              <td>{a.name}</td>
              <td>{a.status}</td>
              <td className="row">
                <button type="button" className="ghost" onClick={() => toggle(a.id, a.status)}>
                  {a.status === "active" ? t("suspend") : t("activate")}
                </button>
                {!a.is_super_admin ? (
                  <button type="button" className="danger" onClick={() => remove(a.id)}>{t("delete")}</button>
                ) : null}
              </td>
            </tr>
          ))}
        </tbody>
      </table>
    </>
  );
}
