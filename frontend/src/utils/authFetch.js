/**
 * Authenticated API Fetch Helper
 * Automatically includes session cookies AND Authorization token
 * This ensures requests work even if cookies fail
 */

/**
 * Make an authenticated API request
 * @param {string} url - API endpoint URL
 * @param {object} options - Fetch options (method, headers, body, etc.)
 * @returns {Promise<Response>} - Fetch response
 */
export async function authFetch(url, options = {}) {
  // Get stored token from localStorage
  const token = localStorage.getItem('mailpilot_token');
  const tokenExpiry = localStorage.getItem('mailpilot_token_expiry');
  
  // Debug logging
  console.log('[authFetch] URL:', url);
  console.log('[authFetch] Token present:', !!token);
  console.log('[authFetch] Token expiry:', tokenExpiry);
  
  // Check if token is expired
  if (tokenExpiry) {
    const expiryTime = new Date(tokenExpiry).getTime();
    if (Date.now() > expiryTime) {
      // Token expired - clear storage and redirect to login
      console.log('[authFetch] Token expired, redirecting to login');
      localStorage.removeItem('mailpilot_user');
      localStorage.removeItem('mailpilot_token');
      localStorage.removeItem('mailpilot_token_expiry');
      window.location.href = '/login';
      throw new Error('Session expired');
    }
  }
  
  // Merge headers with Authorization token
  const headers = {
    ...options.headers,
  };
  
  // Add Authorization header if token exists
  if (token) {
    headers['Authorization'] = `Bearer ${token}`;
    console.log('[authFetch] Added Authorization header');
  } else {
    console.warn('[authFetch] No token found in localStorage');
  }
  
  // Always include credentials for cookies
  const fetchOptions = {
    ...options,
    headers,
    credentials: 'include', // Send cookies too
  };
  
  try {
    const response = await fetch(url, fetchOptions);
    
    console.log('[authFetch] Response status:', response.status);
    
    // Handle 401 Unauthorized
    if (response.status === 401) {
      console.log('[authFetch] 401 Unauthorized, redirecting to login');
      // Clear storage and redirect to login
      localStorage.removeItem('mailpilot_user');
      localStorage.removeItem('mailpilot_token');
      localStorage.removeItem('mailpilot_token_expiry');
      
      // Only redirect if not already on login page
      if (!window.location.pathname.includes('/login')) {
        window.location.href = '/login';
      }
    }
    
    return response;
  } catch (error) {
    console.error('[authFetch] Request failed:', error);
    throw error;
  }
}

/**
 * Shorthand for GET request
 */
export async function authGet(url) {
  return authFetch(url, { method: 'GET' });
}

/**
 * Shorthand for POST request with JSON body
 */
export async function authPost(url, data) {
  return authFetch(url, {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify(data),
  });
}

/**
 * Shorthand for PUT request with JSON body
 */
export async function authPut(url, data) {
  return authFetch(url, {
    method: 'PUT',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify(data),
  });
}

/**
 * Shorthand for DELETE request
 */
export async function authDelete(url) {
  return authFetch(url, { method: 'DELETE' });
}
