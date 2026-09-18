"use client";

import { FormEvent, useEffect, useState } from "react";
import { useTranslations } from "next-intl";
import { useCharacter } from "@/lib/character-context";
import { linesToList, listToLines } from "@/lib/character";
import { FieldTip } from "@/components/FieldTip";

export default function FunnelPage() {
  const t = useTranslations("characters");
  const common = useTranslations("common");
  const { character, saveSection, saving } = useCharacter();
  const [form, setForm] = useState({
    max_pitch_every_n_messages: 6,
    only_after_positive_signal: true,
    forbidden_warmup_phrases: "",
    stages_json: "",
    catalog_json: "",
  });

  useEffect(() => {
    if (!character) return;
    const f = character.sales_funnel as Record<string, unknown>;
    setForm({
      max_pitch_every_n_messages: Number(f.max_pitch_every_n_messages || 6),
      only_after_positive_signal: Boolean(f.only_after_positive_signal ?? true),
      forbidden_warmup_phrases: listToLines(f.forbidden_warmup_phrases),
      stages_json: JSON.stringify(f.stages || [], null, 2),
      catalog_json: JSON.stringify(f.catalog || [], null, 2),
    });
  }, [character]);

  async function onSubmit(e: FormEvent) {
    e.preventDefault();
    let stages: unknown[] = [];
    let catalog: unknown[] = [];
    try {
      stages = JSON.parse(form.stages_json || "[]");
      catalog = JSON.parse(form.catalog_json || "[]");
    } catch {
      return;
    }
    await saveSection("sales_funnel", {
      max_pitch_every_n_messages: Number(form.max_pitch_every_n_messages),
      only_after_positive_signal: form.only_after_positive_signal,
      forbidden_warmup_phrases: linesToList(form.forbidden_warmup_phrases),
      stages,
      catalog,
    });
  }

  if (!character) return null;

  return (
    <form className="card" onSubmit={onSubmit}>
      <h2>{t("sections.funnel")}</h2>
      <p className="hint">{t("hints.funnel")}</p>
      <div className="form-grid">
        <div className="field"><label><FieldTip tipKey="pitchEveryN">{t("fields.pitchEveryN")}</FieldTip></label><input type="number" value={form.max_pitch_every_n_messages} onChange={(e) => setForm({ ...form, max_pitch_every_n_messages: Number(e.target.value) })} /></div>
        <label className="field inline" style={{ alignSelf: "end" }}>
          <input type="checkbox" checked={form.only_after_positive_signal} onChange={(e) => setForm({ ...form, only_after_positive_signal: e.target.checked })} />
          <FieldTip tipKey="onlyPositive" inline>{t("fields.onlyPositive")}</FieldTip>
        </label>
      </div>
      <div className="field"><label><FieldTip tipKey="forbiddenWarmup">{t("fields.forbiddenWarmup")}</FieldTip></label><textarea value={form.forbidden_warmup_phrases} onChange={(e) => setForm({ ...form, forbidden_warmup_phrases: e.target.value })} /></div>
      <div className="form-grid">
        <div className="field"><label><FieldTip tipKey="stagesJson">{t("fields.stagesJson")}</FieldTip></label><textarea className="prompt-box" style={{ minHeight: 200 }} value={form.stages_json} onChange={(e) => setForm({ ...form, stages_json: e.target.value })} /></div>
        <div className="field"><label><FieldTip tipKey="catalogJson">{t("fields.catalogJson")}</FieldTip></label><textarea className="prompt-box" style={{ minHeight: 200 }} value={form.catalog_json} onChange={(e) => setForm({ ...form, catalog_json: e.target.value })} /></div>
      </div>
      <button type="submit" disabled={saving}>{common("save")}</button>
    </form>
  );
}
