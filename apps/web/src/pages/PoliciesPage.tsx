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

type Policy = {
  id: string;
  carrier_policy_number: string;
  cover_note_number: string | null;
  status: string;
  verification_status: string;
  premium_amount: string | null;
  currency: string;
  effective_from: string;
  effective_to: string;
  customer?: { display_name: string };
  carrier?: { legal_name: string };
};

type Customer = { id: string; display_name: string };
type Appointment = {
  carrier: { id: string; legal_name: string };
};

export function PoliciesPage() {
  const { user } = useAuth();
  const canIntake = can(user, 'policy.issue.authorized');
  const canView = can(user, 'policy.view');
  const [policies, setPolicies] = useState<Policy[]>([]);
  const [renewals, setRenewals] = useState<Policy[]>([]);
  const [customers, setCustomers] = useState<Customer[]>([]);
  const [carriers, setCarriers] = useState<Appointment[]>([]);
  const [customerId, setCustomerId] = useState('');
  const [carrierId, setCarrierId] = useState('');
  const [policyNumber, setPolicyNumber] = useState('');
  const [message, setMessage] = useState<string | null>(null);
  const [error, setError] = useState<string | null>(null);

  const load = async () => {
    const [p, r] = await Promise.all([
      api.get<{ data: Policy[] }>('/policies'),
      api.get<{ data: Policy[] }>('/policies/renewals/upcoming?within_days=60'),
    ]);
    setPolicies(p.data.data ?? []);
    setRenewals(r.data.data ?? []);

    if (!canIntake) return;

    const [c, a] = await Promise.all([
      api.get<{ data: Customer[] }>('/customers'),
      api.get<{ data: Appointment[] }>('/catalog/appointments'),
    ]);
    setCustomers(c.data.data ?? []);
    setCarriers((a.data.data ?? []).filter((x) => x.carrier));
    if (!customerId && c.data.data?.[0]) setCustomerId(c.data.data[0].id);
    const firstCarrier = a.data.data?.[0]?.carrier?.id;
    if (!carrierId && firstCarrier) setCarrierId(firstCarrier);
  };

  useEffect(() => {
    void load().catch(() => setError('Unable to load policies.'));
  }, []);

  const intake = async () => {
    setError(null);
    setMessage(null);
    try {
      await api.post('/policies/intake', {
        customer_id: customerId,
        carrier_id: carrierId,
        carrier_policy_number: policyNumber,
        effective_from: new Date().toISOString(),
        effective_to: new Date(Date.now() + 365 * 86400000).toISOString(),
        status: 'active',
        verification_status: 'not_checked',
        intake_notes: 'Staff intake of insurer-issued schedule (demo).',
      });
      setPolicyNumber('');
      setMessage('Policy intake recorded. Verification remains not_checked until authorized check.');
      await load();
    } catch {
      setError('Intake failed — need policy.issue.authorized and an active carrier appointment.');
    }
  };

  const openRenewal = async (id: string) => {
    try {
      await api.post(`/policies/${id}/renewals`);
      setMessage('Renewal case opened (no auto-charge).');
      await load();
    } catch {
      setError('Could not open renewal case.');
    }
  };

  return (
    <Stack spacing={2}>
      <Typography variant="h4">Policies</Typography>
      <Alert severity="info">
        Policies are insurer-issued records with provenance. Agency intake does not bind cover. TIRA verification is never fabricated.
      </Alert>
      {error && <Alert severity="error">{error}</Alert>}
      {message && <Alert severity="success">{message}</Alert>}

      {canIntake && (
        <>
          <Typography variant="h6">Intake insurer-issued policy</Typography>
          <Stack direction={{ xs: 'column', sm: 'row' }} spacing={1}>
            <TextField select size="small" label="Customer" value={customerId} onChange={(e) => setCustomerId(e.target.value)} sx={{ minWidth: 180 }}>
              {customers.map((c) => (
                <MenuItem key={c.id} value={c.id}>
                  {c.display_name}
                </MenuItem>
              ))}
            </TextField>
            <TextField select size="small" label="Carrier" value={carrierId} onChange={(e) => setCarrierId(e.target.value)} sx={{ minWidth: 220 }}>
              {carriers.map((a) => (
                <MenuItem key={a.carrier.id} value={a.carrier.id}>
                  {a.carrier.legal_name}
                </MenuItem>
              ))}
            </TextField>
            <TextField size="small" label="Carrier policy #" value={policyNumber} onChange={(e) => setPolicyNumber(e.target.value)} />
            <Button variant="contained" onClick={() => void intake()} disabled={!customerId || !carrierId || !policyNumber}>
              Intake
            </Button>
          </Stack>
        </>
      )}

      <Typography variant="h6">Upcoming renewals (60 days)</Typography>
      <Table size="small">
        <TableHead>
          <TableRow>
            <TableCell>Policy #</TableCell>
            <TableCell>Customer</TableCell>
            <TableCell>Expires</TableCell>
            <TableCell />
          </TableRow>
        </TableHead>
        <TableBody>
          {renewals.length === 0 ? (
            <TableRow>
              <TableCell colSpan={4}>No renewals in window.</TableCell>
            </TableRow>
          ) : (
            renewals.map((p) => (
              <TableRow key={p.id}>
                <TableCell>{p.carrier_policy_number}</TableCell>
                <TableCell>{p.customer?.display_name}</TableCell>
                <TableCell>{new Date(p.effective_to).toLocaleDateString()}</TableCell>
                <TableCell>
                  {canView && (
                    <Button size="small" onClick={() => void openRenewal(p.id)}>
                      Open renewal
                    </Button>
                  )}
                </TableCell>
              </TableRow>
            ))
          )}
        </TableBody>
      </Table>

      <Typography variant="h6">All policies</Typography>
      <Table size="small">
        <TableHead>
          <TableRow>
            <TableCell>Policy #</TableCell>
            <TableCell>Customer</TableCell>
            <TableCell>Carrier</TableCell>
            <TableCell>Status</TableCell>
            <TableCell>Verification</TableCell>
            <TableCell>Premium</TableCell>
            <TableCell>Term</TableCell>
          </TableRow>
        </TableHead>
        <TableBody>
          {policies.map((p) => (
            <TableRow key={p.id}>
              <TableCell>{p.carrier_policy_number}</TableCell>
              <TableCell>{p.customer?.display_name}</TableCell>
              <TableCell>{p.carrier?.legal_name}</TableCell>
              <TableCell>
                <Chip size="small" label={p.status} />
              </TableCell>
              <TableCell>{p.verification_status}</TableCell>
              <TableCell>
                {p.premium_amount != null ? `${p.currency} ${p.premium_amount}` : '—'}
              </TableCell>
              <TableCell>
                {new Date(p.effective_from).toLocaleDateString()} → {new Date(p.effective_to).toLocaleDateString()}
              </TableCell>
            </TableRow>
          ))}
        </TableBody>
      </Table>
    </Stack>
  );
}
