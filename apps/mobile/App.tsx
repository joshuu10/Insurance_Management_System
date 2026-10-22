import { useEffect, useMemo, useState } from 'react';
import {
  ActivityIndicator,
  SafeAreaView,
  ScrollView,
  StyleSheet,
  View,
} from 'react-native';
import { StatusBar } from 'expo-status-bar';
import {
  Button,
  MD3LightTheme,
  PaperProvider,
  Text,
  TextInput,
  Banner,
  Chip,
} from 'react-native-paper';
import * as SecureStore from 'expo-secure-store';

const API_BASE = process.env.EXPO_PUBLIC_API_URL ?? 'http://127.0.0.1:8000/api/v1';

const theme = {
  ...MD3LightTheme,
  colors: {
    ...MD3LightTheme.colors,
    primary: '#0B1F3B',
    secondary: '#2563EB',
    tertiary: '#0D9488',
    background: '#F6F8FC',
    error: '#DC2626',
  },
  roundness: 12,
};

type AuthUser = {
  id: string;
  name: string;
  email: string;
  roles: string[];
  organization?: { legal_name: string; intermediary_type: string } | null;
};

export default function App() {
  const [email, setEmail] = useState('admin@demo.wabantu.local');
  const [password, setPassword] = useState('Password1!');
  const [user, setUser] = useState<AuthUser | null>(null);
  const [error, setError] = useState<string | null>(null);
  const [busy, setBusy] = useState(false);
  const [apiStatus, setApiStatus] = useState<'checking' | 'ok' | 'down'>('checking');
  const [edition, setEdition] = useState<string>('—');

  useEffect(() => {
    void (async () => {
      try {
        const res = await fetch(`${API_BASE}/health`);
        if (!res.ok) throw new Error('bad status');
        const data = (await res.json()) as { edition?: string };
        setApiStatus('ok');
        setEdition(data.edition ?? '—');
      } catch {
        setApiStatus('down');
      }
    })();
  }, []);

  const greeting = useMemo(() => {
    if (!user) return null;
    return `${user.name} · ${(user.roles ?? []).join(', ')}`;
  }, [user]);

  const login = async () => {
    setBusy(true);
    setError(null);
    try {
      const controller = new AbortController();
      const timer = setTimeout(() => controller.abort(), 15000);
      const res = await fetch(`${API_BASE}/auth/login`, {
        method: 'POST',
        headers: { Accept: 'application/json', 'Content-Type': 'application/json' },
        body: JSON.stringify({ email, password, device_name: 'mobile' }),
        signal: controller.signal,
      });
      clearTimeout(timer);

      if (!res.ok) {
        const body = await res.json().catch(() => ({}));
        if (res.status === 429) {
          throw new Error('Too many login attempts. Wait a minute and retry.');
        }
        throw new Error(body.message || 'Login failed — check email/password.');
      }
      const data = (await res.json()) as { token: string; user: AuthUser };
      await SecureStore.setItemAsync('wabantu_token', data.token);
      setUser(data.user);
      setApiStatus('ok');
    } catch (e) {
      const msg = e instanceof Error ? e.message : 'Unable to sign in.';
      if (msg.includes('abort') || msg.includes('Network')) {
        setError('Network error. Confirm API is reachable from this device (not only localhost on emulator).');
        setApiStatus('down');
      } else {
        setError(msg);
      }
    } finally {
      setBusy(false);
    }
  };

  const logout = async () => {
    const token = await SecureStore.getItemAsync('wabantu_token');
    try {
      if (token) {
        await fetch(`${API_BASE}/auth/logout`, {
          method: 'POST',
          headers: { Accept: 'application/json', Authorization: `Bearer ${token}` },
        });
      }
    } finally {
      await SecureStore.deleteItemAsync('wabantu_token');
      setUser(null);
    }
  };

  return (
    <PaperProvider theme={theme}>
      <SafeAreaView style={styles.safe}>
        <StatusBar style="dark" />
        <Banner visible icon="alert-circle-outline">
          Integrations are sandbox/manual only. No live insurer or TIRA confirmations.
        </Banner>
        <ScrollView contentContainerStyle={styles.container}>
          <Text variant="headlineMedium" style={styles.brand}>
            Wabantu Insurance
          </Text>
          <Text variant="bodyMedium" style={styles.tagline}>
            Field & customer mobile workspace
          </Text>
          <View style={styles.statusRow}>
            <Chip compact icon={apiStatus === 'ok' ? 'check' : 'close'}>
              API {apiStatus}
            </Chip>
            <Chip compact style={{ marginLeft: 8 }}>
              Edition {edition}
            </Chip>
          </View>

          {!user ? (
            <View style={styles.card}>
              <TextInput
                label="Email"
                mode="outlined"
                autoCapitalize="none"
                keyboardType="email-address"
                value={email}
                onChangeText={setEmail}
                style={styles.field}
              />
              <TextInput
                label="Password"
                mode="outlined"
                secureTextEntry
                value={password}
                onChangeText={setPassword}
                style={styles.field}
              />
              {error ? (
                <Text style={styles.error} variant="bodySmall">
                  {error}
                </Text>
              ) : null}
              <Button mode="contained" onPress={() => void login()} loading={busy} disabled={busy || apiStatus === 'down'}>
                Sign in
              </Button>
              {busy ? <ActivityIndicator style={{ marginTop: 12 }} color="#0B1F3B" /> : null}
            </View>
          ) : (
            <View style={styles.card}>
              <Text variant="titleMedium">Signed in</Text>
              <Text variant="bodyMedium">{greeting}</Text>
              <Text variant="bodySmall" style={styles.meta}>
                {user.organization?.legal_name ?? 'Organization pending'} ·{' '}
                {user.organization?.intermediary_type ?? '—'}
              </Text>
              <Button mode="outlined" onPress={() => void logout()} style={{ marginTop: 16 }}>
                Sign out
              </Button>
            </View>
          )}
        </ScrollView>
      </SafeAreaView>
    </PaperProvider>
  );
}

const styles = StyleSheet.create({
  safe: { flex: 1, backgroundColor: '#F6F8FC' },
  container: { padding: 24, gap: 8 },
  brand: { color: '#0B1F3B', fontWeight: '700', marginTop: 12 },
  tagline: { color: '#1F2937', marginBottom: 8 },
  statusRow: { flexDirection: 'row', marginBottom: 12, flexWrap: 'wrap' },
  card: {
    backgroundColor: '#FFFFFF',
    borderRadius: 14,
    padding: 16,
    borderWidth: 1,
    borderColor: '#E5E7EB',
    gap: 8,
  },
  field: { marginBottom: 8, backgroundColor: '#FFFFFF' },
  error: { color: '#DC2626', marginBottom: 8 },
  meta: { color: '#6B7280', marginTop: 8 },
});
