"use client";

import { createContext, useCallback, useContext, useEffect, useState } from "react";
import { api } from "@/lib/api";
import type { CharacterSettings } from "@/lib/character";
import { useToast } from "@/lib/toast";

type Ctx = {
  character: CharacterSettings | null;
  loading: boolean;
  error: string;
  reload: () => Promise<void>;
  saveSection: (section: string, data: Record<string, unknown>) => Promise<void>;
  saving: boolean;
};

const CharacterCtx = createContext<Ctx | null>(null);

export function useCharacter(): Ctx {
  const ctx = useContext(CharacterCtx);
  if (!ctx) throw new Error("useCharacter must be used under CharacterProvider");
  return ctx;
}

export function CharacterProvider({
  id,
  children,
}: {
  id: string;
  children: React.ReactNode;
}) {
  const toast = useToast();
  const [character, setCharacter] = useState<CharacterSettings | null>(null);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState("");
  const [saving, setSaving] = useState(false);

  const reload = useCallback(async () => {
    if (!id) return;
    setLoading(true);
    try {
      const data = await api<CharacterSettings>(`/api/v1/admin/characters/${id}`);
      setCharacter(data);
      setError("");
    } catch (e) {
      setError(e instanceof Error ? e.message : "Error");
    } finally {
      setLoading(false);
    }
  }, [id]);

  useEffect(() => {
    void reload();
  }, [reload]);

  async function saveSection(section: string, data: Record<string, unknown>) {
    if (!id) return;
    setSaving(true);
    try {
      const updated = await api<CharacterSettings>(`/api/v1/admin/characters/${id}`, {
        method: "PATCH",
        json: { section, data },
      });
      setCharacter(updated);
      toast.success("Saved", `v${updated.version} · ${section.replaceAll("_", " ")}`);
    } catch (e) {
      const msg = e instanceof Error ? e.message : "Error";
      setError(msg);
      toast.error("Save failed", msg);
    } finally {
      setSaving(false);
    }
  }

  return (
    <CharacterCtx.Provider value={{ character, loading, error, reload, saveSection, saving }}>
      {children}
    </CharacterCtx.Provider>
  );
}
