import { useEffect, useState } from 'react';
import {
  Alert,
  Button,
  Chip,
  Stack,
  Table,
  TableBody,
  TableCell,
  TableHead,
  TableRow,
  TextField,
  Typography,
} from '@mui/material';
import { api } from '../api/client';
import { can } from '../auth/permissions';
import { useAuth } from '../store/auth';

type Integration = {
  id: string;
  provider_key: string;
  display_name: string;
  status: string;
  last_health_status: string | null;
  notes: string | null;
};

export function SettingsPage() {
  const { user } = useAuth();
  const canManageIntegrations = can(user, 'integration.manage');
  const canConfigureTenant = can(user, 'tenant.configure');
  const [rows, setRows] = useState<Integration[]>([]);
  const [displayName, setDisplayName] = useState('');
  const [error, setError] = useState<string | null>(null);
  const [message, setMessage] = useState<string | null>(null);

  const load = async () => {
    if (!canManageIntegrations) return;
    const { data } = await api.get<{ data: Integration[]; notice: string }>('/integrations');
    setRows(data.data ?? []);
  };

  useEffect(() => {
    void load().catch(() => setError('Integrations require integration.manage permission.'));
  }, []);

  const enableSandbox = async (id: string) => {
    setError(null);
    try {
      await api.post(`/integrations/${id}/sandbox`);
      await api.post(`/integrations/${id}/sandbox-probe`);
      setMessage('Sandbox enabled and probed — not a live confirmation.');
      await load();
    } catch {
      setError('Sandbox action failed.');
    }
  };

  const saveBranding = async () => {
    try {
      await api.put('/settings/branding', { display_name: displayName, primary_color: '#0B1F3B' });
      setMessage('Branding saved.');
    } catch {
      setError('Branding update failed (needs tenant.configure).');
    }
  };

  return (
    <Stack spacing={2}>
      <Typography variant="h4">Settings & integrations</Typography>
      <Alert severity="warning">
        Integration states: NOT_CONFIGURED / SANDBOX / ACTIVE… Live TIRA/insurer/payment stay OFF without authorized credentials.
        SaaS platform billing is a separate ledger from insurance premiums.
      </Alert>
      {error && <Alert severity="error">{error}</Alert>}
      {message && <Alert severity="success">{message}</Alert>}

      {canConfigureTenant && (
        <>
          <Typography variant="h6">Tenant branding</Typography>
          <Stack direction="row" spacing={1}>
            <TextField size="small" label="Display name" value={displayName} onChange={(e) => setDisplayName(e.target.value)} />
            <Button variant="outlined" onClick={() => void saveBranding()}>
              Save
            </Button>
          </Stack>
        </>
      )}

      {canManageIntegrations && (
        <>
          <Typography variant="h6">Adapters</Typography>
          <Table size="small">
            <TableHead>
              <TableRow>
                <TableCell>Provider</TableCell>
                <TableCell>Status</TableCell>
                <TableCell>Health</TableCell>
                <TableCell />
              </TableRow>
            </TableHead>
            <TableBody>
              {rows.map((r) => (
                <TableRow key={r.id}>
                  <TableCell>
                    {r.display_name}
                    <Typography variant="caption" color="text.secondary" sx={{ display: 'block' }}>
                      {r.provider_key}
                    </Typography>
                  </TableCell>
                  <TableCell>
                    <Chip size="small" label={r.status} color={r.status === 'SANDBOX' ? 'warning' : 'default'} />
                  </TableCell>
                  <TableCell>{r.last_health_status ?? '—'}</TableCell>
                  <TableCell>
                    {(r.status === 'NOT_CONFIGURED' || r.status === 'SANDBOX') && (
                      <Button size="small" onClick={() => void enableSandbox(r.id)}>
                        Enable sandbox + probe
                      </Button>
                    )}
                  </TableCell>
                </TableRow>
              ))}
            </TableBody>
          </Table>
        </>
      )}
    </Stack>
  );
}
