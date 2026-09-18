"use client";

import Echo from "laravel-echo";
import Pusher from "pusher-js";
import { getToken } from "./auth";

let echo: Echo<"reverb"> | null = null;
let echoToken: string | null = null;

export function getEcho(): Echo<"reverb"> | null {
  if (typeof window === "undefined") return null;
  const key = process.env.NEXT_PUBLIC_REVERB_KEY;
  if (!key) return null;

  const token = getToken();
  if (echo && echoToken !== token) {
    echo.disconnect();
    echo = null;
  }

  if (!echo) {
    echoToken = token;
    (window as unknown as { Pusher: typeof Pusher }).Pusher = Pusher;
    echo = new Echo({
      broadcaster: "reverb",
      key,
      wsHost: process.env.NEXT_PUBLIC_REVERB_HOST || "localhost",
      wsPort: Number(process.env.NEXT_PUBLIC_REVERB_PORT || 8080),
      wssPort: Number(process.env.NEXT_PUBLIC_REVERB_PORT || 8080),
      forceTLS: (process.env.NEXT_PUBLIC_REVERB_SCHEME || "http") === "https",
      enabledTransports: ["ws", "wss"],
      authEndpoint: `${process.env.NEXT_PUBLIC_API_URL || "https://resale-displace-banker.ngrok-free.dev"}/api/broadcasting/auth`,
      auth: {
        headers: {
          Authorization: token ? `Bearer ${token}` : "",
          Accept: "application/json",
          "ngrok-skip-browser-warning": "true",
        },
      },
    });
  }

  return echo;
}
