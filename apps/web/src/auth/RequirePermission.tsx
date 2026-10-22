import { Navigate } from 'react-router-dom';
import { Alert, Stack, Typography } from '@mui/material';
import { useAuth } from '../store/auth';
import { canAny } from './permissions';

type Props = {
  anyOf: string[];
  children: React.ReactNode;
  /** If true, show a blocked message instead of redirecting. */
  soft?: boolean;
};

export function RequirePermission({ anyOf, children, soft = false }: Props) {
  const { user } = useAuth();

  if (canAny(user, anyOf)) {
    return children;
  }

  if (soft) {
    return (
      <Stack spacing={2}>
        <Typography variant="h5">Access restricted</Typography>
        <Alert severity="warning">
          Your role does not include the permissions needed for this page
          {anyOf.length ? ` (${anyOf.join(' or ')})` : ''}.
        </Alert>
      </Stack>
    );
  }

  return <Navigate to="/" replace />;
}
