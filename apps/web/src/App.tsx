import { useEffect } from 'react';
import { BrowserRouter, Navigate, Route, Routes } from 'react-router-dom';
import { CssBaseline, ThemeProvider } from '@mui/material';
import { QueryClient, QueryClientProvider } from '@tanstack/react-query';
import { theme } from './theme';
import './i18n';
import { AdminShell } from './layouts/AdminShell';
import { PortalShell } from './layouts/PortalShell';
import { LoginPage } from './pages/LoginPage';
import { DashboardPage } from './pages/DashboardPage';
import { CustomersPage } from './pages/CustomersPage';
import { QuotesPage } from './pages/QuotesPage';
import { LeadsPage } from './pages/LeadsPage';
import { PoliciesPage } from './pages/PoliciesPage';
import { ClaimsPage } from './pages/ClaimsPage';
import { FinancePage } from './pages/FinancePage';
import { SettingsPage } from './pages/SettingsPage';
import { PortalHomePage } from './pages/portal/PortalHomePage';
import { PortalPoliciesPage } from './pages/portal/PortalPoliciesPage';
import { PortalClaimsPage } from './pages/portal/PortalClaimsPage';
import { PortalRegisterPage } from './pages/portal/PortalRegisterPage';
import { PortalForgotPasswordPage } from './pages/portal/PortalForgotPasswordPage';
import { PortalProfilePage } from './pages/portal/PortalProfilePage';
import { PortalProductsPage } from './pages/portal/PortalProductsPage';
import { PortalDocumentsPage } from './pages/portal/PortalDocumentsPage';
import { PortalPaymentsPage } from './pages/portal/PortalPaymentsPage';
import { PortalRenewalsPage } from './pages/portal/PortalRenewalsPage';
import { PortalSupportPage } from './pages/portal/PortalSupportPage';
import { PortalNotificationsPage } from './pages/portal/PortalNotificationsPage';
import { RequirePermission } from './auth/RequirePermission';
import { isPortalUser } from './auth/portal';
import { useAuth } from './store/auth';

const queryClient = new QueryClient();

function Protected({ children, portal }: { children: React.ReactNode; portal?: boolean }) {
  const { user, loading, loadMe } = useAuth();

  useEffect(() => {
    void loadMe();
  }, [loadMe]);

  if (loading && !user) {
    return null;
  }

  if (!user && !localStorage.getItem('wabantu_token')) {
    return <Navigate to="/login" replace />;
  }

  if (!user) {
    return null;
  }

  if (portal && !isPortalUser(user)) {
    return <Navigate to="/" replace />;
  }

  if (!portal && isPortalUser(user)) {
    return <Navigate to="/portal" replace />;
  }

  return children;
}

export default function App() {
  return (
    <QueryClientProvider client={queryClient}>
      <ThemeProvider theme={theme}>
        <CssBaseline />
        <BrowserRouter>
          <Routes>
            <Route path="/login" element={<LoginPage />} />
            <Route path="/portal/register" element={<PortalRegisterPage />} />
            <Route path="/portal/forgot-password" element={<PortalForgotPasswordPage />} />
            <Route
              path="/portal"
              element={
                <Protected portal>
                  <PortalShell />
                </Protected>
              }
            >
              <Route index element={<PortalHomePage />} />
              <Route path="profile" element={<PortalProfilePage />} />
              <Route path="products" element={<PortalProductsPage />} />
              <Route path="policies" element={<PortalPoliciesPage />} />
              <Route path="documents" element={<PortalDocumentsPage />} />
              <Route path="payments" element={<PortalPaymentsPage />} />
              <Route path="claims" element={<PortalClaimsPage />} />
              <Route path="renewals" element={<PortalRenewalsPage />} />
              <Route path="support" element={<PortalSupportPage />} />
              <Route path="notifications" element={<PortalNotificationsPage />} />
            </Route>
            <Route
              path="/"
              element={
                <Protected>
                  <AdminShell />
                </Protected>
              }
            >
              <Route index element={<DashboardPage />} />
              <Route
                path="customers"
                element={
                  <RequirePermission anyOf={['customer.view']} soft>
                    <CustomersPage />
                  </RequirePermission>
                }
              />
              <Route
                path="leads"
                element={
                  <RequirePermission anyOf={['customer.view']} soft>
                    <LeadsPage />
                  </RequirePermission>
                }
              />
              <Route
                path="quotes"
                element={
                  <RequirePermission anyOf={['quote.create']} soft>
                    <QuotesPage />
                  </RequirePermission>
                }
              />
              <Route
                path="policies"
                element={
                  <RequirePermission anyOf={['policy.view']} soft>
                    <PoliciesPage />
                  </RequirePermission>
                }
              />
              <Route
                path="claims"
                element={
                  <RequirePermission anyOf={['claim.submit']} soft>
                    <ClaimsPage />
                  </RequirePermission>
                }
              />
              <Route
                path="finance"
                element={
                  <RequirePermission anyOf={['finance.reconcile', 'commission.approve']} soft>
                    <FinancePage />
                  </RequirePermission>
                }
              />
              <Route
                path="settings"
                element={
                  <RequirePermission anyOf={['integration.manage', 'tenant.configure']} soft>
                    <SettingsPage />
                  </RequirePermission>
                }
              />
            </Route>
          </Routes>
        </BrowserRouter>
      </ThemeProvider>
    </QueryClientProvider>
  );
}
