import { useEffect, useState } from 'react';
import { Alert, Button, Stack, Table, TableBody, TableCell, TableHead, TableRow, Typography } from '@mui/material';
import { api } from '../../api/client';

type Policy = {
  id: string;
  carrier_policy_number: string;
  effective_to: string;
  status: string;
  carrier?: { legal_name: string };
};

export function PortalRenewalsPage() {
  const [rows, setRows] = useState<Policy[]>([]);
  const [message, setMessage] = useState<string | null>(null);
  const [error, setError] = useState<string | null>(null);

  useEffect(() => {
    void api
      .get<{ data: Policy[] }>('/portal/renewals')
      .then((res) => setRows(res.data.data ?? []))
      .catch(() => setError('Unable to load renewals.'));
  }, []);

  const request = async (policyId: string) => {
    try {
      const { data } = await api.post('/portal/service-requests', {
        type: 'renewal',
        subject: 'Renewal assistance request',
        body: 'Please help me renew this policy with the insurer.',
        policy_id: policyId,
      });
      setMessage(data.acknowledgement ?? 'Renewal request logged.');
    } catch {
      setError('Could not submit renewal request.');
    }
  };

  return (
    <Stack spacing={2}>
      <Typography variant="h4">Renewals</Typography>
      <Alert severity="info">Policies expiring within 90 days. Renewal is staff-assisted — not automatic issuance.</Alert>
      {error && <Alert severity="error">{error}</Alert>}
      {message && <Alert severity="success">{message}</Alert>}
      <Table size="small">
        <TableHead>
          <TableRow>
            <TableCell>Policy</TableCell>
            <TableCell>Carrier</TableCell>
            <TableCell>Expires</TableCell>
            <TableCell />
          </TableRow>
        </TableHead>
        <TableBody>
          {rows.length === 0 ? (
            <TableRow>
              <TableCell colSpan={4}>No upcoming renewals in the reminder window.</TableCell>
            </TableRow>
          ) : (
            rows.map((p) => (
              <TableRow key={p.id}>
                <TableCell>{p.carrier_policy_number}</TableCell>
                <TableCell>{p.carrier?.legal_name}</TableCell>
                <TableCell>{new Date(p.effective_to).toLocaleDateString()}</TableCell>
                <TableCell>
                  <Button size="small" onClick={() => void request(p.id)}>
                    Request renewal help
                  </Button>
                </TableCell>
              </TableRow>
            ))
          )}
        </TableBody>
      </Table>
    </Stack>
  );
}
