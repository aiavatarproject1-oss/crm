"use client";

import { FormEvent, useState } from "react";
import { useTranslations } from "next-intl";
import { api } from "@/lib/api";
import { getCachedAdmin } from "@/lib/auth";
import { useToast } from "@/lib/toast";

export default function AccountPage() {
  const t = useTranslations("account");
  const toast = useToast();
  const admin = getCachedAdmin();
  const [current, setCurrent] = useState("");
  const [next, setNext] = useState("");
  const [confirm, setConfirm] = useState("");
  const [error, setError] = useState("");

  async function onSubmit(e: FormEvent) {
    e.preventDefault();
    setError("");
    if (next !== confirm) {
      setError(t("mismatch"));
      toast.error(t("mismatch"));
      return;
    }
    try {
      await api("/api/v1/admin/auth/change-password", {
        method: "POST",
        json: { current_password: current, new_password: next, new_password_confirmation: confirm },
      });
      toast.success(t("saved"));
      setCurrent("");
      setNext("");
      setConfirm("");
    } catch (err) {
      const msg = err instanceof Error ? err.message : "Error";
      setError(msg);
      toast.error(msg);
    }
  }

  return (
    <>
      <div className="topbar"><h1>{t("title")}</h1></div>
      <div className="card" style={{ marginBottom: "1rem" }}>
        <strong>{admin?.name}</strong>
        <div className="muted">{admin?.username}</div>
      </div>
      <form className="card" onSubmit={onSubmit} style={{ maxWidth: 420 }}>
        <div className="field">
          <label>{t("current")}</label>
          <input type="password" value={current} onChange={(e) => setCurrent(e.target.value)} />
        </div>
        <div className="field">
          <label>{t("next")}</label>
          <input type="password" value={next} onChange={(e) => setNext(e.target.value)} />
        </div>
        <div className="field">
          <label>{t("confirm")}</label>
          <input type="password" value={confirm} onChange={(e) => setConfirm(e.target.value)} />
        </div>
        {error ? <p className="error">{error}</p> : null}
        <button type="submit">{t("save")}</button>
      </form>
    </>
  );
}
