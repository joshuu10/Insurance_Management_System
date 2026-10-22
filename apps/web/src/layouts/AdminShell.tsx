import { useMemo, useState } from 'react';
import { Outlet, useNavigate, useLocation } from 'react-router-dom';
import { useTranslation } from 'react-i18next';
import {
  AppBar,
  Box,
  Divider,
  Drawer,
  IconButton,
  List,
  ListItemButton,
  ListItemIcon,
  ListItemText,
  Toolbar,
  Typography,
  Chip,
  Button,
  useMediaQuery,
  useTheme,
} from '@mui/material';
import MenuIcon from '@mui/icons-material/Menu';
import DashboardIcon from '@mui/icons-material/Dashboard';
import PeopleIcon from '@mui/icons-material/People';
import RequestQuoteIcon from '@mui/icons-material/RequestQuote';
import PolicyIcon from '@mui/icons-material/Policy';
import ReportIcon from '@mui/icons-material/Report';
import AccountBalanceIcon from '@mui/icons-material/AccountBalance';
import SettingsIcon from '@mui/icons-material/Settings';
import type { ReactElement } from 'react';
import { useAuth } from '../store/auth';
import { visibleNav } from '../auth/permissions';

const drawerWidth = 260;

const icons: Record<string, ReactElement> = {
  dashboard: <DashboardIcon />,
  customers: <PeopleIcon />,
  leads: <PeopleIcon />,
  quotes: <RequestQuoteIcon />,
  policies: <PolicyIcon />,
  claims: <ReportIcon />,
  finance: <AccountBalanceIcon />,
  settings: <SettingsIcon />,
};

export function AdminShell() {
  const { t, i18n } = useTranslation();
  const { user, logout } = useAuth();
  const navigate = useNavigate();
  const location = useLocation();
  const theme = useTheme();
  const compact = useMediaQuery(theme.breakpoints.down('md'));
  const [open, setOpen] = useState(!compact);

  const nav = useMemo(() => visibleNav(user), [user]);
  const roleLabel = (user?.roles ?? []).join(', ') || 'no role';

  const drawer = (
    <Box sx={{ display: 'flex', flexDirection: 'column', height: '100%' }}>
      <Toolbar sx={{ px: 2 }}>
        <Box>
          <Typography variant="subtitle1" color="primary" sx={{ fontWeight: 700 }}>
            {t('appName')}
          </Typography>
          <Typography variant="caption" color="text.secondary">
            Wabantu Technologies
          </Typography>
        </Box>
      </Toolbar>
      <Divider />
      <List sx={{ flex: 1, px: 1 }}>
        {nav.map((item) => (
          <ListItemButton
            key={item.path}
            selected={location.pathname === item.path}
            onClick={() => {
              navigate(item.path);
              if (compact) setOpen(false);
            }}
            sx={{ borderRadius: 2, mb: 0.5 }}
          >
            <ListItemIcon sx={{ minWidth: 40 }}>{icons[item.key]}</ListItemIcon>
            <ListItemText primary={t(item.key)} />
          </ListItemButton>
        ))}
      </List>
      <Box sx={{ p: 2 }}>
        <Chip
          size="small"
          label={t('sandboxNotice')}
          color="warning"
          variant="outlined"
          sx={{ height: 'auto', py: 0.5, '& .MuiChip-label': { whiteSpace: 'normal' } }}
        />
      </Box>
    </Box>
  );

  return (
    <Box sx={{ display: 'flex', minHeight: '100vh', bgcolor: 'background.default' }}>
      <AppBar
        position="fixed"
        color="inherit"
        elevation={0}
        sx={{
          borderBottom: '1px solid',
          borderColor: 'divider',
          width: { md: `calc(100% - ${drawerWidth}px)` },
          ml: { md: `${drawerWidth}px` },
        }}
      >
        <Toolbar>
          <IconButton edge="start" onClick={() => setOpen((v) => !v)} sx={{ mr: 1, display: { md: 'none' } }}>
            <MenuIcon />
          </IconButton>
          <Box sx={{ flex: 1, display: 'flex', gap: 1, flexWrap: 'wrap', alignItems: 'center' }}>
            <Chip size="small" label={`${t('organization')}: ${user?.organization?.legal_name ?? '—'}`} />
            <Chip size="small" variant="outlined" label={`${t('branch')}: ${user?.branch?.name ?? '—'}`} />
            <Chip size="small" color="secondary" variant="outlined" label={roleLabel} />
          </Box>
          <Button size="small" onClick={() => i18n.changeLanguage(i18n.language === 'en-TZ' ? 'sw-TZ' : 'en-TZ')}>
            {i18n.language === 'en-TZ' ? 'SW' : 'EN'}
          </Button>
          <Button size="small" onClick={() => void logout().then(() => navigate('/login'))}>
            {t('logout')}
          </Button>
        </Toolbar>
      </AppBar>

      <Drawer
        variant={compact ? 'temporary' : 'permanent'}
        open={open}
        onClose={() => setOpen(false)}
        sx={{
          width: drawerWidth,
          [`& .MuiDrawer-paper`]: { width: drawerWidth, boxSizing: 'border-box', borderRight: '1px solid', borderColor: 'divider' },
        }}
      >
        {drawer}
      </Drawer>

      <Box component="main" sx={{ flexGrow: 1, p: 3, mt: 8, width: { md: `calc(100% - ${drawerWidth}px)` } }}>
        <Outlet />
      </Box>
    </Box>
  );
}
