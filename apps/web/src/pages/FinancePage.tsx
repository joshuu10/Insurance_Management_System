import { useEffect, useState } from 'react';
import {
  Alert,
  Button,
  Stack,
  Table,
  TableBody,
  TableCell,
  TableHead,
  TableRow,
  Typography,
} from '@mui/material';
import { api } from '../api/client';
import { can } from '../auth/permissions';
import { useAuth } from '../store/auth';

type Overview = {
  commission_expected: string | number;
  commission_confirmed: string | number;
  commission_received: string | number;
  journals_posted: number;
  open_reconciliations: number;
};

type Commission = {
  id: string;
  status: string;
  expected_amount: string;
  confirmed_amount: string | null;
  received_amount: string | null;
  policy?: { carrier_policy_number: string };
};

type Journal = {
  id: string;
  reference: string;
  memo: string;
  status: string;
  lines?: Array<{ debit: string; credit: string; account?: { code: string; name: string } }>;
};

export function FinancePage() {
  const { user } = useAuth();
  const canApprove = can(user, 'commission.approve');
  const [overview, setOverview] = useState<Overview | null>(null);
  const [commissions, setCommissions] = useState<Commission[]>([]);
  const [journals, setJournals] = useState<Journal[]>([]);
  const [error, setError] = useState<string | null>(null);
  const [message, setMessage] = useState<string | null>(null);

  const load = async () => {
    const [o, c, j] = await Promise.all([
      api.get<{ data: Overview; notice: string }>('/finance/overview'),
      api.get<{ data: Commission[] }>('/finance/commissions'),
      api.get<{ data: Journal[] }>('/finance/journals'),
    ]);
    setOverview(o.data.data);
    setCommissions(c.data.data ?? []);
    setJournals(j.data.data ?? []);
  };

  useEffect(() => {
    void load().catch(() => setError('Finance requires finance.reconcile permission.'));
  }, []);

  const expectFromDemoPolicy = async () => {
    setError(null);
    try {
      const policies = await api.get('/policies');
      const policyId = policies.data.data?.[0]?.id;
      if (!policyId) throw new Error('no policy');
      await api.post('/finance/commissions/expect', { policy_id: policyId });
      setMessage('Expected commission calculated from schedule.');
      await load();
    } catch {
      setError('Could not expect commission.');
    }
  };

  return (
    <Stack spacing={2}>
      <Typography variant="h4">Finance</Typography>
      <Alert severity="info">
        Agency subledger only. Commission is not gross premium. Posted journals cannot be hard-deleted.
      </Alert>
      {error && <Alert severity="error">{error}</Alert>}
      {message && <Alert severity="success">{message}</Alert>}

      {overview && (
        <Stack direction={{ xs: 'column', sm: 'row' }} spacing={2}>
          <Typography variant="body2">Expected: TZS {overview.commission_expected}</Typography>
          <Typography variant="body2">Confirmed: TZS {overview.commission_confirmed}</Typography>
          <Typography variant="body2">Received: TZS {overview.commission_received}</Typography>
          <Typography variant="body2">Journals: {overview.journals_posted}</Typography>
        </Stack>
      )}

      {canApprove && (
        <Button variant="outlined" onClick={() => void expectFromDemoPolicy()} sx={{ alignSelf: 'flex-start' }}>
          Expect commission on first policy
        </Button>
      )}

      <Typography variant="h6">Commission receivables</Typography>
      <Table size="small">
        <TableHead>
          <TableRow>
            <TableCell>Policy</TableCell>
            <TableCell>Status</TableCell>
            <TableCell>Expected</TableCell>
            <TableCell>Confirmed</TableCell>
            <TableCell>Received</TableCell>
          </TableRow>
        </TableHead>
        <TableBody>
          {commissions.map((c) => (
            <TableRow key={c.id}>
              <TableCell>{c.policy?.carrier_policy_number ?? '—'}</TableCell>
              <TableCell>{c.status}</TableCell>
              <TableCell>{c.expected_amount}</TableCell>
              <TableCell>{c.confirmed_amount ?? '—'}</TableCell>
              <TableCell>{c.received_amount ?? '—'}</TableCell>
            </TableRow>
          ))}
        </TableBody>
      </Table>

      <Typography variant="h6">Journals</Typography>
      <Table size="small">
        <TableHead>
          <TableRow>
            <TableCell>Reference</TableCell>
            <TableCell>Memo</TableCell>
            <TableCell>Status</TableCell>
            <TableCell>Lines</TableCell>
          </TableRow>
        </TableHead>
        <TableBody>
          {journals.map((j) => (
            <TableRow key={j.id}>
              <TableCell>{j.reference}</TableCell>
              <TableCell>{j.memo}</TableCell>
              <TableCell>{j.status}</TableCell>
              <TableCell>{j.lines?.length ?? 0}</TableCell>
            </TableRow>
          ))}
        </TableBody>
      </Table>
    </Stack>
  );
}
