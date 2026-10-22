import { useState } from 'react';
import { Link as RouterLink } from 'react-router-dom';
import { Alert, Box, Button, Card, CardContent, Link, Stack, TextField, Typography } from '@mui/material';
import { api } from '../../api/client';

export function PortalForgotPasswordPage() {
  const [email, setEmail] = useState('customer@demo.wabantu.local');
  const [token, setToken] = useState('');
  const [password, setPassword] = useState('');
  const [passwordConfirmation, setPasswordConfirmation] = useState('');
  const [message, setMessage] = useState<string | null>(null);
  const [error, setError] = useState<string | null>(null);
  const [sandboxToken, setSandboxToken] = useState<string | null>(null);

  const requestReset = async () => {
    setError(null);
    try {
      const { data } = await api.post('/portal/auth/forgot-password', { email });
      setMessage(data.notice);
      setSandboxToken(data.sandbox_reset_token ?? null);
      if (data.sandbox_reset_token) setToken(data.sandbox_reset_token);
    } catch {
      setError('Could not request reset.');
    }
  };

  const reset = async (e: React.FormEvent) => {
    e.preventDefault();
    setError(null);
    try {
      const { data } = await api.post('/portal/auth/reset-password', {
        email,
        token,
        password,
        password_confirmation: passwordConfirmation,
      });
      setMessage(data.notice);
    } catch {
      setError('Reset failed — check token and password rules.');
    }
  };

  return (
    <Box sx={{ minHeight: '100vh', display: 'grid', placeItems: 'center', px: 2, background: 'linear-gradient(160deg, #0B1F3B 0%, #143356 45%, #F6F8FC 45%)' }}>
      <Card sx={{ width: '100%', maxWidth: 460, border: '1px solid', borderColor: 'divider' }}>
        <CardContent sx={{ p: 4 }}>
          <Stack spacing={2} component="form" onSubmit={reset}>
            <Typography variant="h5" color="primary">
              Reset password
            </Typography>
            {error && <Alert severity="error">{error}</Alert>}
            {message && <Alert severity="info">{message}</Alert>}
            {sandboxToken && (
              <Alert severity="warning">Sandbox token (not emailed): {sandboxToken}</Alert>
            )}
            <TextField label="Email" type="email" value={email} onChange={(e) => setEmail(e.target.value)} required fullWidth />
            <Button variant="outlined" onClick={() => void requestReset()}>
              Send reset token
            </Button>
            <TextField label="Reset token" value={token} onChange={(e) => setToken(e.target.value)} fullWidth />
            <TextField label="New password" type="password" value={password} onChange={(e) => setPassword(e.target.value)} fullWidth />
            <TextField
              label="Confirm password"
              type="password"
              value={passwordConfirmation}
              onChange={(e) => setPasswordConfirmation(e.target.value)}
              fullWidth
            />
            <Button type="submit" variant="contained">
              Update password
            </Button>
            <Link component={RouterLink} to="/login" variant="body2">
              Back to sign in
            </Link>
          </Stack>
        </CardContent>
      </Card>
    </Box>
  );
}
