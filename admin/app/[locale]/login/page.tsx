"use client";

import { FormEvent, useEffect, useState } from "react";
import { useTranslations } from "next-intl";
import { api } from "@/lib/api";
import { getSavedLogin, setSavedLogin, setSession, type AdminProfile } from "@/lib/auth";
import { useRouter } from "@/i18n/routing";
import { useToast } from "@/lib/toast";

export default function LoginPage() {
  const t = useTranslations("auth");
  const app = useTranslations("app");
  const router = useRouter();
  const toast = useToast();
  const [username, setUsername] = useState("");
  const [password, setPassword] = useState("");
  const [remember, setRemember] = useState(false);
  const [error, setError] = useState("");
  const [busy, setBusy] = useState(false);

  useEffect(() => {
    const saved = getSavedLogin();
    if (saved) {
      setUsername(saved.username);
      setPassword(saved.password);
      setRemember(true);
    }
  }, []);

  async function onSubmit(e: FormEvent) {
    e.preventDefault();
    setBusy(true);
    setError("");
    try {
      const data = await api<{ token: string; admin: AdminProfile }>("/api/v1/admin/auth/login", {
        method: "POST",
        json: { username, password, device_name: "admin-web" },
      });
      setSession(data.token, data.admin);
      setSavedLogin(remember ? { username, password } : null);
      toast.success(t("welcome"), data.admin.name || data.admin.username);
      router.replace("/");
    } catch (err) {
      const msg = err instanceof Error ? err.message : t("failed");
      setError(msg);
      toast.error(t("failed"), msg);
    } finally {
      setBusy(false);
    }
  }

  return (
    <div className="login">
      <form className="login-card" onSubmit={onSubmit}>
        <div className="brand">
          <span className="brand-mark">{app("name")}</span>
        </div>
        <h1>{t("title")}</h1>
        <div className="field">
          <label htmlFor="username">{t("username")}</label>
          <input id="username" autoComplete="username" value={username} onChange={(e) => setUsername(e.target.value)} />
        </div>
        <div className="field">
          <label htmlFor="password">{t("password")}</label>
          <input
            id="password"
            type="password"
            autoComplete={remember ? "current-password" : "off"}
            value={password}
            onChange={(e) => setPassword(e.target.value)}
          />
        </div>
        <label className="field inline">
          <input type="checkbox" checked={remember} onChange={(e) => setRemember(e.target.checked)} />
          <span>{t("remember")}</span>
        </label>
        {error ? <p className="error">{error}</p> : null}
        <button type="submit" disabled={busy} style={{ width: "100%", marginTop: "0.6rem" }}>
          {t("submit")}
        </button>
      </form>
    </div>
  );
}
