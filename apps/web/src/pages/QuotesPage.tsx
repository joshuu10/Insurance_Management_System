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

type Quote = {
  id: string;
  reference: string;
  status: string;
  premium_classification: string;
  premium_amount: string | null;
  currency: string;
  customer?: { display_name: string };
  carrier?: { legal_name: string; is_illustrative?: boolean };
  carrier_product?: { name: string };
};

type Customer = { id: string; display_name: string };
type Product = { id: string; name: string; carrier?: { legal_name: string } };

export function QuotesPage() {
  const { user } = useAuth();
  const canCreate = can(user, 'quote.create');
  const [quotes, setQuotes] = useState<Quote[]>([]);
  const [customers, setCustomers] = useState<Customer[]>([]);
  const [products, setProducts] = useState<Product[]>([]);
  const [customerId, setCustomerId] = useState('');
  const [productId, setProductId] = useState('');
  const [message, setMessage] = useState<string | null>(null);
  const [error, setError] = useState<string | null>(null);

  const load = async () => {
    const [q, c, p] = await Promise.all([
      api.get<{ data: Quote[] }>('/quotes'),
      api.get<{ data: Customer[] }>('/customers'),
      api.get<{ data: Product[] }>('/catalog/products'),
    ]);
    setQuotes(q.data.data ?? []);
    setCustomers(c.data.data ?? []);
    setProducts(p.data.data ?? []);
    if (!customerId && c.data.data?.[0]) setCustomerId(c.data.data[0].id);
    if (!productId && p.data.data?.[0]) setProductId(p.data.data[0].id);
  };

  useEffect(() => {
    void load().catch(() => setError('Unable to load quotes catalogue.'));
  }, []);

  const create = async () => {
    setError(null);
    setMessage(null);
    try {
      await api.post('/quotes', {
        customer_id: customerId,
        carrier_product_id: productId,
        risk_summary: { note: 'Draft risk summary — complete underwriting questions before insurer submit.' },
      });
      setMessage('Draft quote created. Premium remains awaiting insurer.');
      await load();
    } catch (e: unknown) {
      setError('Quote create failed. Carrier appointment may be missing.');
    }
  };

  const submit = async (id: string) => {
    setError(null);
    try {
      const { data } = await api.post(`/quotes/${id}/submit`, { channel: 'sandbox' });
      setMessage(data.data?.notice ?? 'Submitted to sandbox/manual queue.');
      await load();
    } catch {
      setError('Submit failed.');
    }
  };

  return (
    <Stack spacing={2}>
      <Typography variant="h4">Quotes</Typography>
      <Alert severity="info">
        Official premiums are never invented. Sandbox submit queues insurer handoff without fabricating confirmation.
      </Alert>
      {error && <Alert severity="error">{error}</Alert>}
      {message && <Alert severity="success">{message}</Alert>}

      {canCreate && (
        <Stack direction={{ xs: 'column', sm: 'row' }} spacing={1}>
          <TextField select size="small" label="Customer" value={customerId} onChange={(e) => setCustomerId(e.target.value)} sx={{ minWidth: 200 }}>
            {customers.map((c) => (
              <MenuItem key={c.id} value={c.id}>
                {c.display_name}
              </MenuItem>
            ))}
          </TextField>
          <TextField select size="small" label="Product" value={productId} onChange={(e) => setProductId(e.target.value)} sx={{ minWidth: 260 }}>
            {products.map((p) => (
              <MenuItem key={p.id} value={p.id}>
                {p.name}
              </MenuItem>
            ))}
          </TextField>
          <Button variant="contained" onClick={() => void create()} disabled={!customerId || !productId}>
            New draft quote
          </Button>
        </Stack>
      )}

      <Table size="small">
        <TableHead>
          <TableRow>
            <TableCell>Reference</TableCell>
            <TableCell>Customer</TableCell>
            <TableCell>Carrier / product</TableCell>
            <TableCell>Status</TableCell>
            <TableCell>Premium</TableCell>
            <TableCell />
          </TableRow>
        </TableHead>
        <TableBody>
          {quotes.length === 0 ? (
            <TableRow>
              <TableCell colSpan={6}>No quotes yet.</TableCell>
            </TableRow>
          ) : (
            quotes.map((q) => (
              <TableRow key={q.id}>
                <TableCell>{q.reference}</TableCell>
                <TableCell>{q.customer?.display_name ?? '—'}</TableCell>
                <TableCell>
                  {q.carrier?.legal_name}
                  <br />
                  <Typography variant="caption">{q.carrier_product?.name}</Typography>
                </TableCell>
                <TableCell>
                  <Chip size="small" label={q.status} />
                </TableCell>
                <TableCell>
                  {q.premium_amount != null
                    ? `${q.currency} ${q.premium_amount}`
                    : q.premium_classification}
                </TableCell>
                <TableCell>
                  {canCreate &&
                    (q.status === 'draft' || q.status === 'validated' || q.status === 'awaiting_insurer') && (
                      <Button size="small" onClick={() => void submit(q.id)}>
                        Sandbox submit
                      </Button>
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
