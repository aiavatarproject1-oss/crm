"use client";

import { FormEvent, useEffect, useState } from "react";
import { useTranslations } from "next-intl";
import { useCharacter } from "@/lib/character-context";
import { linesToList, listToLines } from "@/lib/character";
import { FieldTip } from "@/components/FieldTip";

export default function IdentityPage() {
  const t = useTranslations("characters");
  const common = useTranslations("common");
  const { character, saveSection, saving } = useCharacter();
  const [form, setForm] = useState({
    name: "",
    age: 27,
    city: "",
    country: "",
    languages: "",
    bio: "",
    backstory: "",
    canon_facts: "",
    never_says: "",
    explicit_level: "explicit",
    allowed_explicit_words: "",
  });

  useEffect(() => {
    if (!character) return;
    const i = character.identity as Record<string, unknown>;
    setForm({
      name: String(i.name || ""),
      age: Number(i.age || 27),
      city: String(i.city || ""),
      country: String(i.country || ""),
      languages: Array.isArray(i.languages) ? (i.languages as string[]).join(", ") : "",
      bio: String(i.bio || ""),
      backstory: String(i.backstory || ""),
      canon_facts: listToLines(i.canon_facts),
      never_says: listToLines(i.never_says),
      explicit_level: String(i.explicit_level || "suggestive"),
      allowed_explicit_words: Array.isArray(i.allowed_explicit_words)
        ? (i.allowed_explicit_words as string[]).join(", ")
        : "",
    });
  }, [character]);

  async function onSubmit(e: FormEvent) {
    e.preventDefault();
    await saveSection("identity", {
      name: form.name,
      age: Number(form.age),
      city: form.city,
      country: form.country,
      languages: form.languages.split(",").map((s) => s.trim()).filter(Boolean),
      bio: form.bio,
      backstory: form.backstory,
      canon_facts: linesToList(form.canon_facts),
      never_says: linesToList(form.never_says),
      explicit_level: form.explicit_level,
      allowed_explicit_words: form.allowed_explicit_words.split(",").map((s) => s.trim()).filter(Boolean),
    });
  }

  if (!character) return null;

  return (
    <form className="card" onSubmit={onSubmit}>
      <h2>{t("sections.identity")}</h2>
      <p className="hint">{t("hints.identity")}</p>
      <div className="form-grid three">
        <div className="field"><label><FieldTip tipKey="name">{t("fields.name")}</FieldTip></label><input value={form.name} onChange={(e) => setForm({ ...form, name: e.target.value })} /></div>
        <div className="field"><label><FieldTip tipKey="age">{t("fields.age")}</FieldTip></label><input type="number" value={form.age} onChange={(e) => setForm({ ...form, age: Number(e.target.value) })} /></div>
        <div className="field"><label><FieldTip tipKey="languages">{t("fields.languages")}</FieldTip></label><input value={form.languages} onChange={(e) => setForm({ ...form, languages: e.target.value })} placeholder="en, fa" /></div>
        <div className="field"><label><FieldTip tipKey="city">{t("fields.city")}</FieldTip></label><input value={form.city} onChange={(e) => setForm({ ...form, city: e.target.value })} /></div>
        <div className="field"><label><FieldTip tipKey="country">{t("fields.country")}</FieldTip></label><input value={form.country} onChange={(e) => setForm({ ...form, country: e.target.value })} /></div>
        <div className="field">
          <label><FieldTip tipKey="explicitLevel">{t("fields.explicitLevel")}</FieldTip></label>
          <select value={form.explicit_level} onChange={(e) => setForm({ ...form, explicit_level: e.target.value })}>
            <option value="sfw">SFW</option>
            <option value="suggestive">Suggestive</option>
            <option value="explicit">Explicit</option>
          </select>
        </div>
      </div>
      <div className="field"><label><FieldTip tipKey="bio">{t("fields.bio")}</FieldTip></label><textarea value={form.bio} onChange={(e) => setForm({ ...form, bio: e.target.value })} /></div>
      <div className="field"><label><FieldTip tipKey="backstory">{t("fields.backstory")}</FieldTip></label><textarea value={form.backstory} onChange={(e) => setForm({ ...form, backstory: e.target.value })} /></div>
      <div className="form-grid">
        <div className="field"><label><FieldTip tipKey="canonFacts">{t("fields.canonFacts")}</FieldTip></label><textarea value={form.canon_facts} onChange={(e) => setForm({ ...form, canon_facts: e.target.value })} /></div>
        <div className="field"><label><FieldTip tipKey="neverSays">{t("fields.neverSays")}</FieldTip></label><textarea value={form.never_says} onChange={(e) => setForm({ ...form, never_says: e.target.value })} /></div>
      </div>
      <div className="field"><label><FieldTip tipKey="allowedWords">{t("fields.allowedWords")}</FieldTip></label><input value={form.allowed_explicit_words} onChange={(e) => setForm({ ...form, allowed_explicit_words: e.target.value })} /></div>
      <button type="submit" disabled={saving}>{common("save")}</button>
    </form>
  );
}
