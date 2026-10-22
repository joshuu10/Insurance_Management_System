import { useState } from 'react';
import { Link as RouterLink, useNavigate } from 'react-router-dom';
import { Alert, Box, Button, Card, CardContent, Link, Stack, TextField, Typography } from '@mui/material';
import { api } from '../../api/client';
import { useAuth } from '../../store/auth';

export function PortalRegisterPage() {
  const navigate = useNavigate();
  const loadMe = useAuth((s) => s.loadMe);
  const [name, setName] = useState('');
  const [email, setEmail] = useState('');
  const [password, setPassword] = useState('');
  const [passwordConfirmation, setPasswordConfirmation] = useState('');
  const [phone, setPhone] = useState('');
  const [error, setError] = useState<string | null>(null);
  const [busy, setBusy] = useState(false);

  const submit = async (e: React.FormEvent) => {
    e.preventDefault();
    setBusy(true);
    setError(null);
    try {
      const { data } = await api.post('/portal/auth/register', {
        name,
        email,
        password,
        password_confirmation: passwordConfirmation,
        phone_e164: phone || null,
      });
      localStorage.setItem('wabantu_token', data.token);
      await loadMe();
      navigate('/portal');
    } catch {
      setError('Registration failed — email may already be in use.');
    } finally {
      setBusy(false);
    }
  };

  return (
    <Box sx={{ minHeight: '100vh', display: 'grid', placeItems: 'center', px: 2, background: 'linear-gradient(160deg, #0B1F3B 0%, #143356 45%, #F6F8FC 45%)' }}>
      <Card sx={{ width: '100%', maxWidth: 460, border: '1px solid', borderColor: 'divider' }}>
        <CardContent sx={{ p: 4 }}>
          <Stack spacing={2} component="form" onSubmit={submit}>
            <Typography variant="h5" color="primary">
              Create customer account
            </Typography>
            <Typography variant="body2" color="text.secondary">
              Enrol for portal access. Identity verification and insurer KYC may still apply.
            </Typography>
            {error && <Alert severity="error">{error}</Alert>}
            <TextField label="Full name" value={name} onChange={(e) => setName(e.target.value)} required fullWidth />
            <TextField label="Email" type="email" value={email} onChange={(e) => setEmail(e.target.value)} required fullWidth />
            <TextField label="Phone (E.164)" value={phone} onChange={(e) => setPhone(e.target.value)} fullWidth />
            <TextField label="Password" type="password" value={password} onChange={(e) => setPassword(e.target.value)} required fullWidth />
            <TextField
              label="Confirm password"
              type="password"
              value={passwordConfirmation}
              onChange={(e) => setPasswordConfirmation(e.target.value)}
              required
              fullWidth
            />
            <Button type="submit" variant="contained" disabled={busy}>
              Register
            </Button>
            <Link component={RouterLink} to="/login" variant="body2">
              Already have an account? Sign in
            </Link>
          </Stack>
        </CardContent>
      </Card>
    </Box>
  );
}
