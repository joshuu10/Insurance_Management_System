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
  TextField,
  Typography,
} from '@mui/material';
import { api } from '../../api/client';

type Product = {
  id: string;
  name: string;
  code: string;
  coverage_summary: string | null;
  eligibility_guidance: string;
  carrier?: { legal_name: string; is_illustrative?: boolean };
};

export function PortalProductsPage() {
  const [rows, setRows] = useState<Product[]>([]);
  const [productId, setProductId] = useState('');
  const [subject, setSubject] = useState('Quotation request');
  const [body, setBody] = useState('');
  const [message, setMessage] = useState<string | null>(null);
  const [error, setError] = useState<string | null>(null);

  useEffect(() => {
    void api
      .get<{ data: Product[] }>('/portal/products')
      .then((res) => {
        setRows(res.data.data ?? []);
        if (res.data.data?.[0]) setProductId(res.data.data[0].id);
      })
      .catch(() => setError('Unable to load products.'));
  }, []);

  const enquire = async () => {
    setError(null);
    try {
      const { data } = await api.post('/portal/service-requests', {
        type: 'quotation',
        subject,
        body: body || 'Please contact me about this product.',
        carrier_product_id: productId || null,
      });
      setMessage(data.acknowledgement ?? 'Enquiry submitted for staff follow-up.');
      setBody('');
    } catch {
      setError('Enquiry failed.');
    }
  };

  return (
    <Stack spacing={2}>
      <Typography variant="h4">Products</Typography>
      <Alert severity="info">Browse appointed products. Enquiries are for staff follow-up — not live pricing or binding cover.</Alert>
      {error && <Alert severity="error">{error}</Alert>}
      {message && <Alert severity="success">{message}</Alert>}

      <Table size="small">
        <TableHead>
          <TableRow>
            <TableCell>Product</TableCell>
            <TableCell>Carrier</TableCell>
            <TableCell>Summary</TableCell>
          </TableRow>
        </TableHead>
        <TableBody>
          {rows.map((p) => (
            <TableRow key={p.id} selected={p.id === productId} onClick={() => setProductId(p.id)} sx={{ cursor: 'pointer' }}>
              <TableCell>
                {p.name}
                <Typography variant="caption" sx={{ display: 'block' }}>
                  {p.code}
                </Typography>
              </TableCell>
              <TableCell>{p.carrier?.legal_name}</TableCell>
              <TableCell>{p.coverage_summary ?? p.eligibility_guidance}</TableCell>
            </TableRow>
          ))}
        </TableBody>
      </Table>

      <Typography variant="h6">Request quotation / enquiry</Typography>
      <TextField label="Subject" value={subject} onChange={(e) => setSubject(e.target.value)} />
      <TextField label="Details" multiline minRows={3} value={body} onChange={(e) => setBody(e.target.value)} />
      <Button variant="contained" onClick={() => void enquire()} disabled={!productId} sx={{ alignSelf: 'flex-start' }}>
        Submit enquiry
      </Button>
    </Stack>
  );
}
