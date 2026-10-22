import { useEffect, useState } from 'react';
import {
  Alert,
  Chip,
  Stack,
  Table,
  TableBody,
  TableCell,
  TableHead,
  TableRow,
  Typography,
} from '@mui/material';
import { api } from '../../api/client';

type Policy = {
  id: string;
  carrier_policy_number: string;
  status: string;
  premium_amount: string | null;
  currency: string;
  effective_from: string;
  effective_to: string;
  days_to_expiry?: number;
  renewal_reminder?: boolean;
  premium_due?: { amount: string | null; currency: string };
  carrier?: { legal_name: string };
  carrier_product?: { name: string };
};

export function PortalPoliciesPage() {
  const [rows, setRows] = useState<Policy[]>([]);
  const [notice, setNotice] = useState<string | null>(null);
  const [error, setError] = useState<string | null>(null);

  useEffect(() => {
    void api
      .get<{ data: Policy[]; notice?: string }>('/portal/policies')
      .then((res) => {
        setRows(res.data.data ?? []);
        setNotice(res.data.notice ?? null);
      })
      .catch(() => setError('Unable to load your policies.'));
  }, []);

  return (
    <Stack spacing={2}>
      <Typography variant="h4">My policies</Typography>
      {notice && <Alert severity="info">{notice}</Alert>}
      {error && <Alert severity="error">{error}</Alert>}
      <Table size="small">
        <TableHead>
          <TableRow>
            <TableCell>Policy #</TableCell>
            <TableCell>Carrier / product</TableCell>
            <TableCell>Status</TableCell>
            <TableCell>Premium due</TableCell>
            <TableCell>Term</TableCell>
            <TableCell>Reminder</TableCell>
          </TableRow>
        </TableHead>
        <TableBody>
          {rows.length === 0 ? (
            <TableRow>
              <TableCell colSpan={6}>No policies on file yet.</TableCell>
            </TableRow>
          ) : (
            rows.map((p) => (
              <TableRow key={p.id}>
                <TableCell>{p.carrier_policy_number}</TableCell>
                <TableCell>
                  {p.carrier?.legal_name}
                  <br />
                  <Typography variant="caption">{p.carrier_product?.name ?? '—'}</Typography>
                </TableCell>
                <TableCell>
                  <Chip size="small" label={p.status} />
                </TableCell>
                <TableCell>
                  {p.premium_due?.amount != null
                    ? `${p.premium_due.currency} ${p.premium_due.amount}`
                    : p.premium_amount != null
                      ? `${p.currency} ${p.premium_amount}`
                      : '—'}
                </TableCell>
                <TableCell>
                  {new Date(p.effective_from).toLocaleDateString()} → {new Date(p.effective_to).toLocaleDateString()}
                </TableCell>
                <TableCell>
                  {p.renewal_reminder ? (
                    <Chip size="small" color="warning" label={`${p.days_to_expiry}d`} />
                  ) : (
                    '—'
                  )}
                </TableCell>
              </TableRow>
            ))
          )}
        </TableBody>
      </Table>
    </Stack>
  );
}
