import { useEffect, useState } from 'react';
import {
  Alert,
  Button,
  Chip,
  MenuItem,
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

type Policy = { id: string; carrier_policy_number: string; customer?: { display_name: string } };
type Claim = {
  id: string;
  reference: string;
  status: string;
  incident_at: string;
  declared_amount: string | null;
  fraud_review_flag: string | null;
  policy?: { carrier_policy_number: string };
  customer?: { display_name: string };
};

export function ClaimsPage() {
  const { user } = useAuth();
  const canSubmit = can(user, 'claim.submit');
  const canReview = can(user, 'claim.review');
  const [claims, setClaims] = useState<Claim[]>([]);
  const [policies, setPolicies] = useState<Policy[]>([]);
  const [policyId, setPolicyId] = useState('');
  const [description, setDescription] = useState('');
  const [error, setError] = useState<string | null>(null);
  const [message, setMessage] = useState<string | null>(null);

  const load = async () => {
    const [c, p] = await Promise.all([
      api.get<{ data: Claim[] }>('/claims'),
      api.get<{ data: Policy[] }>('/policies', { params: { status: 'active' } }),
    ]);
    setClaims(c.data.data ?? []);
    setPolicies(p.data.data ?? []);
    if (!policyId && p.data.data?.[0]) setPolicyId(p.data.data[0].id);
  };

  useEffect(() => {
    void load().catch(() => setError('Unable to load claims.'));
  }, []);

  const createFnol = async () => {
    setError(null);
    setMessage(null);
    try {
      const { data } = await api.post('/claims', {
        policy_id: policyId,
        incident_at: new Date().toISOString(),
        description,
        urgency: 'normal',
      });
      setMessage(data.notice ?? 'FNOL draft created.');
      setDescription('');
      await load();
    } catch {
      setError('FNOL create failed.');
    }
  };

  const submit = async (id: string) => {
    try {
      await api.post(`/claims/${id}/submit`);
      setMessage('FNOL submitted.');
      await load();
    } catch {
      setError('Submit failed.');
    }
  };

  const acknowledge = async (id: string) => {
    try {
      await api.post(`/claims/${id}/acknowledge`);
      setMessage('Claim acknowledged.');
      await load();
    } catch {
      setError('Acknowledge requires claim.review permission.');
    }
  };

  return (
    <Stack spacing={2}>
      <Typography variant="h4">Claims</Typography>
      <Alert severity="warning">
        Coverage shown from policy is provisional. Agency coordinates FNOL — settlement decisions remain with the insurer unless delegated authority is documented.
      </Alert>
      {error && <Alert severity="error">{error}</Alert>}
      {message && <Alert severity="success">{message}</Alert>}

      {canSubmit && (
        <Stack direction={{ xs: 'column', sm: 'row' }} spacing={1}>
          <TextField select size="small" label="Policy" value={policyId} onChange={(e) => setPolicyId(e.target.value)} sx={{ minWidth: 220 }}>
            {policies.map((p) => (
              <MenuItem key={p.id} value={p.id}>
                {p.carrier_policy_number}
              </MenuItem>
            ))}
          </TextField>
          <TextField
            size="small"
            label="Incident description"
            value={description}
            onChange={(e) => setDescription(e.target.value)}
            sx={{ flex: 1 }}
          />
          <Button variant="contained" disabled={!policyId || description.length < 10} onClick={() => void createFnol()}>
            Draft FNOL
          </Button>
        </Stack>
      )}

      <Table size="small">
        <TableHead>
          <TableRow>
            <TableCell>Reference</TableCell>
            <TableCell>Policy</TableCell>
            <TableCell>Customer</TableCell>
            <TableCell>Status</TableCell>
            <TableCell>Flags</TableCell>
            <TableCell />
          </TableRow>
        </TableHead>
        <TableBody>
          {claims.map((c) => (
            <TableRow key={c.id}>
              <TableCell>{c.reference}</TableCell>
              <TableCell>{c.policy?.carrier_policy_number}</TableCell>
              <TableCell>{c.customer?.display_name}</TableCell>
              <TableCell>
                <Chip size="small" label={c.status} />
              </TableCell>
              <TableCell>{c.fraud_review_flag ?? '—'}</TableCell>
              <TableCell>
                {canSubmit && c.status === 'draft' && (
                  <Button size="small" onClick={() => void submit(c.id)}>
                    Submit
                  </Button>
                )}
                {canReview && c.status === 'submitted' && (
                  <Button size="small" onClick={() => void acknowledge(c.id)}>
                    Acknowledge
                  </Button>
                )}
              </TableCell>
            </TableRow>
          ))}
        </TableBody>
      </Table>
    </Stack>
  );
}
