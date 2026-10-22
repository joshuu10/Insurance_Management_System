import i18n from 'i18next';
import { initReactI18next } from 'react-i18next';

const resources = {
  'en-TZ': {
    translation: {
      appName: 'Wabantu Insurance',
      tagline: 'Agency & brokerage operations for Tanzania',
      login: 'Sign in',
      email: 'Email',
      password: 'Password',
      dashboard: 'Dashboard',
      logout: 'Sign out',
      emptyDashboard: 'No operational data yet. Complete onboarding to get started.',
      sandboxNotice: 'Integrations are not live. Sandbox and manual workflows only.',
      customers: 'Customers',
      leads: 'Leads',
      quotes: 'Quotes',
      policies: 'Policies',
      claims: 'Claims',
      finance: 'Finance',
      settings: 'Settings',
      organization: 'Organization',
      branch: 'Branch',
      edition: 'Edition',
    },
  },
  'sw-TZ': {
    translation: {
      appName: 'Bima ya Wabantu',
      tagline: 'Uendeshaji wa wakala na madalali nchini Tanzania',
      login: 'Ingia',
      email: 'Barua pepe',
      password: 'Nenosiri',
      dashboard: 'Dashibodi',
      logout: 'Toka',
      emptyDashboard: 'Hakuna data bado. Kamilisha usanidi ili kuanza.',
      sandboxNotice: 'Miunganisho haijaamilishwa. Sandbox na michakato ya mkono tu.',
      customers: 'Wateja',
      leads: 'Viongozi / Leads',
      quotes: 'Nukuu',
      policies: 'Polisi',
      claims: 'Madai',
      finance: 'Fedha',
      settings: 'Mipangilio',
      organization: 'Shirika',
      branch: 'Tawi',
      edition: 'Toleo',
    },
  },
};

void i18n.use(initReactI18next).init({
  resources,
  lng: 'en-TZ',
  fallbackLng: 'en-TZ',
  interpolation: { escapeValue: false },
});

export default i18n;
