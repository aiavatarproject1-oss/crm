"use client";

import { useEffect, useState } from "react";
import { useTranslations } from "next-intl";
import { apiWithMeta } from "@/lib/api";
import { TehranTime } from "@/lib/datetime";

type Row = {
  id: string;
  actor_username?: string;
  action: string;
  target_type?: string;
  target_id?: string;
  created_at?: string;
};

export default function AuditPage() {
  const t = useTranslations("logs");
  const [items, setItems] = useState<Row[]>([]);

  useEffect(() => {
    apiWithMeta<Row[]>("/api/v1/admin/logs/audit?per_page=80")
      .then((r) => setItems(r.data || []))
      .catch(() => setItems([]));
  }, []);

  return (
    <>
      <div className="topbar"><h1>{t("auditTitle")}</h1></div>
      {items.length === 0 ? <p className="muted">{t("empty")}</p> : (
        <table className="table">
          <thead>
            <tr>
              <th>{t("actor")}</th>
              <th>{t("action")}</th>
              <th>{t("target")}</th>
              <th></th>
            </tr>
          </thead>
          <tbody>
            {items.map((row) => (
              <tr key={row.id}>
                <td>{row.actor_username || "—"}</td>
                <td>{row.action}</td>
                <td>{row.target_type} {row.target_id}</td>
                <td><TehranTime value={row.created_at} /></td>
              </tr>
            ))}
          </tbody>
        </table>
      )}
    </>
  );
}
