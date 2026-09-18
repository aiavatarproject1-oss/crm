"use client";

import { useEffect, useState } from "react";
import { useParams } from "next/navigation";
import { useTranslations } from "next-intl";
import { api, apiWithMeta } from "@/lib/api";
import { getEcho } from "@/lib/echo";
import { TehranTime } from "@/lib/datetime";

type Message = {
  id: string;
  role?: string;
  direction?: string;
  content: string;
  content_type?: string;
  created_at?: string;
};

export default function ConversationDetailPage() {
  const t = useTranslations("conversations");
  const params = useParams<{ id: string }>();
  const [messages, setMessages] = useState<Message[]>([]);

  async function load() {
    const res = await apiWithMeta<Message[]>(`/api/v1/admin/conversations/${params.id}/messages?per_page=200`);
    setMessages(res.data || []);
  }

  useEffect(() => {
    load().catch(() => setMessages([]));
  }, [params.id]);

  useEffect(() => {
    const echo = getEcho();
    if (!echo) return;
    const channel = echo.private(`admin.conversations.${params.id}`);
    channel.listen(".message.created", () => {
      load().catch(() => undefined);
    });
    return () => {
      echo.leave(`admin.conversations.${params.id}`);
    };
  }, [params.id]);

  return (
    <>
      <div className="topbar">
        <h1>{t("messages")}</h1>
      </div>
      <div className="chat">
        {messages.map((m) => {
          const outgoing = m.direction === "outgoing" || m.role === "assistant" || m.role === "ai";
          return (
            <div key={m.id} className={`bubble ${outgoing ? "ai" : "user"}`}>
              <div>{m.content}</div>
              <div style={{ marginTop: 6 }}><TehranTime value={m.created_at} /></div>
            </div>
          );
        })}
      </div>
    </>
  );
}
