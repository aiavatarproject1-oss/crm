"use client";

import { useEffect, useId, useRef, useState, type ReactNode } from "react";
import { useTranslations } from "next-intl";

type Props = {
  tipKey: string;
  children: ReactNode;
  inline?: boolean;
};

/**
 * Label + click/hover tip: short lesson + concrete example.
 */
export function FieldTip({ tipKey, children, inline = false }: Props) {
  const t = useTranslations("characters.tips");
  const [open, setOpen] = useState(false);
  const rootRef = useRef<HTMLSpanElement>(null);
  const tipId = useId();

  useEffect(() => {
    if (!open) return;
    function onDoc(e: MouseEvent) {
      if (!rootRef.current?.contains(e.target as Node)) setOpen(false);
    }
    function onKey(e: KeyboardEvent) {
      if (e.key === "Escape") setOpen(false);
    }
    document.addEventListener("mousedown", onDoc);
    document.addEventListener("keydown", onKey);
    return () => {
      document.removeEventListener("mousedown", onDoc);
      document.removeEventListener("keydown", onKey);
    };
  }, [open]);

  return (
    <span className={`field-tip ${inline ? "is-inline" : ""}`} ref={rootRef}>
      <span className="field-tip-label">{children}</span>
      <button
        type="button"
        className={`field-tip-btn ${open ? "open" : ""}`}
        aria-expanded={open}
        aria-controls={tipId}
        aria-label="Help"
        onClick={(e) => {
          e.preventDefault();
          e.stopPropagation();
          setOpen((v) => !v);
        }}
      >
        ?
      </button>
      {open ? (
        <div id={tipId} className="field-tip-pop" role="tooltip">
          <p className="field-tip-about">{t(`${tipKey}.about`)}</p>
          <div className="field-tip-example">
            <span className="field-tip-example-label">{t("exampleLabel")}</span>
            <p>{t(`${tipKey}.example`)}</p>
          </div>
        </div>
      ) : null}
    </span>
  );
}
