"use client";

import { FormEvent, useEffect, useState } from "react";
import { useTranslations } from "next-intl";
import { useCharacter } from "@/lib/character-context";
import { linesToList, listToLines } from "@/lib/character";
import { FieldTip } from "@/components/FieldTip";

export default function BehaviorPage() {
  const t = useTranslations("characters");
  const common = useTranslations("common");
  const { character, saveSection, saving } = useCharacter();
  const [form, setForm] = useState({
    reply_length: "medium",
    base_tone: "",
    reply_language_mode: "match_user",
    max_per_message: 2,
    no_repeat_in_message: true,
    no_repeat_within_n: 3,
    allowed: "",
    probability: 0.45,
    greeting_enabled: true,
    short_reply: true,
    no_selling: true,
    patterns: "",
    time_gap_hours: 12,
  });

  useEffect(() => {
    if (!character) return;
    const b = character.behavior as Record<string, unknown>;
    const emoji = (b.emoji || {}) as Record<string, unknown>;
    const greeting = (b.greeting || {}) as Record<string, unknown>;
    setForm({
      reply_length: String(b.reply_length || "medium"),
      base_tone: String(b.base_tone || ""),
      reply_language_mode: String(b.reply_language_mode || "match_user"),
      max_per_message: Number(emoji.max_per_message || 2),
      no_repeat_in_message: Boolean(emoji.no_repeat_in_message ?? true),
      no_repeat_within_n: Number(emoji.no_repeat_within_n || 3),
      allowed: Array.isArray(emoji.allowed) ? (emoji.allowed as string[]).join(" ") : "",
      probability: Number(emoji.probability || 0.45),
      greeting_enabled: Boolean(greeting.enabled ?? true),
      short_reply: Boolean(greeting.short_reply ?? true),
      no_selling: Boolean(greeting.no_selling ?? true),
      patterns: listToLines(greeting.patterns),
      time_gap_hours: Number(b.time_gap_hours || 12),
    });
  }, [character]);

  async function onSubmit(e: FormEvent) {
    e.preventDefault();
    await saveSection("behavior", {
      reply_length: form.reply_length,
      base_tone: form.base_tone,
      reply_language_mode: form.reply_language_mode,
      time_gap_hours: Number(form.time_gap_hours),
      emoji: {
        max_per_message: Number(form.max_per_message),
        no_repeat_in_message: form.no_repeat_in_message,
        no_repeat_within_n: Number(form.no_repeat_within_n),
        allowed: form.allowed.split(/\s+/).filter(Boolean),
        probability: Number(form.probability),
      },
      greeting: {
        enabled: form.greeting_enabled,
        short_reply: form.short_reply,
        no_selling: form.no_selling,
        patterns: linesToList(form.patterns),
      },
    });
  }

  if (!character) return null;

  return (
    <form className="card" onSubmit={onSubmit}>
      <h2>{t("sections.behavior")}</h2>
      <p className="hint">{t("hints.behavior")}</p>
      <div className="form-grid three">
        <div className="field">
          <label><FieldTip tipKey="replyLength">{t("fields.replyLength")}</FieldTip></label>
          <select value={form.reply_length} onChange={(e) => setForm({ ...form, reply_length: e.target.value })}>
            <option value="short">short</option>
            <option value="medium">medium</option>
            <option value="long">long</option>
          </select>
        </div>
        <div className="field">
          <label><FieldTip tipKey="replyLanguage">{t("fields.replyLanguage")}</FieldTip></label>
          <select value={form.reply_language_mode} onChange={(e) => setForm({ ...form, reply_language_mode: e.target.value })}>
            <option value="match_user">match user</option>
            <option value="en">English</option>
            <option value="fa">Persian</option>
          </select>
        </div>
        <div className="field">
          <label><FieldTip tipKey="timeGap">{t("fields.timeGap")}</FieldTip></label>
          <input type="number" value={form.time_gap_hours} onChange={(e) => setForm({ ...form, time_gap_hours: Number(e.target.value) })} />
        </div>
      </div>
      <div className="field"><label><FieldTip tipKey="baseTone">{t("fields.baseTone")}</FieldTip></label><input value={form.base_tone} onChange={(e) => setForm({ ...form, base_tone: e.target.value })} /></div>

      <h2 style={{ marginTop: "1.2rem" }}>{t("fields.emojiPolicy")}</h2>
      <div className="form-grid three">
        <div className="field"><label><FieldTip tipKey="emojiMax">{t("fields.emojiMax")}</FieldTip></label><input type="number" value={form.max_per_message} onChange={(e) => setForm({ ...form, max_per_message: Number(e.target.value) })} /></div>
        <div className="field"><label><FieldTip tipKey="emojiWithinN">{t("fields.emojiWithinN")}</FieldTip></label><input type="number" value={form.no_repeat_within_n} onChange={(e) => setForm({ ...form, no_repeat_within_n: Number(e.target.value) })} /></div>
        <div className="field"><label><FieldTip tipKey="emojiProbability">{t("fields.emojiProbability")}</FieldTip></label><input type="number" step="0.05" min="0" max="1" value={form.probability} onChange={(e) => setForm({ ...form, probability: Number(e.target.value) })} /></div>
      </div>
      <div className="field"><label><FieldTip tipKey="emojiAllowed">{t("fields.emojiAllowed")}</FieldTip></label><input value={form.allowed} onChange={(e) => setForm({ ...form, allowed: e.target.value })} /></div>
      <label className="field inline">
        <input type="checkbox" checked={form.no_repeat_in_message} onChange={(e) => setForm({ ...form, no_repeat_in_message: e.target.checked })} />
        <FieldTip tipKey="emojiNoRepeat" inline>{t("fields.emojiNoRepeat")}</FieldTip>
      </label>

      <h2 style={{ marginTop: "1.2rem" }}>{t("fields.greetingPolicy")}</h2>
      <label className="field inline">
        <input type="checkbox" checked={form.greeting_enabled} onChange={(e) => setForm({ ...form, greeting_enabled: e.target.checked })} />
        <FieldTip tipKey="greetingEnabled" inline>{t("fields.greetingEnabled")}</FieldTip>
      </label>
      <label className="field inline">
        <input type="checkbox" checked={form.short_reply} onChange={(e) => setForm({ ...form, short_reply: e.target.checked })} />
        <FieldTip tipKey="greetingShort" inline>{t("fields.greetingShort")}</FieldTip>
      </label>
      <label className="field inline">
        <input type="checkbox" checked={form.no_selling} onChange={(e) => setForm({ ...form, no_selling: e.target.checked })} />
        <FieldTip tipKey="greetingNoSell" inline>{t("fields.greetingNoSell")}</FieldTip>
      </label>
      <div className="field"><label><FieldTip tipKey="greetingPatterns">{t("fields.greetingPatterns")}</FieldTip></label><textarea value={form.patterns} onChange={(e) => setForm({ ...form, patterns: e.target.value })} /></div>

      <button type="submit" disabled={saving}>{common("save")}</button>
    </form>
  );
}
