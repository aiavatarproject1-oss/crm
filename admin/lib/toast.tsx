"use client";

import {
  createContext,
  useCallback,
  useContext,
  useEffect,
  useMemo,
  useState,
  type ReactNode,
} from "react";

export type ToastKind = "success" | "error" | "info";

type ToastItem = {
  id: string;
  kind: ToastKind;
  title: string;
  detail?: string;
};

type ToastContextValue = {
  push: (kind: ToastKind, title: string, detail?: string) => void;
  success: (title: string, detail?: string) => void;
  error: (title: string, detail?: string) => void;
};

const ToastContext = createContext<ToastContextValue | null>(null);

export function useToast(): ToastContextValue {
  const ctx = useContext(ToastContext);
  if (!ctx) throw new Error("useToast must be used within ToastProvider");
  return ctx;
}

export function ToastProvider({ children }: { children: ReactNode }) {
  const [items, setItems] = useState<ToastItem[]>([]);

  const dismiss = useCallback((id: string) => {
    setItems((prev) => prev.filter((t) => t.id !== id));
  }, []);

  const push = useCallback((kind: ToastKind, title: string, detail?: string) => {
    const id = `${Date.now()}-${Math.random().toString(16).slice(2)}`;
    setItems((prev) => [...prev.slice(-3), { id, kind, title, detail }]);
    window.setTimeout(() => dismiss(id), 3400);
  }, [dismiss]);

  const value = useMemo<ToastContextValue>(
    () => ({
      push,
      success: (title, detail) => push("success", title, detail),
      error: (title, detail) => push("error", title, detail),
    }),
    [push],
  );

  return (
    <ToastContext.Provider value={value}>
      {children}
      <div className="toast-stack" aria-live="polite" aria-relevant="additions">
        {items.map((toast) => (
          <div key={toast.id} className={`toast toast-${toast.kind}`} role="status">
            <div className="toast-body">
              <strong>{toast.title}</strong>
              {toast.detail ? <span>{toast.detail}</span> : null}
            </div>
            <button type="button" className="toast-close" onClick={() => dismiss(toast.id)} aria-label="Dismiss">
              ×
            </button>
          </div>
        ))}
      </div>
    </ToastContext.Provider>
  );
}

/** Optional hook that no-ops outside provider (for early pages). */
export function useToastOptional(): ToastContextValue | null {
  return useContext(ToastContext);
}
