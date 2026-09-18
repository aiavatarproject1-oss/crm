import type { NextConfig } from "next";
import createNextIntlPlugin from "next-intl/plugin";

const withNextIntl = createNextIntlPlugin("./i18n/request.ts");

const nextConfig: NextConfig = {
  reactStrictMode: true,
  poweredByHeader: false,
  allowedDevOrigins: [
    "unwarlike-anaconda-appendage.ngrok-free.dev",
    "resale-displace-banker.ngrok-free.dev",
  ],
  async rewrites() {
    const api = process.env.NEXT_PUBLIC_API_URL || "https://resale-displace-banker.ngrok-free.dev";
    return [
      {
        source: "/backend/:path*",
        destination: `${api}/:path*`,
      },
    ];
  },
};

export default withNextIntl(nextConfig);
