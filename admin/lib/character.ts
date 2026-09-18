export type CharacterListItem = {
  id: string;
  tenant_id: string;
  character_id: string;
  slug: string;
  display_name: string;
  status: string;
  version: number;
  identity?: { name?: string; age?: number; city?: string; country?: string };
  updated_at?: string | null;
};

export type CharacterSettings = {
  id: string;
  tenant_id: string;
  character_id: string;
  slug: string;
  display_name: string;
  status: string;
  version: number;
  identity: Record<string, unknown>;
  behavior: Record<string, unknown>;
  identity_defense: Record<string, unknown>;
  handoff: Record<string, unknown>;
  sales_funnel: Record<string, unknown>;
  rules_quality: Record<string, unknown>;
  prompt_studio: Record<string, unknown>;
  feature_flags: Record<string, boolean>;
  prompt_preview?: string;
  updated_at?: string | null;
};

export const CHARACTER_SECTIONS = [
  { key: "identity", path: "identity", labelKey: "identity" },
  { key: "behavior", path: "behavior", labelKey: "behavior" },
  { key: "identity_defense", path: "defense", labelKey: "defense" },
  { key: "handoff", path: "handoff", labelKey: "handoff" },
  { key: "sales_funnel", path: "funnel", labelKey: "funnel" },
  { key: "rules_quality", path: "quality", labelKey: "quality" },
  { key: "prompt_studio", path: "prompt", labelKey: "prompt" },
] as const;

export type CharacterSectionKey = (typeof CHARACTER_SECTIONS)[number]["key"];

export function linesToList(value: string): string[] {
  return value
    .split("\n")
    .map((s) => s.trim())
    .filter(Boolean);
}

export function listToLines(value: unknown): string {
  if (Array.isArray(value)) return value.map(String).join("\n");
  return "";
}
