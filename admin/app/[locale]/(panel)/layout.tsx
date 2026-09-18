"use client";

import { useEffect, useMemo, useState } from "react";
import { useLocale, useTranslations } from "next-intl";
import { usePathname, useRouter } from "@/i18n/routing";
import { can, clearSession, getCachedAdmin, getToken } from "@/lib/auth";
import { useToast } from "@/lib/toast";

type NavItem = {
  href: string;
  key: string;
  permission: string | null;
};

const TOP_ITEMS: NavItem[] = [
  { href: "/", key: "dashboard", permission: "dashboard.view" },
  { href: "/characters", key: "characters", permission: "character.view" },
  { href: "/conversations", key: "conversations", permission: "conversations.view" },
  { href: "/quality", key: "quality", permission: "review_queue.view" },
  { href: "/admins", key: "admins", permission: "admins.view" },
  { href: "/roles", key: "roles", permission: "roles.view" },
  { href: "/account", key: "account", permission: null },
];

const LOG_ITEMS: NavItem[] = [
  { href: "/logs/audit", key: "audit", permission: "audit_logs.view" },
  { href: "/logs/pipeline", key: "pipeline", permission: "pipeline_logs.view" },
];

function isActive(pathname: string, href: string): boolean {
  if (href === "/") return pathname === "/";
  return pathname === href || pathname.startsWith(`${href}/`);
}

/** Hard navigation href — works behind ngrok where Next soft-nav can stall. */
function localeHref(locale: string, href: string): string {
  const path = href === "/" ? "" : href;
  return `/${locale}${path}`;
}

export default function Shell({ children }: { children: React.ReactNode }) {
  const t = useTranslations("nav");
  const app = useTranslations("app");
  const auth = useTranslations("auth");
  const pathname = usePathname();
  const router = useRouter();
  const locale = useLocale();
  const toast = useToast();
  const [ready, setReady] = useState(false);
  const [logsOpen, setLogsOpen] = useState(false);
  const [langOpen, setLangOpen] = useState(false);

  useEffect(() => {
    if (!getToken()) {
      router.replace("/login");
      return;
    }
    setReady(true);
  }, [router]);

  useEffect(() => {
    if (pathname.startsWith("/logs")) setLogsOpen(true);
  }, [pathname]);

  const admin = getCachedAdmin();
  const visible = useMemo(() => {
    const allow = (item: NavItem) => {
      if (!item.permission) return true;
      if (admin?.is_super_admin) return true;
      if (item.href === "/quality") {
        return can("review_queue.view") || can("ai_settings.view") || can("character.view");
      }
      return can(item.permission);
    };
    return {
      top: TOP_ITEMS.filter(allow),
      logs: LOG_ITEMS.filter(allow),
    };
  }, [admin]);

  function switchLocale(next: "en" | "fa") {
    setLangOpen(false);
    if (next === locale) return;
    window.location.assign(localeHref(next, pathname));
  }

  function logout() {
    clearSession();
    toast.success(auth("signedOut"));
    window.location.assign(localeHref(locale, "/login"));
  }

  if (!ready) return <div className="main">{app("name")}</div>;

  return (
    <div className="shell">
      <aside className="sidebar">
        <div className="brand">
          <span className="brand-mark">{app("name")}</span>
          <small>{admin?.name || admin?.username}</small>
        </div>

        <nav className="nav">
          {visible.top.map((item) => (
            <a
              key={item.href}
              href={localeHref(locale, item.href)}
              className={`nav-link ${isActive(pathname, item.href) ? "active" : ""}`}
            >
              {t(item.key)}
            </a>
          ))}

          {visible.logs.length > 0 ? (
            <div className="nav-group">
              <button
                type="button"
                className={`nav-group-title ${logsOpen || pathname.startsWith("/logs") ? "open" : ""}`}
                onClick={() => setLogsOpen((v) => !v)}
              >
                <span>{t("logs")}</span>
                <span className="chev">{logsOpen ? "▾" : "▸"}</span>
              </button>
              {logsOpen ? (
                <div className="nav-sub">
                  {visible.logs.map((item) => (
                    <a
                      key={item.href}
                      href={localeHref(locale, item.href)}
                      className={`nav-link ${isActive(pathname, item.href) ? "active" : ""}`}
                    >
                      {t(item.key)}
                    </a>
                  ))}
                </div>
              ) : null}
            </div>
          ) : null}
        </nav>

        <div className="sidebar-foot">
          <div className="lang-panel">
            <button type="button" className="lang-trigger" onClick={() => setLangOpen((v) => !v)} aria-expanded={langOpen}>
              <span className="lang-flag">{locale === "fa" ? "FA" : "EN"}</span>
              <span>{locale === "fa" ? "فارسی" : "English"}</span>
              <span className="chev">{langOpen ? "▴" : "▾"}</span>
            </button>
            {langOpen ? (
              <div className="lang-menu">
                <button type="button" className={locale === "en" ? "active" : ""} onClick={() => switchLocale("en")}>
                  English
                </button>
                <button type="button" className={locale === "fa" ? "active" : ""} onClick={() => switchLocale("fa")}>
                  فارسی
                </button>
              </div>
            ) : null}
          </div>

          <button type="button" className="logout-btn" onClick={logout}>
            <span className="logout-icon">⎋</span>
            {t("logout")}
          </button>
        </div>
      </aside>

      <div className="main" key={pathname}>
        {children}
      </div>
    </div>
  );
}
