import { useState } from 'react';
import { Outlet, useNavigate, useLocation } from 'react-router-dom';
import {
  AppBar,
  Box,
  Button,
  Chip,
  Drawer,
  IconButton,
  List,
  ListItemButton,
  ListItemIcon,
  ListItemText,
  Toolbar,
  Typography,
  Divider,
  useMediaQuery,
  useTheme,
} from '@mui/material';
import MenuIcon from '@mui/icons-material/Menu';
import HomeIcon from '@mui/icons-material/Home';
import PolicyIcon from '@mui/icons-material/Policy';
import ReportIcon from '@mui/icons-material/Report';
import PersonIcon from '@mui/icons-material/Person';
import StorefrontIcon from '@mui/icons-material/Storefront';
import PaymentsIcon from '@mui/icons-material/Payments';
import SupportAgentIcon from '@mui/icons-material/SupportAgent';
import NotificationsIcon from '@mui/icons-material/Notifications';
import EventRepeatIcon from '@mui/icons-material/EventRepeat';
import DescriptionIcon from '@mui/icons-material/Description';
import { useAuth } from '../store/auth';

const drawerWidth = 260;

const nav = [
  { path: '/portal', label: 'Home', icon: <HomeIcon />, end: true },
  { path: '/portal/profile', label: 'Profile', icon: <PersonIcon /> },
  { path: '/portal/products', label: 'Products', icon: <StorefrontIcon /> },
  { path: '/portal/policies', label: 'My policies', icon: <PolicyIcon /> },
  { path: '/portal/documents', label: 'Documents', icon: <DescriptionIcon /> },
  { path: '/portal/payments', label: 'Premiums & payments', icon: <PaymentsIcon /> },
  { path: '/portal/claims', label: 'My claims', icon: <ReportIcon /> },
  { path: '/portal/renewals', label: 'Renewals', icon: <EventRepeatIcon /> },
  { path: '/portal/support', label: 'Support', icon: <SupportAgentIcon /> },
  { path: '/portal/notifications', label: 'Notifications', icon: <NotificationsIcon /> },
];

export function PortalShell() {
  const { user, logout } = useAuth();
  const navigate = useNavigate();
  const location = useLocation();
  const theme = useTheme();
  const compact = useMediaQuery(theme.breakpoints.down('md'));
  const [open, setOpen] = useState(!compact);

  const selected = (path: string, end?: boolean) =>
    end ? location.pathname === path : location.pathname.startsWith(path);

  const drawer = (
    <Box sx={{ display: 'flex', flexDirection: 'column', height: '100%' }}>
      <Toolbar sx={{ px: 2 }}>
        <Box>
          <Typography variant="subtitle1" color="primary" sx={{ fontWeight: 700 }}>
            Customer portal
          </Typography>
          <Typography variant="caption" color="text.secondary">
            {user?.organization?.legal_name ?? 'Your agency'}
          </Typography>
        </Box>
      </Toolbar>
      <Divider />
      <List sx={{ flex: 1, px: 1, overflow: 'auto' }}>
        {nav.map((item) => (
          <ListItemButton
            key={item.path}
            selected={selected(item.path, item.end)}
            onClick={() => {
              navigate(item.path);
              if (compact) setOpen(false);
            }}
            sx={{ borderRadius: 2, mb: 0.5 }}
          >
            <ListItemIcon sx={{ minWidth: 40 }}>{item.icon}</ListItemIcon>
            <ListItemText primary={item.label} />
          </ListItemButton>
        ))}
      </List>
      <Box sx={{ p: 2 }}>
        <Chip
          size="small"
          color="warning"
          variant="outlined"
          label="Agency records / sandbox — not insurer confirmation"
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
          <Typography sx={{ flex: 1 }} variant="body2">
            {user?.customer?.display_name ?? user?.name}
          </Typography>
          <Button size="small" onClick={() => void logout().then(() => navigate('/login'))}>
            Sign out
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
