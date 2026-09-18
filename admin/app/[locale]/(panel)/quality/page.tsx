"use client";

import { FormEvent, useEffect, useState } from "react";
import { useLocale, useTranslations } from "next-intl";
import { api, apiWithMeta } from "@/lib/api";
import { useToast } from "@/lib/toast";
import { can, getCachedAdmin } from "@/lib/auth";
import { TehranTime } from "@/lib/datetime";

type QualityRow = {
  id: string;
  character_id: string;
  conversation_id: string;
  user_message: string;
  ai_response: string;
  score: number;
  threshold: number;
  approved: boolean;
  issues: string[];
  reason: string;
  created_at?: string | null;
};

type ThresholdSetting = {
  id: string;
  character_id: string;
  display_name: string;
  slug: string;
  score_threshold: number;
};

function ExpandableText({
  text,
  className,
  lines = 5,
  moreLabel,
  lessLabel,
}: {
  text: string;
  className?: string;
  lines?: number;
  moreLabel: string;
  lessLabel: string;
}) {
  const [open, setOpen] = useState(false);
  const long = text.length > 220 || text.split("\n").length > lines;

  return (
    <div>
      <div className={`${className || ""} ${!open && long ? "is-collapsed" : ""}`.trim()}>
        {text || "—"}
      </div>
      {long ? (
        <button type="button" className="qc-expand" onClick={() => setOpen((v) => !v)}>
          {open ? lessLabel : moreLabel}
        </button>
      ) : null}
    </div>
  );
}

export default function QualityCheckerPage() {
  const t = useTranslations("qualityChecker");
  const locale = useLocale();
  const toast = useToast();
  const admin = getCachedAdmin();
  const canEdit =
    !!admin?.is_super_admin ||
    can("review_queue.manage") ||
    can("ai_settings.manage") ||
    can("character.manage");

  const [settings, setSettings] = useState<ThresholdSetting[]>([]);
  const [characterId, setCharacterId] = useState("");
  const [threshold, setThreshold] = useState(0.7);
  const [approvedFilter, setApprovedFilter] = useState<"all" | "true" | "false">("all");
  const [search, setSearch] = useState("");
  const [items, setItems] = useState<QualityRow[]>([]);
  const [total, setTotal] = useState(0);
  const [saving, setSaving] = useState(false);
  const [loading, setLoading] = useState(true);

  async function loadSettings() {
    const data = await api<ThresholdSetting[]>("/api/v1/admin/quality-checks/settings");
    setSettings(data || []);
    if (!characterId && data?.[0]) {
      setCharacterId(data[0].id);
      setThreshold(data[0].score_threshold);
    } else if (characterId) {
      const current = data?.find((c) => c.id === characterId || c.character_id === characterId);
      if (current) setThreshold(current.score_threshold);
    }
  }

  async function loadChecks(char: string, approved: string, q: string) {
    const params = new URLSearchParams({ per_page: "40" });
    const setting = settings.find((c) => c.id === char || c.character_id === char);
    const characterFilter = setting?.character_id || char;
    if (characterFilter) params.set("character_id", characterFilter);
    if (approved !== "all") params.set("approved", approved);
    if (q.trim()) params.set("search", q.trim());
    const res = await apiWithMeta<QualityRow[]>(`/api/v1/admin/quality-checks?${params}`);
    setItems(res.data || []);
    setTotal(res.meta?.total || 0);
  }

  useEffect(() => {
    setLoading(true);
    Promise.all([loadSettings(), loadChecks(characterId, approvedFilter, search)])
      .catch((e) => toast.error(e instanceof Error ? e.message : "Failed"))
      .finally(() => setLoading(false));
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, []);

  useEffect(() => {
    loadChecks(characterId, approvedFilter, search).catch(() => setItems([]));
  }, [characterId, approvedFilter, search]);

  function onCharacterChange(id: string) {
    setCharacterId(id);
    const current = settings.find((c) => c.id === id || c.character_id === id);
    if (current) setThreshold(current.score_threshold);
  }

  async function onSaveThreshold(e: FormEvent) {
    e.preventDefault();
    if (!canEdit || !characterId) return;
    setSaving(true);
    try {
      const updated = await api<ThresholdSetting>("/api/v1/admin/quality-checks/threshold", {
        method: "PUT",
        json: { character_id: characterId, score_threshold: Number(threshold) },
      });
      toast.success(t("thresholdSaved"));
      setSettings((prev) =>
        prev.map((c) => (c.id === updated.id ? { ...c, score_threshold: updated.score_threshold } : c)),
      );
      setThreshold(updated.score_threshold);
    } catch (err) {
      toast.error(err instanceof Error ? err.message : "Failed");
    } finally {
      setSaving(false);
    }
  }

  return (
    <>
      <div className="topbar">
        <div>
          <h1>{t("title")}</h1>
          <p className="muted" style={{ margin: 0 }}>{t("subtitle")}</p>
        </div>
        <button type="button" className="ghost" onClick={() => loadChecks(characterId, approvedFilter, search)}>
          {t("refresh")}
        </button>
      </div>

      <form className="card" onSubmit={onSaveThreshold} style={{ marginBottom: "1rem" }}>
        <h2>{t("thresholdTitle")}</h2>
        <p className="hint">{t("thresholdHint")}</p>
        <div className="form-grid">
          <div className="field">
            <label>{t("character")}</label>
            <select value={characterId} onChange={(e) => onCharacterChange(e.target.value)}>
              <option value="">{t("allCharacters")}</option>
              {settings.map((c) => (
                <option key={c.id} value={c.id}>
                  {c.display_name} ({c.slug})
                </option>
              ))}
            </select>
          </div>
          <div className="field">
            <label>{t("scoreThreshold")}</label>
            <input
              type="number"
              min={0}
              max={1}
              step={0.05}
              value={threshold}
              disabled={!canEdit || !characterId}
              onChange={(e) => setThreshold(Number(e.target.value))}
            />
          </div>
        </div>
        {canEdit ? (
          <button type="submit" disabled={saving || !characterId} style={{ marginTop: "0.75rem" }}>
            {saving ? "…" : t("saveThreshold")}
          </button>
        ) : (
          <p className="muted">{t("readOnly")}</p>
        )}
      </form>

      <div className="card" style={{ marginBottom: "1rem" }}>
        <div className="row" style={{ gap: "0.75rem", flexWrap: "wrap" }}>
          <input
            placeholder={t("search")}
            value={search}
            onChange={(e) => setSearch(e.target.value)}
            style={{ width: 240 }}
          />
          <select value={approvedFilter} onChange={(e) => setApprovedFilter(e.target.value as typeof approvedFilter)}>
            <option value="all">{t("filterAll")}</option>
            <option value="true">{t("filterApproved")}</option>
            <option value="false">{t("filterRejected")}</option>
          </select>
          <span className="muted">{t("total", { count: total })}</span>
        </div>
      </div>

      {loading ? (
        <p className="muted">…</p>
      ) : items.length === 0 ? (
        <p className="muted">{t("empty")}</p>
      ) : (
        <div className="qc-list">
          {items.map((row) => {
            const pct = Math.max(0, Math.min(100, Math.round(row.score * 100)));
            return (
              <article
                key={row.id}
                className={`qc-card ${row.approved ? "is-approved" : "is-rejected"}`}
              >
                <div className="qc-head">
                  <div className="qc-meta">
                    <span className={`badge ${row.approved ? "ok" : "bad"}`}>
                      {row.approved ? t("approved") : t("rejected")}
                    </span>
                    <span className="qc-score">
                      <strong>{row.score.toFixed(2)}</strong>
                      <span>/ {row.threshold.toFixed(2)}</span>
                      <span className={`qc-meter ${row.approved ? "" : "is-bad"}`} title={`${pct}%`}>
                        <span style={{ width: `${pct}%` }} />
                      </span>
                    </span>
                  </div>
                  <TehranTime value={row.created_at} />
                </div>

                <div className="qc-thread">
                  <div className="qc-bubble user">
                    <div className="qc-role">{t("userMessage")}</div>
                    <ExpandableText
                      text={row.user_message || "—"}
                      className="qc-body"
                      moreLabel={t("showMore")}
                      lessLabel={t("showLess")}
                    />
                  </div>
                  <div className="qc-bubble ai">
                    <div className="qc-role">{t("aiResponse")}</div>
                    <ExpandableText
                      text={row.ai_response || "—"}
                      className="qc-body"
                      moreLabel={t("showMore")}
                      lessLabel={t("showLess")}
                    />
                  </div>
                </div>

                {row.reason ? (
                  <ExpandableText
                    text={row.reason}
                    className="qc-reason"
                    lines={3}
                    moreLabel={t("showMore")}
                    lessLabel={t("showLess")}
                  />
                ) : null}

                {row.issues?.length ? (
                  <div className="qc-issues">
                    {row.issues.map((issue) => (
                      <span key={issue} className="qc-issue">{issue}</span>
                    ))}
                  </div>
                ) : null}

                <div className="qc-foot">
                  <a href={`/${locale}/conversations/${row.conversation_id}`}>{t("openConversation")}</a>
                  <span className="muted" style={{ fontSize: "0.78rem" }}>
                    {row.character_id}
                  </span>
                </div>
              </article>
            );
          })}
        </div>
      )}
    </>
  );
}
