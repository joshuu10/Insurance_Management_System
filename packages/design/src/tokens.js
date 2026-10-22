/** Shared Wabantu design tokens — web (MUI) and mobile (Paper) consume these. */
export const colors = {
  navy: '#0B1F3B',
  blue: '#2563EB',
  teal: '#0D9488',
  offWhite: '#F6F8FC',
  charcoal: '#1F2937',
  success: '#059669',
  warning: '#D97706',
  error: '#DC2626',
  border: '#E5E7EB',
  white: '#FFFFFF',
};

export const radii = {
  sm: 10,
  md: 12,
  lg: 14,
};

export const spacing = 8;

export const typography = {
  fontFamilyWeb: '"Inter", "Segoe UI", system-ui, sans-serif',
  fontFamilyNative: 'System',
};

export const statusTaxonomy = {
  draft: { labelEn: 'Draft', labelSw: 'Rasimu', tone: 'neutral' },
  pending: { labelEn: 'Pending', labelSw: 'Inasubiri', tone: 'warning' },
  active: { labelEn: 'Active', labelSw: 'Inatumika', tone: 'success' },
  expired: { labelEn: 'Expired', labelSw: 'Imeisha', tone: 'error' },
  sandbox: { labelEn: 'Sandbox (not live)', labelSw: 'Sandbox (si hai)', tone: 'warning' },
  not_configured: { labelEn: 'Not configured', labelSw: 'Haijasanidiwa', tone: 'neutral' },
};
