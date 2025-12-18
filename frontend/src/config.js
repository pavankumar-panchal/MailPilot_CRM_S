// Environment Configuration
// Automatically detects if running on localhost or production server

const isLocalhost = () => {
  const host = window.location.hostname;
  return host === 'localhost' || host === '127.0.0.1' || host.includes('192.168.');
};

const isDevServer = () => {
  // Check if running on Vite dev server (port 5173)
  return window.location.port === '5173';
};

// Base URLs
const LOCAL_BASE = 'http://localhost/verify_emails/MailPilot_CRM';
const PRODUCTION_BASE = 'https://payrollsoft.in/emailvalidation';

export const getBaseUrl = () => {
  // For production server, always use PRODUCTION_BASE unless explicitly on localhost
  if (isDevServer() || isLocalhost()) {
    return LOCAL_BASE;
  }
  return PRODUCTION_BASE;
};

// API Endpoints
const BASE_URL = getBaseUrl();

export const API_CONFIG = {
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
  
  // API Routes
  API_UPLOAD: `${BASE_URL}/backend/routes/api.php/api/upload`,
  API_RETRY_FAILED: `${BASE_URL}/backend/routes/api.php/api/retry-failed`,
  API_RESULTS: `${BASE_URL}/backend/routes/api.php/api/results`,
  API_WORKERS: `${BASE_URL}/backend/routes/api.php/api/workers`,
  API_CAMPAIGNS: `${BASE_URL}/backend/routes/api.php/api/master/campaigns`, // CRUD operations
  API_MASTER_CAMPAIGNS: `${BASE_URL}/backend/routes/api.php/api/master/campaigns_master`, // Status/operations
  API_MASTER_SMTPS: `${BASE_URL}/backend/routes/api.php/api/master/smtps`,
  API_MONITOR_CAMPAIGNS: `${BASE_URL}/backend/routes/api.php/api/monitor/campaigns`,
  
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
