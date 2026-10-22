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
import { api } from '../api/client';

type Lead = {
  id: string;
  title: string;
  stage: string;
  source: string;
  expected_premium_note: string | null;
  customer?: { display_name: string } | null;
  owner?: { name: string } | null;
};

export function LeadsPage() {
  const [rows, setRows] = useState<Lead[]>([]);
  const [error, setError] = useState<string | null>(null);

  useEffect(() => {
    void api
      .get<{ data: Lead[] }>('/leads')
      .then((res) => setRows(res.data.data ?? []))
      .catch(() => setError('Unable to load leads.'));
  }, []);

  return (
    <Stack spacing={2}>
      <Typography variant="h4">Leads</Typography>
      {error && <Alert severity="error">{error}</Alert>}
      <Table size="small">
        <TableHead>
          <TableRow>
            <TableCell>Title</TableCell>
            <TableCell>Customer</TableCell>
            <TableCell>Stage</TableCell>
            <TableCell>Owner</TableCell>
            <TableCell>Premium note</TableCell>
          </TableRow>
        </TableHead>
        <TableBody>
          {rows.length === 0 ? (
            <TableRow>
              <TableCell colSpan={5}>No leads in pipeline.</TableCell>
            </TableRow>
          ) : (
            rows.map((l) => (
              <TableRow key={l.id}>
                <TableCell>{l.title}</TableCell>
                <TableCell>{l.customer?.display_name ?? '—'}</TableCell>
                <TableCell>
                  <Chip size="small" label={l.stage} color="info" variant="outlined" />
                </TableCell>
                <TableCell>{l.owner?.name ?? '—'}</TableCell>
                <TableCell>{l.expected_premium_note ?? '—'}</TableCell>
              </TableRow>
            ))
          )}
        </TableBody>
      </Table>
    </Stack>
  );
}
