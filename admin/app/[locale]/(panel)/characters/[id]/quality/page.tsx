"use client";

import { FormEvent, useEffect, useState } from "react";
import { useTranslations } from "next-intl";
import { useCharacter } from "@/lib/character-context";
import { linesToList, listToLines } from "@/lib/character";
import { FieldTip } from "@/components/FieldTip";

const FLAG_TIPS = {
  vision: "flagVision",
  rag: "flagRag",
  handoff: "flagHandoff",
  explicit_mode: "flagExplicit",
} as const;

export default function QualityPage() {
  const t = useTranslations("characters");
  const common = useTranslations("common");
  const { character, saveSection, saving } = useCharacter();
  const [form, setForm] = useState({
    score_threshold: 0.7,
    checklist: "",
    anti_hallucination_on_vision_fail: true,
    vision_fail_fallback: "",
    vision: true,
    rag: true,
    handoff: true,
    explicit_mode: true,
  });

  useEffect(() => {
    if (!character) return;
    const q = character.rules_quality as Record<string, unknown>;
    const flags = character.feature_flags || {};
    setForm({
      score_threshold: Number(q.score_threshold || 0.7),
      checklist: listToLines(q.checklist),
      anti_hallucination_on_vision_fail: Boolean(q.anti_hallucination_on_vision_fail ?? true),
      vision_fail_fallback: String(q.vision_fail_fallback || ""),
      vision: Boolean(flags.vision ?? true),
      rag: Boolean(flags.rag ?? true),
      handoff: Boolean(flags.handoff ?? true),
      explicit_mode: Boolean(flags.explicit_mode ?? true),
    });
  }, [character]);

  async function onSubmit(e: FormEvent) {
    e.preventDefault();
    await saveSection("rules_quality", {
      score_threshold: Number(form.score_threshold),
      checklist: linesToList(form.checklist),
      anti_hallucination_on_vision_fail: form.anti_hallucination_on_vision_fail,
      vision_fail_fallback: form.vision_fail_fallback,
    });
    await saveSection("feature_flags", {
      vision: form.vision,
      rag: form.rag,
      handoff: form.handoff,
      explicit_mode: form.explicit_mode,
    });
  }

  if (!character) return null;

  return (
    <form className="card" onSubmit={onSubmit}>
      <h2>{t("sections.quality")}</h2>
      <p className="hint">{t("hints.quality")}</p>
      <div className="form-grid">
        <div className="field"><label><FieldTip tipKey="scoreThreshold">{t("fields.scoreThreshold")}</FieldTip></label><input type="number" step="0.05" min="0" max="1" value={form.score_threshold} onChange={(e) => setForm({ ...form, score_threshold: Number(e.target.value) })} /></div>
        <div className="field"><label><FieldTip tipKey="visionFailFallback">{t("fields.visionFailFallback")}</FieldTip></label><input value={form.vision_fail_fallback} onChange={(e) => setForm({ ...form, vision_fail_fallback: e.target.value })} /></div>
      </div>
      <div className="field"><label><FieldTip tipKey="checklist">{t("fields.checklist")}</FieldTip></label><textarea value={form.checklist} onChange={(e) => setForm({ ...form, checklist: e.target.value })} /></div>
      <label className="field inline">
        <input type="checkbox" checked={form.anti_hallucination_on_vision_fail} onChange={(e) => setForm({ ...form, anti_hallucination_on_vision_fail: e.target.checked })} />
        <FieldTip tipKey="antiHallucination" inline>{t("fields.antiHallucination")}</FieldTip>
      </label>

      <h2 style={{ marginTop: "1.2rem" }}>{t("fields.featureFlags")}</h2>
      <div className="row">
        {(["vision", "rag", "handoff", "explicit_mode"] as const).map((key) => (
          <label key={key} className="field inline">
            <input type="checkbox" checked={form[key]} onChange={(e) => setForm({ ...form, [key]: e.target.checked })} />
            <FieldTip tipKey={FLAG_TIPS[key]} inline>{key}</FieldTip>
          </label>
        ))}
      </div>
      <button type="submit" disabled={saving} style={{ marginTop: "0.8rem" }}>{common("save")}</button>
    </form>
  );
}
