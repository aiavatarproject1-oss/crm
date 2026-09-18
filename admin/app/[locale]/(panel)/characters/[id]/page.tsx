"use client";

import { useEffect } from "react";
import { useRouter } from "@/i18n/routing";

export default function CharacterIndex({ params }: { params: Promise<{ id: string }> }) {
  const router = useRouter();
  useEffect(() => {
    void params.then((p) => router.replace(`/characters/${p.id}/identity`));
  }, [params, router]);
  return null;
}
