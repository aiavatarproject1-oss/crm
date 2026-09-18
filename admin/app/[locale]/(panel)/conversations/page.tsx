"use client";

import { useEffect, useState } from "react";
import { useLocale, useTranslations } from "next-intl";
import { apiWithMeta } from "@/lib/api";
import { getEcho } from "@/lib/echo";
import { TehranTime } from "@/lib/datetime";

type Conversation = {
  id: string;
  platform: string;
  status: string;
  user?: { username?: string | null };
  last_activity_at?: string;
  last_message?: { content?: string };
  is_live?: boolean;
};

export default function ConversationsPage() {
  const t = useTranslations("conversations");
  const locale = useLocale();
  const [items, setItems] = useState<Conversation[]>([]);
  const [liveOnly, setLiveOnly] = useState(false);
  const [search, setSearch] = useState("");

  async function load(live: boolean, q: string) {
    const params = new URLSearchParams({ per_page: "50" });
    if (live) params.set("active_within_minutes", "5");
    if (q) params.set("search", q);
    const res = await apiWithMeta<Conversation[]>(`/api/v1/admin/conversations?${params}`);
    setItems(res.data || []);
  }

  useEffect(() => {
    load(liveOnly, search).catch(() => setItems([]));
  }, [liveOnly, search]);

  useEffect(() => {
    const echo = getEcho();
    if (!echo) return;
    const channel = echo.private("admin.conversations");
    channel.listen(".conversation.updated", () => {
      load(liveOnly, search).catch(() => undefined);
    });
    return () => {
      echo.leave("admin.conversations");
    };
  }, [liveOnly, search]);

  return (
    <>
      <div className="topbar">
        <h1>{t("title")}</h1>
        <div className="row">
          <input placeholder={t("search")} value={search} onChange={(e) => setSearch(e.target.value)} style={{ width: 220 }} />
          <button type="button" className={liveOnly ? "" : "ghost"} onClick={() => setLiveOnly((v) => !v)}>
            {t("live")}
          </button>
        </div>
      </div>
      {items.length === 0 ? (
        <p className="muted">{t("empty")}</p>
      ) : (
        <table className="table">
          <thead>
            <tr>
              <th>{t("platform")}</th>
              <th>User</th>
              <th>{t("status")}</th>
              <th>{t("updated")}</th>
            </tr>
          </thead>
          <tbody>
            {items.map((c) => (
              <tr key={c.id}>
                <td>
                  <a href={`/${locale}/conversations/${c.id}`}>{c.platform}</a>
                  {c.is_live ? <span className="badge live">{t("liveBadge")}</span> : null}
                </td>
                <td>{c.user?.username || "—"}</td>
                <td>{c.status}</td>
                <td><TehranTime value={c.last_activity_at} /></td>
              </tr>
            ))}
          </tbody>
        </table>
      )}
    </>
  );
}
