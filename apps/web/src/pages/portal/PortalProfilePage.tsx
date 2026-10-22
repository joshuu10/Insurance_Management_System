import { useEffect, useState } from 'react';
import { Alert, Button, Stack, TextField, Typography } from '@mui/material';
import { api } from '../../api/client';
import { useAuth } from '../../store/auth';

type Profile = {
  display_name: string;
  email: string | null;
  phone_e164: string | null;
  preferred_locale: string | null;
  national_id_last4: string | null;
  kyc_status: string;
  identity_verification_status: string;
};

export function PortalProfilePage() {
  const loadMe = useAuth((s) => s.loadMe);
  const [profile, setProfile] = useState<Profile | null>(null);
  const [otp, setOtp] = useState('');
  const [sandboxOtp, setSandboxOtp] = useState<string | null>(null);
  const [message, setMessage] = useState<string | null>(null);
  const [error, setError] = useState<string | null>(null);

  const load = async () => {
    const { data } = await api.get<{ data: { customer: Profile } }>('/portal/me');
    setProfile(data.data.customer);
  };

  useEffect(() => {
    void load().catch(() => setError('Unable to load profile.'));
  }, []);

  const save = async () => {
    if (!profile) return;
    setError(null);
    try {
      await api.put('/portal/profile', {
        display_name: profile.display_name,
        phone_e164: profile.phone_e164,
        preferred_locale: profile.preferred_locale,
        national_id_last4: profile.national_id_last4,
      });
      setMessage('Profile saved.');
      await loadMe();
      await load();
    } catch {
      setError('Save failed.');
    }
  };

  const requestOtp = async () => {
    try {
      const { data } = await api.post('/portal/auth/otp/request');
      setSandboxOtp(data.sandbox_otp ?? null);
      setMessage(data.notice);
      await load();
    } catch {
      setError('OTP request failed.');
    }
  };

  const verifyOtp = async () => {
    try {
      const { data } = await api.post('/portal/auth/otp/verify', { code: otp });
      setMessage(data.notice);
      setSandboxOtp(null);
      await load();
    } catch {
      setError('OTP verify failed.');
    }
  };

  if (!profile) {
    return <Typography>Loading…</Typography>;
  }

  return (
    <Stack spacing={2} sx={{ maxWidth: 520 }}>
      <Typography variant="h4">Profile</Typography>
      {error && <Alert severity="error">{error}</Alert>}
      {message && <Alert severity="success">{message}</Alert>}
      <TextField
        label="Display name"
        value={profile.display_name}
        onChange={(e) => setProfile({ ...profile, display_name: e.target.value })}
      />
      <TextField label="Email" value={profile.email ?? ''} disabled />
      <TextField
        label="Phone"
        value={profile.phone_e164 ?? ''}
        onChange={(e) => setProfile({ ...profile, phone_e164: e.target.value })}
      />
      <TextField
        label="Locale"
        value={profile.preferred_locale ?? 'en-TZ'}
        onChange={(e) => setProfile({ ...profile, preferred_locale: e.target.value })}
      />
      <TextField
        label="National ID (last 4)"
        value={profile.national_id_last4 ?? ''}
        onChange={(e) => setProfile({ ...profile, national_id_last4: e.target.value })}
        slotProps={{ htmlInput: { maxLength: 4 } }}
      />
      <Typography variant="body2" color="text.secondary">
        KYC: {profile.kyc_status} · Identity: {profile.identity_verification_status}
      </Typography>
      <Button variant="contained" onClick={() => void save()} sx={{ alignSelf: 'flex-start' }}>
        Save profile
      </Button>

      <Typography variant="h6">Identity verification (OTP sandbox)</Typography>
      {sandboxOtp && <Alert severity="warning">Sandbox OTP: {sandboxOtp}</Alert>}
      <Stack direction="row" spacing={1}>
        <Button variant="outlined" onClick={() => void requestOtp()}>
          Request OTP
        </Button>
        <TextField size="small" label="Code" value={otp} onChange={(e) => setOtp(e.target.value)} />
        <Button variant="contained" onClick={() => void verifyOtp()}>
          Verify
        </Button>
      </Stack>
    </Stack>
  );
}
