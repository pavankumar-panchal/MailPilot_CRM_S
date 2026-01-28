import React, { useState, useEffect, useCallback, useMemo } from "react";

import { TableSkeleton } from "../components/SkeletonLoader";
import { API_CONFIG } from "../config";

// Login Component
const Login = ({ onLogin, onSwitchToRegister }) => {
  const [email, setEmail] = useState('');
  const [password, setPassword] = useState('');
  const [error, setError] = useState('');
  const [loading, setLoading] = useState(false);

  const handleSubmit = async (e) => {
    e.preventDefault();
    setLoading(true);
    setError('');

    try {
      const response = await fetch('/api/login.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        credentials: 'include',
        body: JSON.stringify({ email, password }),
      });

      const data = await response.json();
      
      if (data.success) {
        // Save to localStorage
        localStorage.setItem('mailpilot_user', JSON.stringify(data.user));
        localStorage.setItem('mailpilot_token', data.token);
        onLogin(data.user, data.token);
      } else {
        setError(data.message || 'Login failed');
      }
    } catch (err) {
      console.error('Login error:', err);
      setError('Network error. Please check your connection and try again.');
    } finally {
      setLoading(false);
    }
  };

  return (
    <div className="min-h-screen flex items-center justify-center bg-gradient-to-br from-purple-600 to-blue-600 px-4">
      <div className="bg-white rounded-2xl shadow-2xl p-8 w-full max-w-md">
        <div className="text-center mb-8">
          <h2 className="text-3xl font-bold text-gray-800 mb-2">📧 Relyon CRM</h2>
          <p className="text-gray-600">Login to your account</p>
        </div>
        <form onSubmit={handleSubmit} className="space-y-6">
          <div>
            <label className="block text-sm font-medium text-gray-700 mb-2">Email Address</label>
            <input
              type="email"
              value={email}
              onChange={(e) => setEmail(e.target.value)}
              placeholder="your@email.com"
              required
              disabled={loading}
              className="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-600 focus:border-transparent"
            />
          </div>
          <div>
            <label className="block text-sm font-medium text-gray-700 mb-2">Password</label>
            <input
              type="password"
              value={password}
              onChange={(e) => setPassword(e.target.value)}
              placeholder="Enter your password"
              required
              disabled={loading}
              className="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-600 focus:border-transparent"
            />
          </div>
          {error && <div className="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded-lg">{error}</div>}
          <button 
            type="submit" 
            disabled={loading}
            className="w-full bg-gradient-to-r from-purple-600 to-blue-600 text-white py-3 rounded-lg font-semibold hover:from-purple-700 hover:to-blue-700 disabled:opacity-50 transition-all"
          >
            {loading ? 'Logging in...' : 'Login'}
          </button>
        </form>
        <p className="text-center mt-6 text-gray-600">
          Don't have an account?{' '}
          <button onClick={onSwitchToRegister} className="text-purple-600 font-semibold hover:underline">
            Register here
          </button>
        </p>
      </div>
    </div>
  );
};

// Register Component
const Register = ({ onRegister, onSwitchToLogin }) => {
  const [name, setName] = useState('');
  const [email, setEmail] = useState('');
  const [password, setPassword] = useState('');
  const [confirmPassword, setConfirmPassword] = useState('');
  const [error, setError] = useState('');
  const [success, setSuccess] = useState('');
  const [loading, setLoading] = useState(false);

  const handleSubmit = async (e) => {
    e.preventDefault();
    setLoading(true);
    setError('');
    setSuccess('');

    if (!name.trim()) {
      setError('Name is required');
      setLoading(false);
      return;
    }

    if (password.length < 6) {
      setError('Password must be at least 6 characters');
      setLoading(false);
      return;
    }

    if (password !== confirmPassword) {
      setError('Passwords do not match');
      setLoading(false);
      return;
    }

    try {
      const response = await fetch('/api/register.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        credentials: 'include',
        body: JSON.stringify({ name, email, password }),
      });

      const data = await response.json();
      
      if (data.success) {
        setSuccess(data.message || 'Registration successful! Redirecting to login...');
        setTimeout(() => onRegister(), 2000);
      } else {
        setError(data.message || 'Registration failed');
      }
    } catch (err) {
      console.error('Registration error:', err);
      setError('Network error. Please check your connection and try again.');
    } finally {
      setLoading(false);
    }
  };

  return (
    <div className="min-h-screen flex items-center justify-center bg-gradient-to-br from-purple-600 to-blue-600 px-4">
      <div className="bg-white rounded-2xl shadow-2xl p-8 w-full max-w-md">
        <div className="text-center mb-8">
          <h2 className="text-3xl font-bold text-gray-800 mb-2">📧 Relyon CRM</h2>
          <p className="text-gray-600">Create your account</p>
        </div>
        <form onSubmit={handleSubmit} className="space-y-6">
          <div>
            <label className="block text-sm font-medium text-gray-700 mb-2">Full Name</label>
            <input
              type="text"
              value={name}
              onChange={(e) => setName(e.target.value)}
              placeholder="John Doe"
              required
              disabled={loading}
              className="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-600 focus:border-transparent"
            />
          </div>
          <div>
            <label className="block text-sm font-medium text-gray-700 mb-2">Email Address</label>
            <input
              type="email"
              value={email}
              onChange={(e) => setEmail(e.target.value)}
              placeholder="your@email.com"
              required
              disabled={loading}
              className="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-600 focus:border-transparent"
            />
          </div>
          <div>
            <label className="block text-sm font-medium text-gray-700 mb-2">Password</label>
            <input
              type="password"
              value={password}
              onChange={(e) => setPassword(e.target.value)}
              placeholder="At least 6 characters"
              required
              disabled={loading}
              className="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-600 focus:border-transparent"
            />
          </div>
          <div>
            <label className="block text-sm font-medium text-gray-700 mb-2">Confirm Password</label>
            <input
              type="password"
              value={confirmPassword}
              onChange={(e) => setConfirmPassword(e.target.value)}
              placeholder="Re-enter your password"
              required
              disabled={loading}
              className="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-600 focus:border-transparent"
            />
          </div>
          {error && <div className="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded-lg">{error}</div>}
          {success && <div className="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded-lg">{success}</div>}
          <button 
            type="submit" 
            disabled={loading}
            className="w-full bg-gradient-to-r from-purple-600 to-blue-600 text-white py-3 rounded-lg font-semibold hover:from-purple-700 hover:to-blue-700 disabled:opacity-50 transition-all"
          >
            {loading ? 'Creating account...' : 'Register'}
          </button>
        </form>
        <p className="text-center mt-6 text-gray-600">
          Already have an account?{' '}
          <button onClick={onSwitchToLogin} className="text-purple-600 font-semibold hover:underline">
            Login here
          </button>
        </p>
      </div>
    </div>
  );
};

const EmailsList = ({ listId, onClose }) => {
  // Auth state
  const [isAuthenticated, setIsAuthenticated] = useState(false);
  const [authView, setAuthView] = useState('login'); // 'login' or 'register'
  const [user, setUser] = useState(null);

  // Check authentication on mount
  useEffect(() => {
    const savedUser = localStorage.getItem('mailpilot_user');
    const savedToken = localStorage.getItem('mailpilot_token');
    if (savedUser && savedToken) {
      try {
        const userData = JSON.parse(savedUser);
        setUser(userData);
        setIsAuthenticated(true);
      } catch (e) {
        localStorage.removeItem('mailpilot_user');
        localStorage.removeItem('mailpilot_token');
      }
    }
  }, []);

  const handleLogin = (userData, token) => {
    setUser(userData);
    setIsAuthenticated(true);
  };

  const handleRegister = () => {
    setAuthView('login');
  };

  const handleLogout = () => {
    // Inform backend and then clear local state
    fetch('/api/logout.php', { method: 'POST', credentials: 'include' })
      .catch(() => {})
      .finally(() => {
        localStorage.removeItem('mailpilot_user');
        localStorage.removeItem('mailpilot_token');
        setUser(null);
        setIsAuthenticated(false);
      });
  };

  // Note: render auth UI via conditional in the return to keep hooks order stable

  const [listEmails, setListEmails] = useState([]);
  const [status, setStatus] = useState(null);
  const [loading, setLoading] = useState(false);
  const [pagination, setPagination] = useState({
    page: 1,
    rowsPerPage: 50,
    total: 0,
  });
  const [filter, setFilter] = useState("all");
  const [counts, setCounts] = useState({ all: 0, valid: 0, invalid: 0, timeout: 0 });

  // Fetch email details with server-side pagination
  const fetchListEmails = useCallback(async () => {
    try {
      setLoading(true);
      const filterParam = filter !== 'all' ? `&filter=${filter}` : '';
      const url = `${API_CONFIG.API_RESULTS}&batch_id=${listId}&page=${pagination.page}&limit=${pagination.rowsPerPage}${filterParam}`;
      console.log('Fetching emails from:', url);
      
      const res = await fetch(url, { credentials: 'include' });
      
      console.log('Response status:', res.status);
      
      if (!res.ok) {
        const errorText = await res.text();
        console.error('HTTP error:', res.status, errorText);
        throw new Error(`HTTP error! status: ${res.status}`);
      }

      const data = await res.json();
      console.log('Emails data received:', data);
      
      setListEmails(Array.isArray(data.data) ? data.data : []);
      setPagination((prev) => ({
        ...prev,
        total: data.total || 0,
      }));
    } catch (error) {
      console.error("Failed to fetch emails:", error);
      setListEmails([]);
      setStatus({ type: "error", message: "Failed to load emails: " + error.message });
    } finally {
      setLoading(false);
    }
  }, [listId, pagination.page, pagination.rowsPerPage, filter]);

  // Fetch server-side counts for all filter types
  const fetchCounts = useCallback(async () => {
    try {
      const urlBase = `${API_CONFIG.API_RESULTS}&batch_id=${listId}&limit=1`;
      const [allRes, validRes, invalidRes, timeoutRes] = await Promise.all([
        fetch(urlBase, { credentials: 'include' }),
        fetch(`${urlBase}&filter=valid`, { credentials: 'include' }),
        fetch(`${urlBase}&filter=invalid`, { credentials: 'include' }),
        fetch(`${urlBase}&filter=timeout`, { credentials: 'include' }),
      ]);

      const [allJson, validJson, invalidJson, timeoutJson] = await Promise.all([
        allRes.json(),
        validRes.json(),
        invalidRes.json(),
        timeoutRes.json(),
      ]);

      setCounts({
        all: Number(allJson?.total ?? 0),
        valid: Number(validJson?.total ?? 0),
        invalid: Number(invalidJson?.total ?? 0),
        timeout: Number(timeoutJson?.total ?? 0),
      });
    } catch (error) {
      console.error("Failed to fetch counts:", error);
      // Fallback to pagination total if available
      setCounts(prev => ({ ...prev, all: pagination.total }));
    }
  }, [listId, pagination.total]);

  // Utility function for timeout detection
  const isTimeout = useCallback((e) =>
    (e.validation_response || "").toLowerCase().includes("timeout") ||
    (e.validation_response || "").toLowerCase().includes("connection refused") ||
    (e.validation_response || "").toLowerCase().includes("failed to connect"),
    []
  );

  // Fetch emails on mount and when dependencies change (only when authenticated)
  useEffect(() => {
    if (!isAuthenticated) return;
    fetchListEmails();
  }, [fetchListEmails, isAuthenticated]);

  // Fetch counts when listId changes (only when authenticated)
  useEffect(() => {
    if (!isAuthenticated) return;
    fetchCounts();
  }, [fetchCounts, isAuthenticated]);

  // Reset to page 1 when filter changes
  useEffect(() => {
    setPagination((prev) => ({
      ...prev,
      page: 1,
    }));
  }, [filter]);

  // Export emails to CSV - Uses backend API to export ALL data (not just current page)
  const exportToCSV = useCallback(async (type) => {
    try {
      // Use backend export API to get ALL data for this list
      const exportType = type === 'timeout' ? 'connection_timeout' : type;
      const url = `${API_CONFIG.GET_RESULTS}?export=${exportType}&csv_list_id=${listId}`;
      console.log('Export URL:', url);
      
      const res = await fetch(url, { credentials: 'include' });
      console.log('Export response status:', res.status);
      
      if (!res.ok) {
        throw new Error(`HTTP error! status: ${res.status}`);
      }
      
      const blob = await res.blob();
      console.log('Export blob size:', blob.size);

      const downloadUrl = window.URL.createObjectURL(blob);
      const link = document.createElement("a");
      link.href = downloadUrl;
      link.download = `${type}_emails_list_${listId}.csv`;
      document.body.appendChild(link);
      link.click();
      link.remove();
      window.URL.revokeObjectURL(downloadUrl);

      setStatus({ type: "success", message: `Exported all ${type} emails from this list` });
    } catch (error) {
      console.error('Export error:', error);
      setStatus({ type: "error", message: `Failed to export ${type} emails: ${error.message}` });
    }
  }, [listId]);

  // Status message component
  const StatusMessage = ({ status, onClose }) =>
    status && (
      <div
        className="fixed top-6 left-1/2 transform -translate-x-1/2 z-50 px-6 py-3 rounded-xl shadow text-base font-semibold flex items-center gap-3 transition-all duration-300 backdrop-blur-md"
        style={{
          minWidth: 250,
          maxWidth: 400,
          boxShadow: "0 8px 32px 0 rgba(0, 0, 0, 0.23)",
          background: status.type === "error" ? "rgba(255, 0, 0, 0.29)" : "rgba(0, 200, 83, 0.29)",
          borderRadius: "16px",
          backdropFilter: "blur(8px)",
          WebkitBackdropFilter: "blur(8px)",
        }}
        role="alert"
      >
        <i className={`fas text-lg ${status.type === "error" ? "fa-exclamation-circle text-red-500" : "fa-check-circle text-green-500"}`}></i>
        <span className="flex-1">{status.message}</span>
        <button onClick={onClose} className="ml-2 text-gray-500 hover:text-gray-700 focus:outline-none" aria-label="Close">
          <i className="fas fa-times"></i>
        </button>
      </div>
    );

  // Auto-hide status message
  useEffect(() => {
    if (status) {
      const timer = setTimeout(() => setStatus(null), 4000);
      return () => clearTimeout(timer);
    }
  }, [status]);

  return (
    !isAuthenticated
      ? (
        authView === 'login'
          ? <Login onLogin={handleLogin} onSwitchToRegister={() => setAuthView('register')} />
          : <Register onRegister={handleRegister} onSwitchToLogin={() => setAuthView('login')} />
      )
      : (
    <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/30 backdrop-blur-sm p-4">
      <div className="bg-white rounded-xl shadow-2xl w-full max-w-7xl max-h-[90vh] flex flex-col">
        {/* Header */}
        <div className="p-6 pb-4 border-b border-gray-200 flex justify-between items-start">
          <div>
            <h3 className="text-2xl font-bold text-gray-800 mb-1">Email List Details</h3>
            <p className="text-gray-600">
              List ID: {listId} • {counts.all.toLocaleString()} total emails
            </p>
          </div>
          <div className="flex items-center gap-2">
            <button
              className="text-gray-500 hover:text-gray-700 p-2 rounded-full hover:bg-gray-100 transition-colors"
              onClick={onClose}
              aria-label="Close"
            >
              <i className="fas fa-times text-xl"></i>
            </button>
          </div>
        </div>

        <StatusMessage status={status} onClose={() => setStatus(null)} />

        {/* Filters and Stats */}
        <div className="p-6 pt-4 border-b border-gray-200">
          <div className="flex flex-wrap items-center justify-between gap-4">
            <div className="flex flex-wrap gap-2">
              <button
                onClick={() => setFilter("all")}
                className={`px-4 py-2 rounded-lg font-medium text-sm transition-all ${
                  filter === "all"
                    ? "bg-blue-600 text-white shadow-md"
                    : "bg-gray-100 text-gray-700 hover:bg-gray-200"
                }`}
              >
                All ({counts.all.toLocaleString()})
              </button>
              <button
                onClick={() => setFilter("valid")}
                className={`px-4 py-2 rounded-lg font-medium text-sm transition-all ${
                  filter === "valid"
                    ? "bg-green-600 text-white shadow-md"
                    : "bg-gray-100 text-gray-700 hover:bg-gray-200"
                }`}
              >
                Valid ({counts.valid.toLocaleString()})
              </button>
              <button
                onClick={() => setFilter("invalid")}
                className={`px-4 py-2 rounded-lg font-medium text-sm transition-all ${
                  filter === "invalid"
                    ? "bg-red-600 text-white shadow-md"
                    : "bg-gray-100 text-gray-700 hover:bg-gray-200"
                }`}
              >
                Invalid ({counts.invalid.toLocaleString()})
              </button>
              <button
                onClick={() => setFilter("timeout")}
                className={`px-4 py-2 rounded-lg font-medium text-sm transition-all ${
                  filter === "timeout"
                    ? "bg-yellow-600 text-white shadow-md"
                    : "bg-gray-100 text-gray-700 hover:bg-gray-200"
                }`}
              >
                Timeout ({counts.timeout.toLocaleString()})
              </button>
            </div>

            <div className="flex flex-wrap gap-2">
              <button
                onClick={() => exportToCSV("valid")}
                className="px-4 py-2 bg-green-600 text-white rounded-lg font-medium text-sm hover:bg-green-700 flex items-center gap-2 transition-colors"
                disabled={loading}
              >
                <i className="fas fa-file-export"></i>
                Export Valid
              </button>
              <button
                onClick={() => exportToCSV("invalid")}
                className="px-4 py-2 bg-red-600 text-white rounded-lg font-medium text-sm hover:bg-red-700 flex items-center gap-2 transition-colors"
                disabled={loading}
              >
                <i className="fas fa-file-export"></i>
                Export Invalid
              </button>
              <button
                onClick={() => exportToCSV("timeout")}
                className="px-4 py-2 bg-yellow-600 text-white rounded-lg font-medium text-sm hover:bg-yellow-700 flex items-center gap-2 transition-colors"
                disabled={loading}
              >
                <i className="fas fa-file-export"></i>
                Export Timeout
              </button>
            </div>
          </div>
        </div>


        {/* Email Table */}
        <div className="flex-1 overflow-auto">
          {loading ? (
            <table className="min-w-full divide-y divide-gray-200 bg-white">
              <tbody>
                <TableSkeleton rows={10} columns={7} />
              </tbody>
            </table>
          ) : listEmails.length === 0 ? (
            <div className="flex items-center justify-center h-full">
              <div className="text-center text-gray-500 py-12">
                <i className="fas fa-inbox text-6xl mb-4 text-gray-300"></i>
                <p className="text-lg">No emails found</p>
              </div>
            </div>
          ) : (
            <table className="min-w-full divide-y divide-gray-200 bg-white">
              <thead className="bg-gray-50 sticky top-0 z-10">
                <tr>
                  <th className="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider w-20">
                    ID
                  </th>
                  <th className="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                    Email
                  </th>
                  <th className="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider w-40">
                    Account
                  </th>
                  <th className="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider w-32">
                    Domain
                  </th>
                  <th className="px-4 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider w-28">
                    Verified
                  </th>
                  <th className="px-4 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider w-28">
                    Status
                  </th>
                  <th className="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider w-64">
                    Response
                  </th>
                </tr>
              </thead>
              <tbody className="divide-y divide-gray-200">
                {listEmails.map((email) => (
                  <tr key={email.id} className="hover:bg-gray-50 transition-colors">
                    <td className="px-4 py-4 whitespace-nowrap text-sm text-gray-500 w-20">
                      {email.id}
                    </td>
                    <td className="px-4 py-4 text-sm font-medium text-gray-900">
                      {email.raw_emailid || email.email || "N/A"}
                    </td>
                    <td className="px-4 py-4 whitespace-nowrap text-sm text-gray-500 w-40">
                      {email.sp_account || "N/A"}
                    </td>
                    <td className="px-4 py-4 whitespace-nowrap text-sm text-gray-500 w-32">
                      {email.sp_domain || "N/A"}
                    </td>
                    <td className="px-4 py-4 whitespace-nowrap text-center w-28">
                      <span
                        className={`px-2 py-1 text-xs font-semibold rounded-full ${
                          email.domain_verified == 1
                            ? "bg-green-100 text-green-800"
                            : "bg-red-100 text-red-800"
                        }`}
                      >
                        {email.domain_verified == 1 ? "Verified" : "Invalid"}
                      </span>
                    </td>
                    <td className="px-4 py-4 whitespace-nowrap text-center w-28">
                      <span
                        className={`px-2 py-1 text-xs font-semibold rounded-full ${
                          isTimeout(email)
                            ? "bg-yellow-100 text-yellow-800"
                            : email.domain_status == 1
                            ? "bg-blue-100 text-blue-800"
                            : "bg-orange-100 text-red-800"
                        }`}
                      >
                        {isTimeout(email)
                          ? "Timeout"
                          : email.domain_status == 1
                          ? "Valid"
                          : "Invalid"}
                      </span>
                    </td>
                    <td
                      className="px-4 py-4 text-xs text-gray-700 w-64 max-w-md"
                      title={email.validation_response || email.smtp_response || "N/A"}
                    >
                      <div className="break-words whitespace-normal">
                        {email.validation_response || email.smtp_response || "N/A"}
                      </div>
                    </td>
                  </tr>
                ))}
              </tbody>
            </table>
          )}
        </div>

        {/* Pagination Footer */}
        <div className="px-6 py-4 border-t border-gray-200 bg-gray-50">
          <div className="flex flex-col sm:flex-row items-center justify-between gap-4">
            <div className="text-sm text-gray-600">
              Showing{" "}
              <span className="font-medium">
                {(pagination.page - 1) * pagination.rowsPerPage + 1}
              </span>{" "}
              to{" "}
              <span className="font-medium">
                {Math.min(
                  pagination.page * pagination.rowsPerPage,
                  pagination.total
                )}
              </span>{" "}
              of <span className="font-medium">{pagination.total.toLocaleString()}</span> emails
            </div>

            <div className="flex items-center gap-2">
              <button
                onClick={() =>
                  setPagination((prev) => ({ ...prev, page: 1 }))
                }
                disabled={pagination.page === 1 || loading}
                className="p-2 border border-gray-300 rounded-lg hover:bg-gray-100 disabled:opacity-50 disabled:cursor-not-allowed transition-colors"
              >
                <svg
                  className="w-5 h-5 text-gray-500"
                  fill="none"
                  stroke="currentColor"
                  viewBox="0 0 24 24"
                >
                  <path
                    strokeLinecap="round"
                    strokeLinejoin="round"
                    strokeWidth="2"
                    d="M11 19l-7-7 7-7m8 14l-7-7 7-7"
                  />
                </svg>
              </button>
              <button
                onClick={() =>
                  setPagination((prev) => ({
                    ...prev,
                    page: Math.max(1, prev.page - 1),
                  }))
                }
                disabled={pagination.page === 1 || loading}
                className="p-2 border border-gray-300 rounded-lg hover:bg-gray-100 disabled:opacity-50 disabled:cursor-not-allowed transition-colors"
              >
                <svg
                  className="w-5 h-5 text-gray-500"
                  fill="none"
                  stroke="currentColor"
                  viewBox="0 0 24 24"
                >
                  <path
                    strokeLinecap="round"
                    strokeLinejoin="round"
                    strokeWidth="2"
                    d="M15 19l-7-7 7-7"
                  />
                </svg>
              </button>
              <span className="text-sm font-medium text-gray-700 px-2">
                Page {pagination.page} of{" "}
                {Math.max(1, Math.ceil(pagination.total / pagination.rowsPerPage))}
              </span>
              <button
                onClick={() =>
                  setPagination((prev) => ({
                    ...prev,
                    page: Math.min(
                      Math.ceil(pagination.total / pagination.rowsPerPage),
                      prev.page + 1
                    ),
                  }))
                }
                disabled={
                  pagination.page >=
                    Math.ceil(pagination.total / pagination.rowsPerPage) ||
                  loading
                }
                className="p-2 border border-gray-300 rounded-lg hover:bg-gray-100 disabled:opacity-50 disabled:cursor-not-allowed transition-colors"
              >
                <svg
                  className="w-5 h-5 text-gray-500"
                  fill="none"
                  stroke="currentColor"
                  viewBox="0 0 24 24"
                >
                  <path
                    strokeLinecap="round"
                    strokeLinejoin="round"
                    strokeWidth="2"
                    d="M9 5l7 7-7 7"
                  />
                </svg>
              </button>
              <button
                onClick={() =>
                  setPagination((prev) => ({
                    ...prev,
                    page: Math.ceil(pagination.total / pagination.rowsPerPage),
                  }))
                }
                disabled={
                  pagination.page >=
                    Math.ceil(pagination.total / pagination.rowsPerPage) ||
                  loading
                }
                className="p-2 border border-gray-300 rounded-lg hover:bg-gray-100 disabled:opacity-50 disabled:cursor-not-allowed transition-colors"
              >
                <svg
                  className="w-5 h-5 text-gray-500"
                  fill="none"
                  stroke="currentColor"
                  viewBox="0 0 24 24"
                >
                  <path
                    strokeLinecap="round"
                    strokeLinejoin="round"
                    strokeWidth="2"
                    d="M13 5l7 7-7 7M5 5l7 7-7 7"
                  />
                </svg>
              </button>
              <select
                value={pagination.rowsPerPage}
                onChange={(e) =>
                  setPagination((prev) => ({
                    ...prev,
                    rowsPerPage: Number(e.target.value),
                    page: 1,
                  }))
                }
                className="border p-2 rounded-lg text-sm bg-white focus:ring-blue-500 focus:border-blue-500 transition-colors ml-2"
              >
                {[10, 25, 50, 100].map((n) => (
                  <option key={n} value={n}>
                    {n} / page
                  </option>
                ))}
              </select>
            </div>
          </div>
        </div>
      </div>
    </div>
    )
  );
};

export default EmailsList;
