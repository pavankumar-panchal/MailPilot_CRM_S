// Environment Configuration
// Uses Vite environment variables for configuration

// Helper to safely log only in development
const isDevelopment = import.meta.env.DEV;
const enableLogs = import.meta.env.VITE_ENABLE_CONSOLE_LOGS === 'true';

const logInfo = (...args) => {
  if (isDevelopment && enableLogs) {
    console.log(...args);
  }
};

// Get base URL from environment variable or fallback to auto-detection
export const getBaseUrl = () => {
  // Use environment variable if available (recommended for production)
  if (import.meta.env.VITE_API_BASE_URL) {
    const baseUrl = import.meta.env.VITE_API_BASE_URL;
    logInfo('🔧 Using configured base URL:', baseUrl);
    return baseUrl;
  }
  
  // Fallback to auto-detection (for backward compatibility)
  const host = window.location.hostname;
  const protocol = window.location.protocol;
  const port = window.location.port;
  
  // Check if running on Vite dev server
  const isDevServer = ['5173', '5174', '5175', '5176'].includes(port);
  
  // Check for localhost variations
  const isLocalhost = protocol === 'file:' ||
    host === 'localhost' || 
    host === '127.0.0.1' || 
    host.includes('192.168.') ||
    host.includes('10.0.') ||
    host.endsWith('.local');
  
  const isLocal = isDevServer || isLocalhost;
  const baseUrl = isLocal 
    ? 'http://localhost/verify_emails/MailPilot_CRM_S'
    : 'https://payrollsoft.in/emailvalidation';
  
  logInfo('🔧 Environment Detection:', {
    hostname: host,
    port: port,
    protocol: protocol,
    isLocalhost,
    isDevServer,
    selectedBase: isLocal ? 'LOCAL' : 'PRODUCTION',
    baseUrl
  });
  
  return baseUrl;
};

// API Endpoints
const BASE_URL = getBaseUrl();

export const API_CONFIG = {
  // Base URL for direct access
  BASE_URL: BASE_URL,
  
  // Backend base paths
  BACKEND_BASE: `${BASE_URL}/backend`,
  INCLUDES: `${BASE_URL}/backend/includes`,
  PUBLIC: `${BASE_URL}/backend/public`,
  ROUTES: `${BASE_URL}/backend/routes`,
  APP: `${BASE_URL}/backend/app`,
  STORAGE: `${BASE_URL}/backend/storage`,
  
  // Specific API endpoints
  PROGRESS: `${BASE_URL}/backend/includes/progress.php`,
  RETRY_SMTP: `${BASE_URL}/backend/includes/retry_smtp.php`,
  GET_CSV_LIST: `${BASE_URL}/backend/includes/get_csv_list.php`,
  GET_RESULTS: `${BASE_URL}/backend/includes/get_results.php`,
  UPLOAD_IMAGE: `${BASE_URL}/backend/includes/upload_image.php`,
  
  // All APIs through router using query parameters (works on all servers)
  API_LOGIN: `${BASE_URL}/backend/routes/api.php?endpoint=/api/login`,
  API_REGISTER: `${BASE_URL}/backend/routes/api.php?endpoint=/api/register`,
  API_LOGOUT: `${BASE_URL}/backend/routes/api.php?endpoint=/api/logout`,
  API_VERIFY_SESSION: `${BASE_URL}/backend/routes/api.php?endpoint=/api/verify_session`,
  API_SMTP_SERVERS: `${BASE_URL}/backend/routes/api.php?endpoint=/api/master/smtps`,
  API_SMTP_ACCOUNTS: `${BASE_URL}/backend/includes/smtp_accounts.php`,
  API_WORKERS: `${BASE_URL}/backend/routes/api.php?endpoint=/api/workers`,
  API_CAMPAIGNS: `${BASE_URL}/backend/routes/api.php?endpoint=/api/master/campaigns`,
  API_MAIL_TEMPLATES: `${BASE_URL}/backend/includes/mail_templates.php`,
  API_IMPORT_DATA: `${BASE_URL}/backend/includes/import_data.php`,
  API_EMAIL_PROCESSOR: `${BASE_URL}/backend/public/email_processor.php`,
  API_UPLOAD: `${BASE_URL}/backend/routes/api.php?endpoint=/api/upload`,
  API_RESULTS: `${BASE_URL}/backend/includes/import_data.php?action=get_batch`,
  
  // Aliases for backward compatibility
  API_MASTER_SMTPS: `${BASE_URL}/backend/routes/api.php?endpoint=/api/master/smtps`,
  API_MASTER_CAMPAIGNS: `${BASE_URL}/backend/routes/api.php?endpoint=/api/master/campaigns_master`,
  
  // Export endpoints
  API_EXPORT_CAMPAIGN: `${BASE_URL}/backend/api/export_campaign.php`,
  
  // Legacy endpoints
  API_RETRY_FAILED: `${BASE_URL}/backend/routes/api.php?endpoint=/api/retry-failed`,
  API_MONITOR_CAMPAIGNS: `${BASE_URL}/backend/routes/api.php?endpoint=/api/monitor/campaigns`,
  
  // App endpoints
  APP_EMAIL_RESPONSE: `${BASE_URL}/backend/app/email_responce.php`,
  APP_RECEIVED_RESPONSE: `${BASE_URL}/backend/app/received_response.php`,
  
  // Storage paths
  STORAGE_IMAGES: `${BASE_URL}/backend/storage/images`,
  STORAGE_ATTACHMENTS: `${BASE_URL}/backend/storage/attachments`,
};

// Helper function to get full URL
export const getApiUrl = (endpoint) => {
  return API_CONFIG[endpoint] || endpoint;
};

// Export base URL for backward compatibility
export default BASE_URL;
