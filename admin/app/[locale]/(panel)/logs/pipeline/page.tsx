"use client";

import { useEffect, useState } from "react";
import { useTranslations } from "next-intl";
import { apiWithMeta } from "@/lib/api";
import { getEcho } from "@/lib/echo";
import { TehranTime } from "@/lib/datetime";

type Row = {
  id: string;
  event: string;
  level: string;
  correlation_id?: string;
  created_at?: string;
  payload?: Record<string, unknown>;
};

export default function PipelineLogPage() {
  const t = useTranslations("logs");
  const [items, setItems] = useState<Row[]>([]);

  async function load() {
    const r = await apiWithMeta<Row[]>("/api/v1/admin/logs/pipeline?per_page=100");
    setItems(r.data || []);
  }

  useEffect(() => {
    load().catch(() => setItems([]));
  }, []);

  useEffect(() => {
    const echo = getEcho();
    if (!echo) return;
    echo.private("admin.pipeline").listen(".pipeline.event", () => {
      load().catch(() => undefined);
    });
    return () => {
      echo.leave("admin.pipeline");
    };
  }, []);

  return (
    <>
      <div className="topbar"><h1>{t("pipelineTitle")}</h1></div>
      {items.length === 0 ? <p className="muted">{t("empty")}</p> : (
        <table className="table">
          <thead>
            <tr>
              <th>{t("level")}</th>
              <th>{t("event")}</th>
              <th></th>
            </tr>
          </thead>
          <tbody>
            {items.map((row) => (
              <tr key={row.id}>
                <td>{row.level}</td>
                <td>
                  {row.event}
                  <div className="muted">{row.correlation_id}</div>
                </td>
                <td><TehranTime value={row.created_at} /></td>
              </tr>
            ))}
          </tbody>
        </table>
      )}
    </>
  );
}
