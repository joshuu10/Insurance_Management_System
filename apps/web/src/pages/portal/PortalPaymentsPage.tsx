import { useEffect, useState } from 'react';
import {
  Alert,
  Button,
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
import { api } from '../../api/client';

type Policy = { id: string; carrier_policy_number: string; premium_amount: string | null; currency: string };
type Instruction = { id: string; policy_id: string; amount: string; currency: string; status: string; payment_reference: string | null };
type Tx = {
  id: string;
  reference: string;
  status: string;
  amount: string;
  currency: string;
  receipt_number: string | null;
  policy?: { carrier_policy_number: string };
};

export function PortalPaymentsPage() {
  const [policies, setPolicies] = useState<Policy[]>([]);
  const [instructions, setInstructions] = useState<Instruction[]>([]);
  const [txs, setTxs] = useState<Tx[]>([]);
  const [policyId, setPolicyId] = useState('');
  const [error, setError] = useState<string | null>(null);
  const [message, setMessage] = useState<string | null>(null);

  const load = async () => {
    const [dues, payments] = await Promise.all([
      api.get<{ data: { policies: Policy[]; premium_instructions: Instruction[] } }>('/portal/premiums'),
      api.get<{ data: Tx[] }>('/portal/payments'),
    ]);
    setPolicies(dues.data.data.policies ?? []);
    setInstructions(dues.data.data.premium_instructions ?? []);
    setTxs(payments.data.data ?? []);
    if (!policyId && dues.data.data.policies?.[0]) setPolicyId(dues.data.data.policies[0].id);
  };

  useEffect(() => {
    void load().catch(() => setError('Unable to load premiums.'));
  }, []);

  const pay = async () => {
    setError(null);
    try {
      const instruction = instructions.find((i) => i.policy_id === policyId);
      const { data } = await api.post('/portal/payments', {
        policy_id: policyId,
        premium_instruction_id: instruction?.id ?? null,
      });
      setMessage(data.notice);
      await load();
    } catch {
      setError('Could not initiate payment.');
    }
  };

  const confirm = async (id: string) => {
    try {
      const { data } = await api.post(`/portal/payments/${id}/sandbox-confirm`);
      setMessage(`Sandbox receipt ${data.receipt?.receipt_number}`);
      await load();
    } catch {
      setError('Confirm failed.');
    }
  };

  return (
    <Stack spacing={2}>
      <Typography variant="h4">Premiums & payments</Typography>
      <Alert severity="warning">
        Sandbox gateway only. Confirmation is not insurer settlement and does not bind or renew cover.
      </Alert>
      {error && <Alert severity="error">{error}</Alert>}
      {message && <Alert severity="success">{message}</Alert>}

      <Stack direction={{ xs: 'column', sm: 'row' }} spacing={1}>
        <TextField select size="small" label="Policy" value={policyId} onChange={(e) => setPolicyId(e.target.value)} sx={{ minWidth: 220 }}>
          {policies.map((p) => (
            <MenuItem key={p.id} value={p.id}>
              {p.carrier_policy_number} — {p.currency} {p.premium_amount}
            </MenuItem>
          ))}
        </TextField>
        <Button variant="contained" disabled={!policyId} onClick={() => void pay()}>
          Initiate sandbox payment
        </Button>
      </Stack>

      <Typography variant="h6">Open premium instructions</Typography>
      <Table size="small">
        <TableHead>
          <TableRow>
            <TableCell>Reference</TableCell>
            <TableCell>Amount</TableCell>
            <TableCell>Status</TableCell>
          </TableRow>
        </TableHead>
        <TableBody>
          {instructions.map((i) => (
            <TableRow key={i.id}>
              <TableCell>{i.payment_reference ?? i.id.slice(0, 8)}</TableCell>
              <TableCell>
                {i.currency} {i.amount}
              </TableCell>
              <TableCell>{i.status}</TableCell>
            </TableRow>
          ))}
        </TableBody>
      </Table>

      <Typography variant="h6">Transaction history</Typography>
      <Table size="small">
        <TableHead>
          <TableRow>
            <TableCell>Reference</TableCell>
            <TableCell>Policy</TableCell>
            <TableCell>Amount</TableCell>
            <TableCell>Status</TableCell>
            <TableCell>Receipt</TableCell>
            <TableCell />
          </TableRow>
        </TableHead>
        <TableBody>
          {txs.map((t) => (
            <TableRow key={t.id}>
              <TableCell>{t.reference}</TableCell>
              <TableCell>{t.policy?.carrier_policy_number}</TableCell>
              <TableCell>
                {t.currency} {t.amount}
              </TableCell>
              <TableCell>{t.status}</TableCell>
              <TableCell>{t.receipt_number ?? '—'}</TableCell>
              <TableCell>
                {t.status === 'pending' && (
                  <Button size="small" onClick={() => void confirm(t.id)}>
                    Sandbox confirm
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
