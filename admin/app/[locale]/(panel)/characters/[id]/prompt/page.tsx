"use client";

import { FormEvent, useEffect, useState } from "react";
import { useTranslations } from "next-intl";
import { useCharacter } from "@/lib/character-context";
import { FieldTip } from "@/components/FieldTip";

export default function PromptStudioPage() {
  const t = useTranslations("characters");
  const common = useTranslations("common");
  const { character, saveSection, saving } = useCharacter();
  const [form, setForm] = useState({
    override_enabled: false,
    override_text: "",
  });

  useEffect(() => {
    if (!character) return;
    const p = character.prompt_studio as Record<string, unknown>;
    setForm({
      override_enabled: Boolean(p.override_enabled),
      override_text: String(p.override_text || ""),
    });
  }, [character]);

  async function onSubmit(e: FormEvent) {
    e.preventDefault();
    await saveSection("prompt_studio", {
      override_enabled: form.override_enabled,
      override_text: form.override_text,
      published_version: character?.version || 1,
      draft_version: (character?.version || 1) + (form.override_enabled ? 1 : 0),
    });
  }

  if (!character) return null;

  return (
    <div className="form-grid" style={{ alignItems: "start" }}>
      <form className="card" onSubmit={onSubmit}>
        <h2>{t("sections.prompt")}</h2>
        <p className="hint">{t("hints.prompt")}</p>
        <label className="field inline">
          <input type="checkbox" checked={form.override_enabled} onChange={(e) => setForm({ ...form, override_enabled: e.target.checked })} />
          <FieldTip tipKey="overrideEnabled" inline>{t("fields.overrideEnabled")}</FieldTip>
        </label>
        <div className="field">
          <label><FieldTip tipKey="overrideText">{t("fields.overrideText")}</FieldTip></label>
          <textarea
            className="prompt-box"
            style={{ minHeight: 260 }}
            value={form.override_text}
            onChange={(e) => setForm({ ...form, override_text: e.target.value })}
            disabled={!form.override_enabled}
          />
        </div>
        <button type="submit" disabled={saving}>{common("save")}</button>
      </form>
      <div className="card">
        <h2>
          <FieldTip tipKey="promptPreview">{t("fields.promptPreview")}</FieldTip>
        </h2>
        <p className="hint">{t("hints.preview")}</p>
        <pre className="prompt-box">{character.prompt_preview || ""}</pre>
      </div>
    </div>
  );
}
