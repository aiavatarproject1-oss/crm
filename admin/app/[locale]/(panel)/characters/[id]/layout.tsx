"use client";

import { useEffect, useState } from "react";
import { useLocale, useTranslations } from "next-intl";
import { usePathname } from "@/i18n/routing";
import { CHARACTER_SECTIONS } from "@/lib/character";
import { CharacterProvider, useCharacter } from "@/lib/character-context";

function localeHref(locale: string, href: string): string {
  const path = href === "/" ? "" : href;
  return `/${locale}${path}`;
}

function CharacterShell({ id, children }: { id: string; children: React.ReactNode }) {
  const t = useTranslations("characters");
  const common = useTranslations("common");
  const pathname = usePathname();
  const locale = useLocale();
  const { character, loading, error } = useCharacter();

  return (
    <>
      <div className="character-hero">
        <div>
          <h1>{character?.display_name || t("title")}</h1>
          <div className="meta">
            {character ? (
              <>
                {character.slug} · {character.character_id} · v{character.version}
              </>
            ) : (
              common("loading")
            )}
          </div>
        </div>
        <a href={localeHref(locale, "/characters")} className="ghost">
          ← {t("back")}
        </a>
      </div>

      <div className="section-tabs">
        {CHARACTER_SECTIONS.map((s) => {
          const href = `/characters/${id}/${s.path}`;
          const active = pathname.includes(`/${s.path}`);
          return (
            <a key={s.key} href={localeHref(locale, href)} className={active ? "active" : ""}>
              {t(`sections.${s.labelKey}`)}
            </a>
          );
        })}
      </div>

      {error ? <p className="error">{error}</p> : null}
      {loading && !character ? <p className="muted">{common("loading")}</p> : children}
    </>
  );
}

export default function CharacterLayout({
  children,
  params,
}: {
  children: React.ReactNode;
  params: Promise<{ id: string }>;
}) {
  const [id, setId] = useState("");
  useEffect(() => {
    void params.then((p) => setId(p.id));
  }, [params]);

  if (!id) return null;

  return (
    <CharacterProvider id={id}>
      <CharacterShell id={id}>{children}</CharacterShell>
    </CharacterProvider>
  );
}
