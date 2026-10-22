import { Alert, Stack, Typography } from '@mui/material';
import { useTranslation } from 'react-i18next';

export function PlaceholderPage({ titleKey }: { titleKey: string }) {
  const { t } = useTranslation();
  return (
    <Stack spacing={2}>
      <Typography variant="h4">{t(titleKey)}</Typography>
      <Alert severity="warning">
        Domain module scheduled for a later phase. Navigation is wired; business logic is not claimed complete.
      </Alert>
    </Stack>
  );
}
