import { useEffect, useState } from 'react';
import { Alert, Button, Stack, Table, TableBody, TableCell, TableHead, TableRow, Typography } from '@mui/material';
import { api } from '../../api/client';

type Note = {
  id: string;
  category: string;
  title: string;
  body: string;
  channel: string;
  is_read: boolean;
  created_at: string;
};

export function PortalNotificationsPage() {
  const [rows, setRows] = useState<Note[]>([]);
  const [history, setHistory] = useState<{ notifications: Note[]; service_requests: unknown[] } | null>(null);
  const [error, setError] = useState<string | null>(null);

  const load = async () => {
    const [n, c] = await Promise.all([
      api.get<{ data: Note[] }>('/portal/notifications'),
      api.get<{ data: { notifications: Note[]; service_requests: unknown[] } }>('/portal/communications'),
    ]);
    setRows(n.data.data ?? []);
    setHistory(c.data.data);
  };

  useEffect(() => {
    void load().catch(() => setError('Unable to load notifications.'));
  }, []);

  const markRead = async (id: string) => {
    await api.post(`/portal/notifications/${id}/read`);
    await load();
  };

  return (
    <Stack spacing={2}>
      <Typography variant="h4">Notifications</Typography>
      <Alert severity="info">In-app channel. Push/SMS remain conditional sandbox stubs.</Alert>
      {error && <Alert severity="error">{error}</Alert>}
      <Table size="small">
        <TableHead>
          <TableRow>
            <TableCell>Title</TableCell>
            <TableCell>Category</TableCell>
            <TableCell>Channel</TableCell>
            <TableCell>When</TableCell>
            <TableCell />
          </TableRow>
        </TableHead>
        <TableBody>
          {rows.map((n) => (
            <TableRow key={n.id} sx={{ opacity: n.is_read ? 0.6 : 1 }}>
              <TableCell>
                <Typography variant="body2">{n.title}</Typography>
                <Typography variant="caption" color="text.secondary">
                  {n.body}
                </Typography>
              </TableCell>
              <TableCell>{n.category}</TableCell>
              <TableCell>{n.channel}</TableCell>
              <TableCell>{new Date(n.created_at).toLocaleString()}</TableCell>
              <TableCell>
                {!n.is_read && (
                  <Button size="small" onClick={() => void markRead(n.id)}>
                    Mark read
                  </Button>
                )}
              </TableCell>
            </TableRow>
          ))}
        </TableBody>
      </Table>
      {history && (
        <Typography variant="body2" color="text.secondary">
          Communication history: {history.notifications.length} notifications · {history.service_requests.length} service
          requests
        </Typography>
      )}
    </Stack>
  );
}
