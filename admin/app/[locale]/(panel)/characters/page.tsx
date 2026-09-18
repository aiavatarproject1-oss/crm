"use client";

import { useEffect, useState } from "react";
import { useLocale, useTranslations } from "next-intl";
import { api } from "@/lib/api";
import type { CharacterListItem } from "@/lib/character";

export default function CharactersPage() {
  const t = useTranslations("characters");
  const locale = useLocale();
  const [items, setItems] = useState<CharacterListItem[]>([]);
  const [error, setError] = useState("");

  useEffect(() => {
    api<CharacterListItem[]>("/api/v1/admin/characters")
      .then(setItems)
      .catch((e: Error) => setError(e.message));
  }, []);

  return (
    <>
      <div className="topbar">
        <div>
          <h1>{t("title")}</h1>
          <p className="hint">{t("subtitle")}</p>
        </div>
      </div>
      {error ? <p className="error">{error}</p> : null}
      {items.length === 0 && !error ? <p className="muted">{t("empty")}</p> : null}
      <div className="cards">
        {items.map((c) => (
          <a key={c.id} href={`/${locale}/characters/${c.id}/identity`} className="card">
            <div className="label">{c.slug}</div>
            <div className="value" style={{ fontSize: "1.25rem" }}>
              {c.display_name}
            </div>
            <div className="muted" style={{ marginTop: "0.45rem", fontSize: "0.88rem" }}>
              {c.identity?.age ? `${c.identity.age} · ` : ""}
              {[c.identity?.city, c.identity?.country].filter(Boolean).join(", ") || c.character_id}
            </div>
            <div className="row" style={{ marginTop: "0.7rem" }}>
              <span className={`badge ${c.status === "active" ? "ok" : ""}`}>{c.status}</span>
              <span className="badge">v{c.version}</span>
            </div>
          </a>
        ))}
      </div>
    </>
  );
}
