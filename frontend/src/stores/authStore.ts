import { create } from "zustand";
import { persist } from "zustand/middleware";
import type { Session, Utilisateur } from "../types/auth.types";
interface AuthState {
  session: Session | null; token: string | null; user: Utilisateur | null;
  permissions: string[]; isAuthenticated: boolean;
  setSession: (s: Session) => void; clearSession: () => void;
  can: (p: string) => boolean; canAny: (...ps: string[]) => boolean; canAll: (...ps: string[]) => boolean;
}
export const useAuthStore = create<AuthState>()(persist((set, get) => ({
  session: null, token: null, user: null, permissions: [], isAuthenticated: false,
  setSession: (session) => set({ session, token: session.access_token, user: session.utilisateur, permissions: session.permissions, isAuthenticated: true }),
  clearSession: () => set({ session: null, token: null, user: null, permissions: [], isAuthenticated: false }),
  can: (p) => get().permissions.includes(p),
  canAny: (...ps) => ps.some((p) => get().permissions.includes(p)),
  canAll: (...ps) => ps.every((p) => get().permissions.includes(p)),
}), { name: "uni_session" }));
