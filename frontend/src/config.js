// Environment Configuration
// Automatically detects if running on localhost or production server

const isLocalhost = () => {
  const host = window.location.hostname;
  const protocol = window.location.protocol;
  
  // If opened as file:// (not served by a web server), default to localhost
  if (protocol === 'file:') {
    return true;
  }
  
  // Check for localhost variations
  return host === 'localhost' || 
         host === '127.0.0.1' || 
         host.includes('192.168.') ||
         host.includes('10.0.') ||
         host.endsWith('.local');
};

const isDevServer = () => {
  // Check if running on Vite dev server (ports 5173-5176)
  const port = window.location.port;
  return port === '5173' || port === '5174' || port === '5175' || port === '5176';
};

// Base URLs
const LOCAL_BASE = 'http://localhost/verify_emails/MailPilot_CRM_S';
const PRODUCTION_BASE = 'https://payrollsoft.in/emailvalidation';

export const getBaseUrl = () => {
  // For production server, always use PRODUCTION_BASE unless explicitly on localhost
  const isLocal = isDevServer() || isLocalhost();
  const baseUrl = isLocal ? LOCAL_BASE : PRODUCTION_BASE;
  
  // Log environment for debugging
  console.log('🔧 Environment Detection:', {
    hostname: window.location.hostname,
    port: window.location.port,
    protocol: window.location.protocol,
    isLocalhost: isLocalhost(),
    isDevServer: isDevServer(),
    selectedBase: isLocal ? 'LOCAL' : 'PRODUCTION',
    baseUrl: baseUrl
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
