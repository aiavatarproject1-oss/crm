import { defineRouting } from "next-intl/routing";
import { createNavigation } from "next-intl/navigation";

export const locales = ["en", "fa"] as const;
export const localeNames: Record<string, string> = {
  en: "English",
  fa: "فارسی",
  de: "Deutsch",
  ar: "العربية",
  es: "Español",
  fr: "Français",
};

export const routing = defineRouting({
  locales,
  defaultLocale: "en",
  localePrefix: "always",
});

export const { Link, redirect, usePathname, useRouter } = createNavigation(routing);

export function isRtl(locale: string): boolean {
  return locale === "fa" || locale === "ar";
}
