import type { ReactNode } from "react";

/** Asia/Tehran as ISO-like YYYY-MM-DD HH:mm:ss */
export function formatTehranDateTime(value?: string | null): string {
  if (!value) return "—";
  const date = new Date(value);
  if (Number.isNaN(date.getTime())) return String(value);

  const parts = new Intl.DateTimeFormat("en-CA", {
    timeZone: "Asia/Tehran",
    year: "numeric",
    month: "2-digit",
    day: "2-digit",
    hour: "2-digit",
    minute: "2-digit",
    second: "2-digit",
    hour12: false,
  }).formatToParts(date);

  const get = (type: Intl.DateTimeFormatPartTypes) =>
    parts.find((p) => p.type === type)?.value ?? "";

  return `${get("year")}-${get("month")}-${get("day")} ${get("hour")}:${get("minute")}:${get("second")}`;
}

export function TehranTime({
  value,
  className = "",
}: {
  value?: string | null;
  className?: string;
}): ReactNode {
  const text = formatTehranDateTime(value);
  if (text === "—") {
    return <span className={className || undefined}>—</span>;
  }

  return (
    <time className={`time-badge ${className}`.trim()} dateTime={value ?? undefined}>
      {text}
    </time>
  );
}
