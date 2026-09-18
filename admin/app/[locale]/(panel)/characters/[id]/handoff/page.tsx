"use client";

import { FormEvent, useEffect, useState } from "react";
import { useTranslations } from "next-intl";
import { useCharacter } from "@/lib/character-context";
import { linesToList, listToLines } from "@/lib/character";
import { FieldTip } from "@/components/FieldTip";

export default function HandoffPage() {
  const t = useTranslations("characters");
  const common = useTranslations("common");
  const { character, saveSection, saving } = useCharacter();
  const [form, setForm] = useState({
    threshold: 4,
    support_telegram_ids: "",
    notify_mode: "all",
    silence_mode: "hold",
    hold_message: "",
    panel_deep_link: true,
  });

  useEffect(() => {
    if (!character) return;
    const h = character.handoff as Record<string, unknown>;
    setForm({
      threshold: Number(h.threshold || 4),
      support_telegram_ids: listToLines(h.support_telegram_ids),
      notify_mode: String(h.notify_mode || "all"),
      silence_mode: String(h.silence_mode || "hold"),
      hold_message: String(h.hold_message || ""),
      panel_deep_link: Boolean(h.panel_deep_link ?? true),
    });
  }, [character]);

  async function onSubmit(e: FormEvent) {
    e.preventDefault();
    await saveSection("handoff", {
      threshold: Number(form.threshold),
      support_telegram_ids: linesToList(form.support_telegram_ids),
      notify_mode: form.notify_mode,
      silence_mode: form.silence_mode,
      hold_message: form.hold_message,
      panel_deep_link: form.panel_deep_link,
    });
  }

  if (!character) return null;

  return (
    <form className="card" onSubmit={onSubmit}>
      <h2>{t("sections.handoff")}</h2>
      <p className="hint">{t("hints.handoff")}</p>
      <div className="form-grid three">
        <div className="field"><label><FieldTip tipKey="threshold">{t("fields.threshold")}</FieldTip></label><input type="number" value={form.threshold} onChange={(e) => setForm({ ...form, threshold: Number(e.target.value) })} /></div>
        <div className="field">
          <label><FieldTip tipKey="notifyMode">{t("fields.notifyMode")}</FieldTip></label>
          <select value={form.notify_mode} onChange={(e) => setForm({ ...form, notify_mode: e.target.value })}>
            <option value="all">all</option>
            <option value="round_robin">round_robin</option>
          </select>
        </div>
        <div className="field">
          <label><FieldTip tipKey="silenceMode">{t("fields.silenceMode")}</FieldTip></label>
          <select value={form.silence_mode} onChange={(e) => setForm({ ...form, silence_mode: e.target.value })}>
            <option value="hold">hold</option>
            <option value="silent">silent</option>
          </select>
        </div>
      </div>
      <div className="field"><label><FieldTip tipKey="supportIds">{t("fields.supportIds")}</FieldTip></label><textarea value={form.support_telegram_ids} onChange={(e) => setForm({ ...form, support_telegram_ids: e.target.value })} placeholder={"@amirrezashahbazi9\nhttps://t.me/someone\n123456789"} /></div>
      <div className="field"><label><FieldTip tipKey="holdMessage">{t("fields.holdMessage")}</FieldTip></label><input value={form.hold_message} onChange={(e) => setForm({ ...form, hold_message: e.target.value })} /></div>
      <label className="field inline">
        <input type="checkbox" checked={form.panel_deep_link} onChange={(e) => setForm({ ...form, panel_deep_link: e.target.checked })} />
        <FieldTip tipKey="panelLink" inline>{t("fields.panelLink")}</FieldTip>
      </label>
      <button type="submit" disabled={saving}>{common("save")}</button>
    </form>
  );
}
