"use client";

import { useEffect, useState } from "react";
import { useTranslations } from "next-intl";
import { api } from "@/lib/api";

type Stats = {
  stats: {
    conversations_total?: number;
    conversations_live_60m?: number;
    messages_24h?: number;
  };
  health: { healthy?: boolean; checks?: { name: string; healthy: boolean }[] };
  models: Record<string, string>;
};

export default function DashboardPage() {
  const t = useTranslations("dashboard");
  const [data, setData] = useState<Stats | null>(null);
  const [error, setError] = useState("");

  useEffect(() => {
    api<Stats>("/api/v1/admin/dashboard/stats")
      .then(setData)
      .catch((e: Error) => setError(e.message));
  }, []);

  if (error) return <p className="error">{error}</p>;
  if (!data) return <p className="muted">{t("title")}…</p>;

  return (
    <>
      <div className="topbar">
        <h1>{t("title")}</h1>
      </div>
      <div className="cards">
        <div className="card">
          <div className="label">{t("live")}</div>
          <div className="value">{data.stats?.conversations_live_60m ?? 0}</div>
        </div>
        <div className="card">
          <div className="label">{t("total")}</div>
          <div className="value">{data.stats?.conversations_total ?? 0}</div>
        </div>
        <div className="card">
          <div className="label">{t("messagesToday")}</div>
          <div className="value">{data.stats?.messages_24h ?? 0}</div>
        </div>
      </div>
      <div className="card" style={{ marginTop: "1rem" }}>
        <div className="label">{t("health")}</div>
        <div className="row" style={{ marginTop: "0.6rem" }}>
          {(data.health?.checks || []).map((c) => (
            <span key={c.name} className={`badge ${c.healthy ? "ok" : ""}`}>
              {c.name}
            </span>
          ))}
        </div>
      </div>
      <div className="card" style={{ marginTop: "1rem" }}>
        <div className="label">{t("models")}</div>
        <pre className="muted" style={{ whiteSpace: "pre-wrap" }}>{JSON.stringify(data.models, null, 2)}</pre>
      </div>
    </>
  );
}
