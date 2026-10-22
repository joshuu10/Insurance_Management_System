import axios from 'axios';

const API_BASE = import.meta.env.VITE_API_URL ?? 'http://127.0.0.1:8000/api/v1';

export const api = axios.create({
  baseURL: API_BASE,
  headers: {
    Accept: 'application/json',
    'Content-Type': 'application/json',
  },
  // Bearer tokens in Authorization — no cookie credentials needed for Phase 1/2.
  withCredentials: false,
});

api.interceptors.request.use((config) => {
  const token = localStorage.getItem('wabantu_token');
  if (token) {
    config.headers.Authorization = `Bearer ${token}`;
  }
  return config;
});

export type AuthUser = {
  id: string;
  name: string;
  email: string;
  roles: string[];
  permissions: string[];
  is_portal?: boolean;
  customer_id?: string | null;
  customer?: {
    id: string;
    display_name: string;
    email?: string | null;
  } | null;
  organization?: {
    id: string;
    legal_name: string;
    intermediary_type: string;
    licence_number: string | null;
  } | null;
  branch?: { id: string; name: string } | null;
};

export async function login(email: string, password: string) {
  const { data } = await api.post<{ token: string; user: AuthUser }>('/auth/login', {
    email,
    password,
    device_name: 'web',
  });
  localStorage.setItem('wabantu_token', data.token);
  return data;
}

export async function fetchMe() {
  const { data } = await api.get<{ data: AuthUser }>('/me');
  return data.data;
}

export async function logout() {
  try {
    await api.post('/auth/logout');
  } finally {
    localStorage.removeItem('wabantu_token');
  }
}

export async function health() {
  const { data } = await api.get<{ status: string; edition: string; app: string }>('/health');
  return data;
}
