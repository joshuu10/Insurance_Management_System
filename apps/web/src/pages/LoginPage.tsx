import { useEffect, useState } from 'react';
import { Link as RouterLink, useNavigate } from 'react-router-dom';
import { useTranslation } from 'react-i18next';
import {
  Alert,
  Box,
  Button,
  Card,
  CardContent,
  Link,
  Stack,
  TextField,
  Typography,
} from '@mui/material';
import { postLoginPath } from '../auth/portal';
import { useAuth } from '../store/auth';

export function LoginPage() {
  const { t } = useTranslation();
  const navigate = useNavigate();
  const { login, loading, error, user, loadMe } = useAuth();
  const [email, setEmail] = useState('customer@demo.wabantu.local');
  const [password, setPassword] = useState('Password1!');

  useEffect(() => {
    void loadMe();
  }, [loadMe]);

  useEffect(() => {
    if (user) {
      navigate(postLoginPath(user), { replace: true });
    }
  }, [user, navigate]);

  const onSubmit = async (e: React.FormEvent) => {
    e.preventDefault();
    try {
      await login(email, password);
      navigate(postLoginPath(useAuth.getState().user));
    } catch {
      /* error in store */
    }
  };

  return (
    <Box
      sx={{
        minHeight: '100vh',
        display: 'grid',
        placeItems: 'center',
        px: 2,
        background: 'linear-gradient(160deg, #0B1F3B 0%, #143356 45%, #F6F8FC 45%)',
      }}
    >
      <Card sx={{ width: '100%', maxWidth: 420, border: '1px solid', borderColor: 'divider' }}>
        <CardContent sx={{ p: 4 }}>
          <Stack spacing={2} component="form" onSubmit={onSubmit}>
            <Box>
              <Typography variant="h5" color="primary">
                {t('appName')}
              </Typography>
              <Typography variant="body2" color="text.secondary">
                {t('tagline')}
              </Typography>
            </Box>
            {error && <Alert severity="error">{error}</Alert>}
            <TextField
              label={t('email')}
              type="email"
              value={email}
              onChange={(e) => setEmail(e.target.value)}
              required
              fullWidth
              autoComplete="username"
            />
            <TextField
              label={t('password')}
              type="password"
              value={password}
              onChange={(e) => setPassword(e.target.value)}
              required
              fullWidth
              autoComplete="current-password"
            />
            <Button type="submit" variant="contained" size="large" disabled={loading}>
              {t('login')}
            </Button>
            <Typography variant="caption" color="text.secondary">
              Demo: customer@demo.wabantu.local · admin@demo.wabantu.local · agent@demo.wabantu.local — Password1!
            </Typography>
            <Stack direction="row" spacing={2}>
              <Link component={RouterLink} to="/portal/register" variant="caption">
                Customer register
              </Link>
              <Link component={RouterLink} to="/portal/forgot-password" variant="caption">
                Forgot password
              </Link>
            </Stack>
            <Link href="https://wabantu.local" underline="hover" variant="caption" onClick={(e) => e.preventDefault()}>
              Wabantu Technologies
            </Link>
          </Stack>
        </CardContent>
      </Card>
    </Box>
  );
}
