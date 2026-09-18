export const TOKEN_KEY = "crm_admin_token";
export const ADMIN_KEY = "crm_admin_profile";
export const SAVED_LOGIN_KEY = "crm_admin_saved_login";

export type AdminProfile = {
  id: string;
  username: string;
  name: string;
  email: string | null;
  is_super_admin: boolean;
  status: string;
  locale: string;
  permissions: string[];
  roles: { id: string; slug: string; name: string }[];
  must_change_password: boolean;
};

export type SavedLogin = {
  username: string;
  password: string;
};

export function getToken(): string | null {
  if (typeof window === "undefined") return null;
  return window.localStorage.getItem(TOKEN_KEY);
}

export function setSession(token: string, admin: AdminProfile): void {
  window.localStorage.setItem(TOKEN_KEY, token);
  window.localStorage.setItem(ADMIN_KEY, JSON.stringify(admin));
}

export function clearSession(): void {
  window.localStorage.removeItem(TOKEN_KEY);
  window.localStorage.removeItem(ADMIN_KEY);
}

export function getCachedAdmin(): AdminProfile | null {
  if (typeof window === "undefined") return null;
  const raw = window.localStorage.getItem(ADMIN_KEY);
  if (!raw) return null;
  try {
    return JSON.parse(raw) as AdminProfile;
  } catch {
    return null;
  }
}

export function can(permission: string): boolean {
  const admin = getCachedAdmin();
  if (!admin) return false;
  if (admin.is_super_admin) return true;
  return admin.permissions.includes(permission);
}

export function getSavedLogin(): SavedLogin | null {
  if (typeof window === "undefined") return null;
  const raw = window.localStorage.getItem(SAVED_LOGIN_KEY);
  if (!raw) return null;
  try {
    return JSON.parse(raw) as SavedLogin;
  } catch {
    return null;
  }
}

export function setSavedLogin(login: SavedLogin | null): void {
  if (typeof window === "undefined") return;
  if (!login) {
    window.localStorage.removeItem(SAVED_LOGIN_KEY);
    return;
  }
  window.localStorage.setItem(SAVED_LOGIN_KEY, JSON.stringify(login));
}
