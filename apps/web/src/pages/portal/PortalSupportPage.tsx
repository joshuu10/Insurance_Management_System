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
import { api } from '../../api/client';

type Req = {
  id: string;
  type: string;
  status: string;
  subject: string;
  body: string;
  staff_response: string | null;
  created_at: string;
};

export function PortalSupportPage() {
  const [rows, setRows] = useState<Req[]>([]);
  const [type, setType] = useState('support');
  const [subject, setSubject] = useState('');
  const [body, setBody] = useState('');
  const [error, setError] = useState<string | null>(null);
  const [message, setMessage] = useState<string | null>(null);

  const load = async () => {
    const { data } = await api.get<{ data: Req[] }>('/portal/service-requests');
    setRows(data.data ?? []);
  };

  useEffect(() => {
    void load().catch(() => setError('Unable to load requests.'));
  }, []);

  const submit = async () => {
    setError(null);
    try {
      await api.post('/portal/service-requests', { type, subject, body });
      setMessage('Request submitted.');
      setSubject('');
      setBody('');
      await load();
    } catch {
      setError('Submit failed.');
    }
  };

  return (
    <Stack spacing={2}>
      <Typography variant="h4">Support & service</Typography>
      {error && <Alert severity="error">{error}</Alert>}
      {message && <Alert severity="success">{message}</Alert>}

      <Stack spacing={1} sx={{ maxWidth: 560 }}>
        <TextField select label="Type" value={type} onChange={(e) => setType(e.target.value)}>
          <MenuItem value="support">Support</MenuItem>
          <MenuItem value="general">General</MenuItem>
          <MenuItem value="product_enquiry">Product enquiry</MenuItem>
          <MenuItem value="renewal">Renewal</MenuItem>
          <MenuItem value="quotation">Quotation</MenuItem>
        </TextField>
        <TextField label="Subject" value={subject} onChange={(e) => setSubject(e.target.value)} />
        <TextField label="Details" multiline minRows={3} value={body} onChange={(e) => setBody(e.target.value)} />
        <Button variant="contained" disabled={subject.length < 3 || body.length < 10} onClick={() => void submit()} sx={{ alignSelf: 'flex-start' }}>
          Submit request
        </Button>
      </Stack>

      <Typography variant="h6">Your requests</Typography>
      <Table size="small">
        <TableHead>
          <TableRow>
            <TableCell>Subject</TableCell>
            <TableCell>Type</TableCell>
            <TableCell>Status</TableCell>
            <TableCell>Staff response</TableCell>
          </TableRow>
        </TableHead>
        <TableBody>
          {rows.map((r) => (
            <TableRow key={r.id}>
              <TableCell>{r.subject}</TableCell>
              <TableCell>{r.type}</TableCell>
              <TableCell>
                <Chip size="small" label={r.status} />
              </TableCell>
              <TableCell>{r.staff_response ?? '—'}</TableCell>
            </TableRow>
          ))}
        </TableBody>
      </Table>
    </Stack>
  );
}
