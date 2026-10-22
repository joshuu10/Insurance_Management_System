import { create } from 'zustand';
import type { AuthUser } from '../api/client';
import * as api from '../api/client';

type AuthState = {
  user: AuthUser | null;
  loading: boolean;
  error: string | null;
  login: (email: string, password: string) => Promise<void>;
  loadMe: () => Promise<void>;
  logout: () => Promise<void>;
};

export const useAuth = create<AuthState>((set) => ({
  user: null,
  loading: false,
  error: null,
  login: async (email, password) => {
    set({ loading: true, error: null });
    try {
      const data = await api.login(email, password);
      set({ user: data.user, loading: false });
    } catch {
      set({ error: 'Invalid credentials or API unavailable.', loading: false });
      throw new Error('login_failed');
    }
  },
  loadMe: async () => {
    const token = localStorage.getItem('wabantu_token');
    if (!token) {
      set({ user: null });
      return;
    }
    set({ loading: true });
    try {
      const user = await api.fetchMe();
      set({ user, loading: false });
    } catch {
      localStorage.removeItem('wabantu_token');
      set({ user: null, loading: false });
    }
  },
  logout: async () => {
    await api.logout();
    set({ user: null });
  },
}));
