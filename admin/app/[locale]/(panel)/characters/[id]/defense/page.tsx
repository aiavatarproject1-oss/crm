"use client";

import { FormEvent, useEffect, useState } from "react";
import { useTranslations } from "next-intl";
import { useCharacter } from "@/lib/character-context";
import { linesToList, listToLines } from "@/lib/character";
import { FieldTip } from "@/components/FieldTip";

export default function DefensePage() {
  const t = useTranslations("characters");
  const common = useTranslations("common");
  const { character, saveSection, saving } = useCharacter();
  const [form, setForm] = useState({
    keywords: "",
    regexes: "",
    llm_classifier_enabled: true,
    suspicion_decay_days: 7,
    handoff_at_step: 4,
    ladder_json: "",
  });

  useEffect(() => {
    if (!character) return;
    const d = character.identity_defense as Record<string, unknown>;
    setForm({
      keywords: listToLines(d.keywords),
      regexes: listToLines(d.regexes),
      llm_classifier_enabled: Boolean(d.llm_classifier_enabled ?? true),
      suspicion_decay_days: Number(d.suspicion_decay_days || 7),
      handoff_at_step: Number(d.handoff_at_step || 4),
      ladder_json: JSON.stringify(d.ladder || [], null, 2),
    });
  }, [character]);

  async function onSubmit(e: FormEvent) {
    e.preventDefault();
    let ladder: unknown[] = [];
    try {
      ladder = JSON.parse(form.ladder_json || "[]");
    } catch {
      return;
    }
    await saveSection("identity_defense", {
      keywords: linesToList(form.keywords),
      regexes: linesToList(form.regexes),
      llm_classifier_enabled: form.llm_classifier_enabled,
      suspicion_decay_days: Number(form.suspicion_decay_days),
      handoff_at_step: Number(form.handoff_at_step),
      ladder,
    });
  }

  if (!character) return null;

  return (
    <form className="card" onSubmit={onSubmit}>
      <h2>{t("sections.defense")}</h2>
      <p className="hint">{t("hints.defense")}</p>
      <div className="form-grid">
        <div className="field"><label><FieldTip tipKey="defenseKeywords">{t("fields.defenseKeywords")}</FieldTip></label><textarea value={form.keywords} onChange={(e) => setForm({ ...form, keywords: e.target.value })} /></div>
        <div className="field"><label><FieldTip tipKey="defenseRegex">{t("fields.defenseRegex")}</FieldTip></label><textarea value={form.regexes} onChange={(e) => setForm({ ...form, regexes: e.target.value })} /></div>
      </div>
      <div className="form-grid three">
        <div className="field"><label><FieldTip tipKey="decayDays">{t("fields.decayDays")}</FieldTip></label><input type="number" value={form.suspicion_decay_days} onChange={(e) => setForm({ ...form, suspicion_decay_days: Number(e.target.value) })} /></div>
        <div className="field"><label><FieldTip tipKey="handoffStep">{t("fields.handoffStep")}</FieldTip></label><input type="number" value={form.handoff_at_step} onChange={(e) => setForm({ ...form, handoff_at_step: Number(e.target.value) })} /></div>
        <label className="field inline" style={{ alignSelf: "end" }}>
          <input type="checkbox" checked={form.llm_classifier_enabled} onChange={(e) => setForm({ ...form, llm_classifier_enabled: e.target.checked })} />
          <FieldTip tipKey="llmClassifier" inline>{t("fields.llmClassifier")}</FieldTip>
        </label>
      </div>
      <div className="field"><label><FieldTip tipKey="ladderJson">{t("fields.ladderJson")}</FieldTip></label><textarea className="prompt-box" style={{ minHeight: 220 }} value={form.ladder_json} onChange={(e) => setForm({ ...form, ladder_json: e.target.value })} /></div>
      <button type="submit" disabled={saving}>{common("save")}</button>
    </form>
  );
}
