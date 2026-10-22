import { useEffect, useState } from 'react';
import { useTranslation } from 'react-i18next';
import { Alert, Box, Paper, Stack, Typography } from '@mui/material';
import { api, health } from '../api/client';
import { can, visibleNav } from '../auth/permissions';
import { useAuth } from '../store/auth';

export function DashboardPage() {
  const { t } = useTranslation();
  const { user } = useAuth();
  const canViewPolicies = can(user, 'policy.view');
  const areas = visibleNav(user)
    .filter((n) => n.path !== '/')
    .map((n) => t(n.key));
  const [apiHealth, setApiHealth] = useState<string>('checking…');
  const [activePolicies, setActivePolicies] = useState<number | null>(null);
  const [upcomingRenewals, setUpcomingRenewals] = useState<number | null>(null);

  useEffect(() => {
    void health()
      .then((h) => setApiHealth(`${h.status} · ${h.edition} · ${h.app}`))
      .catch(() => setApiHealth('unavailable'));

    if (!canViewPolicies) {
      setActivePolicies(null);
      setUpcomingRenewals(null);
      return;
    }

    void Promise.all([
      api.get('/policies', { params: { status: 'active' } }),
      api.get('/policies/renewals/upcoming', { params: { within_days: 60 } }),
    ])
      .then(([policies, renewals]) => {
        setActivePolicies((policies.data.data ?? []).length);
        setUpcomingRenewals((renewals.data.data ?? []).length);
      })
      .catch(() => {
        setActivePolicies(null);
        setUpcomingRenewals(null);
      });
  }, [canViewPolicies]);

  return (
    <Stack spacing={2}>
      <Typography variant="h4">{t('dashboard')}</Typography>
      <Typography color="text.secondary">
        {user?.name} · {(user?.roles ?? []).join(', ') || 'no roles'}
      </Typography>
      <Alert severity="info">{t('sandboxNotice')}</Alert>
      <Paper sx={{ p: 3, border: '1px solid', borderColor: 'divider' }}>
        <Typography variant="h6" gutterBottom>
          Your workspace
        </Typography>
        <Box sx={{ mt: 2, display: 'grid', gap: 1 }}>
          <Typography variant="body2">
            Available modules: {areas.length ? areas.join(' · ') : 'Dashboard only'}
          </Typography>
          {canViewPolicies && (
            <>
              <Typography variant="body2">Active policies (page): {activePolicies ?? '—'}</Typography>
              <Typography variant="body2">Renewals within 60 days: {upcomingRenewals ?? '—'}</Typography>
            </>
          )}
          <Typography variant="body2" color="text.secondary">
            API health: {apiHealth}
          </Typography>
          <Typography variant="body2" color="text.secondary">
            Intermediary: {user?.organization?.intermediary_type ?? '—'} · Licence:{' '}
            {user?.organization?.licence_number ?? 'pending onboarding'}
          </Typography>
        </Box>
      </Paper>
    </Stack>
  );
}
