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

type Policy = { id: string; carrier_policy_number: string; status: string };
type Claim = {
  id: string;
  reference: string;
  status: string;
  incident_at: string;
  description: string;
  incident_location?: string | null;
  policy?: { carrier_policy_number: string };
};
type Timeline = { id: string; event_type: string; message: string; created_at: string };

export function PortalClaimsPage() {
  const [claims, setClaims] = useState<Claim[]>([]);
  const [policies, setPolicies] = useState<Policy[]>([]);
  const [policyId, setPolicyId] = useState('');
  const [description, setDescription] = useState('');
  const [location, setLocation] = useState('');
  const [selectedId, setSelectedId] = useState<string | null>(null);
  const [timeline, setTimeline] = useState<Timeline[]>([]);
  const [evidenceName, setEvidenceName] = useState('');
  const [error, setError] = useState<string | null>(null);
  const [message, setMessage] = useState<string | null>(null);

  const load = async () => {
    const [c, p] = await Promise.all([
      api.get<{ data: Claim[] }>('/portal/claims'),
      api.get<{ data: Policy[] }>('/portal/policies'),
    ]);
    setClaims(c.data.data ?? []);
    const active = (p.data.data ?? []).filter((x) => x.status === 'active' || x.status === 'grace');
    setPolicies(active);
    if (!policyId && active[0]) setPolicyId(active[0].id);
  };

  useEffect(() => {
    void load().catch(() => setError('Unable to load claims.'));
  }, []);

  const createFnol = async () => {
    setError(null);
    try {
      const { data } = await api.post('/portal/claims', {
        policy_id: policyId,
        incident_at: new Date().toISOString(),
        incident_location: location || null,
        description,
        urgency: 'normal',
      });
      setDescription('');
      setMessage(data.acknowledgement ?? 'FNOL draft created.');
      await load();
    } catch {
      setError('Could not create FNOL.');
    }
  };

  const submit = async (id: string) => {
    try {
      const { data } = await api.post(`/portal/claims/${id}/submit`);
      setMessage(data.acknowledgement ?? 'Submitted.');
      await load();
    } catch {
      setError('Submit failed.');
    }
  };

  const openDetail = async (id: string) => {
    setSelectedId(id);
    try {
      const { data } = await api.get<{ data: Claim & { timeline?: Timeline[] } }>(`/portal/claims/${id}`);
      setTimeline(data.data.timeline ?? []);
    } catch {
      setError('Could not load claim detail.');
    }
  };

  const attachEvidence = async () => {
    if (!selectedId || !evidenceName) return;
    try {
      await api.post(`/portal/claims/${selectedId}/evidence`, {
        original_filename: evidenceName,
        mime_type: 'application/octet-stream',
        purpose: 'claim_evidence',
      });
      setEvidenceName('');
      setMessage('Evidence metadata registered.');
      await openDetail(selectedId);
    } catch {
      setError('Evidence attach failed.');
    }
  };

  return (
    <Stack spacing={2}>
      <Typography variant="h4">My claims</Typography>
      <Alert severity="warning">
        First notification of loss only. Agency coordinates with the insurer — this is not a settlement decision.
      </Alert>
      {error && <Alert severity="error">{error}</Alert>}
      {message && <Alert severity="success">{message}</Alert>}

      <Stack direction={{ xs: 'column', sm: 'row' }} spacing={1}>
        <TextField select size="small" label="Policy" value={policyId} onChange={(e) => setPolicyId(e.target.value)} sx={{ minWidth: 200 }}>
          {policies.map((p) => (
            <MenuItem key={p.id} value={p.id}>
              {p.carrier_policy_number}
            </MenuItem>
          ))}
        </TextField>
        <TextField size="small" label="Location" value={location} onChange={(e) => setLocation(e.target.value)} />
        <TextField size="small" label="What happened?" value={description} onChange={(e) => setDescription(e.target.value)} sx={{ flex: 1 }} />
        <Button variant="contained" disabled={!policyId || description.length < 10} onClick={() => void createFnol()}>
          Draft FNOL
        </Button>
      </Stack>

      <Table size="small">
        <TableHead>
          <TableRow>
            <TableCell>Reference</TableCell>
            <TableCell>Policy</TableCell>
            <TableCell>Status</TableCell>
            <TableCell />
          </TableRow>
        </TableHead>
        <TableBody>
          {claims.map((c) => (
            <TableRow key={c.id}>
              <TableCell>{c.reference}</TableCell>
              <TableCell>{c.policy?.carrier_policy_number}</TableCell>
              <TableCell>
                <Chip size="small" label={c.status} />
              </TableCell>
              <TableCell>
                <Button size="small" onClick={() => void openDetail(c.id)}>
                  Details
                </Button>
                {c.status === 'draft' && (
                  <Button size="small" onClick={() => void submit(c.id)}>
                    Submit
                  </Button>
                )}
              </TableCell>
            </TableRow>
          ))}
        </TableBody>
      </Table>

      {selectedId && (
        <Stack spacing={1}>
          <Typography variant="h6">Claim progress</Typography>
          {timeline.length === 0 ? (
            <Typography variant="body2">No timeline entries yet.</Typography>
          ) : (
            timeline.map((t) => (
              <Typography key={t.id} variant="body2">
                {new Date(t.created_at).toLocaleString()} — {t.event_type}: {t.message}
              </Typography>
            ))
          )}
          <Stack direction="row" spacing={1}>
            <TextField size="small" label="Evidence filename" value={evidenceName} onChange={(e) => setEvidenceName(e.target.value)} />
            <Button variant="outlined" onClick={() => void attachEvidence()}>
              Register evidence
            </Button>
          </Stack>
        </Stack>
      )}
    </Stack>
  );
}
