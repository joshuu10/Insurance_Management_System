import { useEffect, useState } from 'react';
import { Alert, Button, Stack, Table, TableBody, TableCell, TableHead, TableRow, Typography } from '@mui/material';
import { api } from '../../api/client';

type Doc = {
  id: string;
  subject_type: string;
  purpose: string;
  original_filename: string;
  status: string;
  issuer: string;
};

export function PortalDocumentsPage() {
  const [rows, setRows] = useState<Doc[]>([]);
  const [error, setError] = useState<string | null>(null);

  useEffect(() => {
    void api
      .get<{ data: Doc[] }>('/portal/documents')
      .then((res) => setRows(res.data.data ?? []))
      .catch(() => setError('Unable to load documents.'));
  }, []);

  const download = async (id: string, filename: string) => {
    try {
      const res = await api.get(`/portal/documents/${id}/download`, { responseType: 'blob' });
      const url = URL.createObjectURL(res.data);
      const a = document.createElement('a');
      a.href = url;
      a.download = `${filename}.txt`;
      a.click();
      URL.revokeObjectURL(url);
    } catch {
      setError('Download failed.');
    }
  };

  return (
    <Stack spacing={2}>
      <Typography variant="h4">Documents</Typography>
      <Alert severity="info">Downloads are metadata placeholders until object storage is configured.</Alert>
      {error && <Alert severity="error">{error}</Alert>}
      <Table size="small">
        <TableHead>
          <TableRow>
            <TableCell>File</TableCell>
            <TableCell>Purpose</TableCell>
            <TableCell>Issuer</TableCell>
            <TableCell>Status</TableCell>
            <TableCell />
          </TableRow>
        </TableHead>
        <TableBody>
          {rows.length === 0 ? (
            <TableRow>
              <TableCell colSpan={5}>No documents on file.</TableCell>
            </TableRow>
          ) : (
            rows.map((d) => (
              <TableRow key={d.id}>
                <TableCell>{d.original_filename}</TableCell>
                <TableCell>{d.purpose}</TableCell>
                <TableCell>{d.issuer}</TableCell>
                <TableCell>{d.status}</TableCell>
                <TableCell>
                  <Button size="small" onClick={() => void download(d.id, d.original_filename)}>
                    Download
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
