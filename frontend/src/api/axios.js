import axios from 'axios';

// Create axios instance with default config
const axiosInstance = axios.create({
  headers: {
    'Content-Type': 'application/json',
  },
  withCredentials: true, // Enable cookies for session-based auth
});

// Add request interceptor to include auth token
axiosInstance.interceptors.request.use(
  (config) => {
    // Get token from localStorage
    const token = localStorage.getItem('mailpilot_token');
    const tokenExpiry = localStorage.getItem('mailpilot_token_expiry');
    
    // Check if token is expired
    if (tokenExpiry) {
      const expiryTime = new Date(tokenExpiry).getTime();
      if (Date.now() > expiryTime) {
        // Token expired - clear storage and redirect to login
        localStorage.removeItem('mailpilot_user');
        localStorage.removeItem('mailpilot_token');
        localStorage.removeItem('mailpilot_token_expiry');
        if (!window.location.pathname.includes('/login')) {
          window.location.href = '/login';
        }
        return Promise.reject(new Error('Session expired'));
      }
    }
    
    // Add Authorization header if token exists
    if (token) {
      config.headers['Authorization'] = `Bearer ${token}`;
    }
    
    console.log('API Request:', config.method?.toUpperCase(), config.url);
    return config;
  },
  (error) => {
    console.error('Request Error:', error);
    return Promise.reject(error);
  }
);

// Add response interceptor for debugging and auth errors
axiosInstance.interceptors.response.use(
  (response) => {
    console.log('API Response:', response.status, response.config.url);
    return response;
  },
  (error) => {
    console.error('API Error:', {
      url: error.config?.url,
      method: error.config?.method,
      status: error.response?.status,
      data: error.response?.data,
      message: error.message
    });
    
    // Handle 401 Unauthorized
    if (error.response?.status === 401) {
      console.log('401 Unauthorized - redirecting to login');
      localStorage.removeItem('mailpilot_user');
      localStorage.removeItem('mailpilot_token');
      localStorage.removeItem('mailpilot_token_expiry');
      
      if (!window.location.pathname.includes('/login')) {
        window.location.href = '/login';
      }
    }
    
    return Promise.reject(error);
  }
);

export default axiosInstance;
