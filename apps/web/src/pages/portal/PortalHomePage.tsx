import { Alert, Button, Paper, Stack, Typography } from '@mui/material';
import { useNavigate } from 'react-router-dom';
import { useAuth } from '../../store/auth';

export function PortalHomePage() {
  const { user } = useAuth();
  const navigate = useNavigate();

  return (
    <Stack spacing={2}>
      <Typography variant="h4">Welcome, {user?.customer?.display_name ?? user?.name}</Typography>
      <Typography color="text.secondary">
        Self-service with {user?.organization?.legal_name ?? 'your agency'}: policies, premiums, claims, renewals and
        support.
      </Typography>
      <Alert severity="info">
        Agency-held records and sandbox payments only. Cover binding and claim settlement remain with the insurer.
      </Alert>
      <Paper sx={{ p: 3, border: '1px solid', borderColor: 'divider' }}>
        <Typography variant="h6" gutterBottom>
          Quick links
        </Typography>
        <Stack direction="row" sx={{ flexWrap: 'wrap', gap: 1 }}>
          <Button variant="outlined" onClick={() => navigate('/portal/policies')}>
            Policies
          </Button>
          <Button variant="outlined" onClick={() => navigate('/portal/payments')}>
            Premiums
          </Button>
          <Button variant="outlined" onClick={() => navigate('/portal/claims')}>
            Claims
          </Button>
          <Button variant="outlined" onClick={() => navigate('/portal/products')}>
            Products
          </Button>
          <Button variant="outlined" onClick={() => navigate('/portal/profile')}>
            Profile / OTP
          </Button>
          <Button variant="outlined" onClick={() => navigate('/portal/notifications')}>
            Notifications
          </Button>
        </Stack>
      </Paper>
    </Stack>
  );
}
