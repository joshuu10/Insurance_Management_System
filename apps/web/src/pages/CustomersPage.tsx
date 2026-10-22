import { useEffect, useState } from 'react';
import {
  Alert,
  Box,
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
import { api } from '../api/client';

type Customer = {
  id: string;
  display_name: string;
  type: string;
  email: string | null;
  phone_e164: string | null;
  kyc_status: string;
};

export function CustomersPage() {
  const [rows, setRows] = useState<Customer[]>([]);
  const [error, setError] = useState<string | null>(null);
  const [name, setName] = useState('');
  const [type, setType] = useState('individual');
  const [email, setEmail] = useState('');

  const load = async () => {
    try {
      const { data } = await api.get<{ data: Customer[] }>('/customers');
      setRows(data.data ?? []);
      setError(null);
    } catch {
      setError('Unable to load customers.');
    }
  };

  useEffect(() => {
    void load();
  }, []);

  const create = async (e: React.FormEvent) => {
    e.preventDefault();
    try {
      await api.post('/customers', { display_name: name, type, email: email || null });
      setName('');
      setEmail('');
      await load();
    } catch {
      setError('Create failed — check permissions and API.');
    }
  };

  return (
    <Stack spacing={2}>
      <Typography variant="h4">Customers</Typography>
      {error && <Alert severity="error">{error}</Alert>}
      <Box component="form" onSubmit={create} sx={{ display: 'flex', gap: 1, flexWrap: 'wrap' }}>
        <TextField size="small" label="Display name" value={name} onChange={(e) => setName(e.target.value)} required />
        <TextField size="small" select label="Type" value={type} onChange={(e) => setType(e.target.value)} sx={{ minWidth: 140 }}>
          <MenuItem value="individual">Individual</MenuItem>
          <MenuItem value="corporate">Corporate</MenuItem>
        </TextField>
        <TextField size="small" label="Email" value={email} onChange={(e) => setEmail(e.target.value)} />
        <Button type="submit" variant="contained">
          Add customer
        </Button>
      </Box>
      <Table size="small">
        <TableHead>
          <TableRow>
            <TableCell>Name</TableCell>
            <TableCell>Type</TableCell>
            <TableCell>Email</TableCell>
            <TableCell>Phone</TableCell>
            <TableCell>KYC</TableCell>
          </TableRow>
        </TableHead>
        <TableBody>
          {rows.length === 0 ? (
            <TableRow>
              <TableCell colSpan={5}>No customers yet.</TableCell>
            </TableRow>
          ) : (
            rows.map((c) => (
              <TableRow key={c.id}>
                <TableCell>{c.display_name}</TableCell>
                <TableCell>{c.type}</TableCell>
                <TableCell>{c.email ?? '—'}</TableCell>
                <TableCell>{c.phone_e164 ?? '—'}</TableCell>
                <TableCell>{c.kyc_status}</TableCell>
              </TableRow>
            ))
          )}
        </TableBody>
      </Table>
    </Stack>
  );
}
