import React, { useEffect, useState } from "react";

import { TableSkeleton } from "../components/SkeletonLoader";
import { API_CONFIG } from "../config";
import { authFetch } from "../utils/authFetch";

// Use centralized config for API endpoints
const API_BASE = API_CONFIG.API_MASTER_SMTPS;

const emptyServer = {
  name: "",
  host: "",
  port: 465,
  encryption: "ssl",
  is_active: true,
  received_email: "", // <-- Add this field
  accounts: [
    {
      email: "",
      password: "",
      daily_limit: 500,
      hourly_limit: 50,
      is_active: true,
    },
  ],
};

const StatusMessage = ({ status, onClose }) =>
  status && (
    <div
      className={`
        fixed top-6 left-1/2 transform -translate-x-1/2 z-[9999]
        px-6 py-3 rounded-xl shadow text-base font-semibold
        flex items-center gap-3
        transition-all duration-300
        backdrop-blur-md
        ${
          status.type === "error"
            ? "bg-red-200/60 border border-red-400 text-red-800"
            : "bg-green-200/60 border border-green-400 text-green-800"
        }
      `}
      style={{
        minWidth: 250,
        maxWidth: 400,
        boxShadow: "0 8px 32px 0 rgba(0, 0, 0, 0.23)",
        background:
          status.type === "error"
            ? "rgba(255, 0, 0, 0.29)"
            : "rgba(0, 200, 83, 0.29)",
        borderRadius: "16px",
        backdropFilter: "blur(8px)",
        WebkitBackdropFilter: "blur(8px)",
      }}
      role="alert"
    >
      <i
        className={`fas text-lg ${
          status.type === "error"
            ? "fa-exclamation-circle text-red-500"
            : "fa-check-circle text-green-500"
        }`}
      ></i>
      <span className="flex-1">{status.message}</span>
      <button
        onClick={onClose}
        className="ml-2 text-gray-500 hover:text-gray-700 focus:outline-none"
        aria-label="Close"
      >
        <i className="fas fa-times"></i>
      </button>
    </div>
  );

const Smtp = () => {
  const [servers, setServers] = useState([]);
  const [loading, setLoading] = useState(true);
  const [modalOpen, setModalOpen] = useState(false);
  const [editModalOpen, setEditModalOpen] = useState(false);
  const [form, setForm] = useState(emptyServer);
  const [editId, setEditId] = useState(null);
  const [status, setStatus] = useState(null);
  const [expandedServer, setExpandedServer] = useState(null);
  const [pagination, setPagination] = useState({
    page: 1,
    rowsPerPage: 10,
  });
  const [accountForm, setAccountForm] = useState({
    email: "",
    password: "",
    daily_limit: 500,
    hourly_limit: 50,
    is_active: true,
  });
  const [editAccountModalOpen, setEditAccountModalOpen] = useState(false);
  const [editingAccount, setEditingAccount] = useState(null);
  const [editAccountForm, setEditAccountForm] = useState({
    email: "",
    from_name: "",
    password: "",
    daily_limit: 500,
    hourly_limit: 50,
    is_active: true,
  });

  // Fetch SMTP servers
  const fetchServers = async () => {
    setLoading(true);
    try {
      const res = await authFetch(API_BASE);
      
      if (!res.ok) {
        const errorData = await res.json().catch(() => ({}));
        throw new Error(errorData.error || `HTTP error! status: ${res.status}`);
      }
      
      const data = await res.json();
      
      // Handle different response formats
      if (data.success && Array.isArray(data.data)) {
        setServers(data.data);
      } else if (Array.isArray(data.data)) {
        setServers(data.data);
      } else if (Array.isArray(data)) {
        setServers(data);
      } else {
        setServers([]);
      }
      
      // Clear any previous errors on successful load
      if (status?.type === 'error') {
        setStatus(null);
      }
    } catch (error) {
      console.error("Error fetching SMTP servers:", error);
      setServers([]);
      setStatus({ type: "error", message: error.message || "Failed to load data. Please try again." });
    }
    setLoading(false);
  };

  useEffect(() => {
    fetchServers();
  }, []);

  // Handle form input
  const handleChange = (e) => {
    const { name, value, type, checked } = e.target;
    setForm((f) => ({
      ...f,
      [name]: type === "checkbox" ? checked : value,
    }));
  };

  // Handle account form input
  const handleAccountFormChange = (e) => {
    const { name, value, type, checked } = e.target;
    setAccountForm((f) => ({
      ...f,
      [name]: type === "checkbox" ? checked : value,
    }));
  };

  // Add new SMTP server
  const handleAdd = async (e) => {
    e.preventDefault();
    // Filter out blank accounts
    const filteredAccounts = form.accounts.filter(
      (acc) => acc.email && acc.password
    );
    if (filteredAccounts.length === 0) {
      setStatus({ type: "error", message: "At least one valid account is required." });
      return;
    }
    try {
      const res = await authFetch(API_BASE, {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify({ ...form, accounts: filteredAccounts }),
      });
      const data = await res.json();
      if (data.success) {
        setStatus({
          type: "success",
          message: "SMTP server added successfully!",
        });
        setModalOpen(false);
        setForm(emptyServer);
        fetchServers();
      } else {
        setStatus({
          type: "error",
          message: data.message || "Failed to add server.",
        });
      }
    } catch {
      setStatus({ type: "error", message: "Failed to add server." });
    }
  };

  // Edit SMTP server
  const handleEdit = (server) => {
    console.log('Editing server:', server);
    setEditId(server.id);
    
    // Ensure accounts have all required fields and preserve IDs
    const accounts = (server.accounts || []).map(acc => ({
      id: acc.id,
      email: acc.email,
      password: '', // Don't prefill password
      daily_limit: acc.daily_limit,
      hourly_limit: acc.hourly_limit,
      is_active: !!acc.is_active
    }));
    
    setForm({
      ...server,
      is_active: !!server.is_active,
      accounts: accounts,
    });
    setEditModalOpen(true);
  };

  // Update SMTP server
  const handleUpdate = async (e) => {
    e.preventDefault();
    
    console.log('=== UPDATE STARTED ===');
    console.log('Edit ID:', editId);
    console.log('Form data:', form);
    console.log('Form accounts:', form.accounts);
    
    if (!editId) {
      setStatus({ type: "error", message: "No server ID for update" });
      return;
    }
    
    try {
      const requestData = {
        server: {
          name: form.name,
          host: form.host,
          port: form.port,
          encryption: form.encryption,
          is_active: form.is_active,
          received_email: form.received_email,
        },
        accounts: form.accounts,
      };
      
      console.log('Request URL:', `${API_BASE}&id=${editId}`);
      console.log('Request data:', JSON.stringify(requestData, null, 2));
      
      const res = await authFetch(`${API_BASE}&id=${editId}`, {
        method: "PUT",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify(requestData),
      });
      
      console.log('Response status:', res.status);
      const data = await res.json();
      console.log('Response data:', data);
      
      if (data.success) {
        setStatus({
          type: "success",
          message: "SMTP server updated successfully!",
        });
        setEditModalOpen(false);
        setForm(emptyServer);
        setEditId(null);
        fetchServers();
      } else {
        console.error('Update failed with message:', data.message);
        setStatus({
          type: "error",
          message: data.message || "Failed to update server.",
        });
      }
    } catch (error) {
      console.error('Update error:', error);
      setStatus({ type: "error", message: `Failed to update server: ${error.message}` });
    }
  };

  // Delete SMTP server
  const handleDelete = async (id) => {
    if (!window.confirm("Are you sure you want to delete this SMTP server?"))
      return;
    try {
      const res = await authFetch(`${API_BASE}&id=${id}`, { 
        method: "DELETE",
      });
      const data = await res.json();
      if (data.success) {
        setStatus({
          type: "success",
          message: "SMTP server deleted successfully!",
        });
        fetchServers();
      } else {
        setStatus({
          type: "error",
          message: data.message || "Failed to delete server.",
        });
      }
    } catch {
      setStatus({ type: "error", message: "Failed to delete server." });
    }
  };

  // Add new account to server
  const handleAddAccount = async (serverId) => {
    if (!accountForm.email || !accountForm.password) {
      setStatus({ type: "error", message: "Email and password are required." });
      return;
    }
    try {
      const res = await authFetch(`${API_BASE}/${serverId}/accounts`, {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify(accountForm),
      });
      const data = await res.json();
      if (data.success) {
        setStatus({
          type: "success",
          message: "Email account added successfully!",
        });
        setAccountForm({
          email: "",
          password: "",
          daily_limit: 500,
          hourly_limit: 50,
          is_active: true,
        });
        fetchServers();
      } else {
        setStatus({
          type: "error",
          message: data.message || "Failed to add email account.",
        });
      }
    } catch {
      setStatus({ type: "error", message: "Failed to add email account." });
    }
  };

  // Delete account from server
  const handleDeleteAccount = async (serverId, accountId) => {
    if (!window.confirm("Are you sure you want to delete this email account?"))
      return;
    try {
      const res = await authFetch(`${API_BASE}/${serverId}/accounts/${accountId}`, { 
        method: "DELETE",
      });
      const data = await res.json();
      if (data.success) {
        setStatus({
          type: "success",
          message: "Email account deleted successfully!",
        });
        fetchServers();
      } else {
        setStatus({
          type: "error",
          message: data.message || "Failed to delete email account.",
        });
      }
    } catch {
      setStatus({ type: "error", message: "Failed to delete email account." });
    }
  };

  // Open edit account modal
  const handleEditAccount = (serverId, account) => {
    setEditingAccount({ serverId, accountId: account.id });
    setEditAccountForm({
      email: account.email,
      from_name: account.from_name || "",
      password: "", // Don't prefill password for security
      daily_limit: account.daily_limit,
      hourly_limit: account.hourly_limit,
      is_active: account.is_active,
    });
    setEditAccountModalOpen(true);
  };

  // Update existing account
  const handleUpdateAccount = async () => {
    if (!editingAccount || !editAccountForm.email) {
      setStatus({ type: "error", message: "Email is required." });
      return;
    }

    console.log('Updating account:', editingAccount, editAccountForm);

    try {
      const res = await authFetch(
        `${API_BASE}/${editingAccount.serverId}/accounts/${editingAccount.accountId}`,
        {
          method: "PUT",
          headers: { "Content-Type": "application/json" },
          body: JSON.stringify(editAccountForm),
        }
      );
      
      console.log('Update response status:', res.status);
      const data = await res.json();
      console.log('Update response data:', data);
      if (data.success) {
        setStatus({
          type: "success",
          message: "Email account updated successfully!",
        });
        setEditAccountModalOpen(false);
        setEditingAccount(null);
        setEditAccountForm({
          email: "",
          from_name: "",
          password: "",
          daily_limit: 500,
          hourly_limit: 50,
          is_active: true,
        });
        fetchServers();
      } else {
        setStatus({
          type: "error",
          message: data.message || "Failed to update email account.",
        });
      }
    } catch (error) {
      console.error('Update account error:', error);
      setStatus({ type: "error", message: "Failed to update email account." });
    }
  };

  // Handle edit account form changes
  const handleEditAccountFormChange = (e) => {
    const { name, value, type, checked } = e.target;
    setEditAccountForm((prev) => ({
      ...prev,
      [name]: type === "checkbox" ? checked : value,
    }));
  };

  // Toggle server expansion
  const toggleExpandServer = (serverId) => {
    setExpandedServer(expandedServer === serverId ? null : serverId);
  };

  // Auto-hide success message
  useEffect(() => {
    if (status) {
      const timer = setTimeout(() => setStatus(null), 3000);
      return () => clearTimeout(timer);
    }
  }, [status]);

  // Handle account field changes in form
  const handleAccountChange = (idx, field, value) => {
    setForm((f) => {
      const accounts = [...f.accounts];
      accounts[idx] = { ...accounts[idx], [field]: value };
      return { ...f, accounts };
    });
  };

  // Add new account field to form
  const addAccount = () => {
    setForm((f) => ({
      ...f,
      accounts: [
        ...f.accounts,
        {
          email: "",
          password: "",
          daily_limit: 500,
          hourly_limit: 50,
          is_active: true,
        },
      ],
    }));
  };

  // Remove account field from form
  const removeAccount = (idx) => {
    setForm((f) => ({
      ...f,
      accounts: f.accounts.filter((_, i) => i !== idx),
    }));
  };

  // Add this helper for limits summary
  const getLimitsSummary = (accounts = []) => {
    if (!accounts.length) return "—";
    const hourly = accounts
      .map((a) => Number(a.hourly_limit) || 0)
      .reduce((a, b) => a + b, 0);
    const daily = accounts
      .map((a) => Number(a.daily_limit) || 0)
      .reduce((a, b) => a + b, 0);
    return (
      <span>
        <span className="font-medium text-indigo-700">{hourly}</span>
        <span className="text-xs text-gray-400">/hr</span>
        {" | "}
        <span className="font-medium text-indigo-700">{daily}</span>
        <span className="text-xs text-gray-400">/day</span>
      </span>
    );
  };

  // Pagination helpers
  const paginatedServers = servers.slice(
    (pagination.page - 1) * pagination.rowsPerPage,
    pagination.page * pagination.rowsPerPage
  );

  const totalPages = Math.ceil(servers.length / pagination.rowsPerPage);

  const handlePageChange = (newPage) => {
    setPagination({ ...pagination, page: newPage });
  };

  const handleRowsPerPageChange = (e) => {
    setPagination({ page: 1, rowsPerPage: parseInt(e.target.value) });
  };

  return (
    <div className="min-h-screen bg-gradient-to-br from-blue-50 via-white to-purple-50">
      <div className="container mx-auto px-2 sm:px-4 py-4 sm:py-6 lg:py-8 max-w-7xl">
        {/* Glassmorphism Status Popup */}
        <StatusMessage status={status} onClose={() => setStatus(null)} />

        {/* SMTP Records Section */}
        <div className="glass-effect rounded-xl shadow-xl border border-white/20 p-5 sm:p-6 lg:p-8 mb-5 sm:mb-6 hover:shadow-2xl transition-all duration-300">
          <div className="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4 mb-6">
            <div className="flex items-center gap-3">
              <div className="bg-gradient-to-br from-blue-500 to-indigo-600 p-3 rounded-xl shadow-lg">
                <svg className="w-6 h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M5 12h14M5 12a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v4a2 2 0 01-2 2M5 12a2 2 0 00-2 2v4a2 2 0 002 2h14a2 2 0 002-2v-4a2 2 0 00-2-2m-2-4h.01M17 16h.01" />
                </svg>
              </div>
              <h2 className="text-lg sm:text-xl font-bold text-gray-800">SMTP Records</h2>
            </div>
            <button
              onClick={() => {
                setForm(emptyServer);
                setModalOpen(true);
              }}
              className="px-6 py-3 bg-gradient-to-r from-blue-600 to-blue-700 text-white font-semibold rounded-xl shadow-xl hover:shadow-2xl hover:scale-105 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2 transition-all duration-300 flex items-center gap-2"
            >
              <svg className="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M12 4v16m8-8H4" />
              </svg>
              Add SMTP Server
            </button>
          </div>

          {/* SMTP Servers Table */}
          <div className="overflow-hidden rounded-xl border-2 border-gray-200/50 shadow-inner bg-white/50 backdrop-blur-sm">
        <div className="overflow-x-auto">
          <table className="min-w-full divide-y divide-gray-200">
            <thead className="bg-gradient-to-r from-gray-50 to-gray-100">
              <tr>
                <th className="px-6 py-3.5 text-left text-xs font-bold text-gray-700 uppercase tracking-wider">
                  Server
                </th>
                <th className="px-6 py-3 text-left text-xs font-bold text-gray-600 uppercase tracking-wider">
                  Accounts
                </th>
                <th className="px-6 py-3 text-left text-xs font-bold text-gray-600 uppercase tracking-wider">
                  Status
                </th>
                <th className="px-6 py-3 text-left text-xs font-bold text-gray-600 uppercase tracking-wider">
                  Total Limits
                </th>
                <th className="px-6 py-3 text-left text-xs font-bold text-gray-600 uppercase tracking-wider">
                  Actions
                </th>
              </tr>
            </thead>
            <tbody className="bg-white/40 divide-y divide-gray-200">
              {loading ? (
                <TableSkeleton rows={5} columns={5} />
              ) : servers.length === 0 ? (
                <tr>
                  <td colSpan={5} className="px-6 py-8 text-center text-sm text-gray-500">
                    No SMTP servers found. Add one to get started.
                  </td>
                </tr>
              ) : (
                paginatedServers.map((server, index) => (
                  <React.Fragment key={server.id}>
                    <tr className="hover:bg-indigo-50/30 transition">
                      <td className="px-6 py-4">
                        <div className="flex items-center">
                          <div className="text-xs text-gray-400 mr-3">
                            #{(pagination.page - 1) * pagination.rowsPerPage + index + 1}
                          </div>
                          <div>
                            <div className="text-base font-semibold text-gray-900">
                              {server.name}
                            </div>
                            <div className="text-xs text-gray-500">
                              {server.host}:{server.port} (
                              {server.encryption?.toUpperCase() || "None"})
                            </div>
                            {server.received_email && (
                              <div className="text-xs text-gray-500 mt-1">
                                Reply-To: <span className="font-medium text-gray-700">{server.received_email}</span>
                              </div>
                            )}
                          </div>
                        </div>
                      </td>
                      <td className="px-6 py-4">
                        <div className="text-sm text-gray-900">
                          {server.accounts?.length || 0} account(s)
                        </div>
                        <div className="text-xs text-gray-500 truncate max-w-xs">
                          {server.accounts
                            ?.map((a) => a.email)
                            .join(", ") || "No accounts"}
                        </div>
                      </td>
                      <td className="px-6 py-4">
                        <span
                          className={`px-2 inline-flex text-xs leading-5 font-semibold rounded-full ${
                            server.is_active
                              ? "bg-green-100 text-green-700"
                              : "bg-red-100 text-red-700"
                          }`}
                        >
                          {server.is_active
                            ? `Active (${server.accounts?.filter(a => a.is_active).length || 0})`
                            : "Inactive"}
                        </span>
                      </td>
                      <td className="px-6 py-4 text-base text-gray-700">
                        {getLimitsSummary(server.accounts)}
                      </td>
                      <td className="px-6 py-4 whitespace-nowrap text-sm font-medium">
                        <div className="flex items-center space-x-2">
                          <button
                            onClick={() => toggleExpandServer(server.id)}
                            className="text-indigo-600 hover:text-indigo-900"
                            title="View accounts"
                          >
                            <i className={`fas ${expandedServer === server.id ? "fa-eye-slash" : "fa-eye"}`}></i>
                          </button>
                          <button
                            onClick={() => handleEdit(server)}
                            className="text-indigo-600 hover:text-indigo-900"
                            title="Edit server"
                          >
                            <i className="fas fa-edit"></i>
                          </button>
                          <button
                            onClick={() => handleDelete(server.id)}
                            className="text-red-600 hover:text-red-900"
                            title="Delete server"
                          >
                            <i className="fas fa-trash"></i>
                          </button>
                        </div>
                      </td>
                    </tr>
                    {expandedServer === server.id && (
                      <tr className="bg-gray-50">
                        <td colSpan={5} className="px-6 py-6">
                          <div className="mb-6">
                            <div className="flex items-center space-x-2 mb-4">
                              <div className="bg-indigo-100 p-2 rounded-lg">
                                <i className="fas fa-users text-indigo-600"></i>
                              </div>
                              <h4 className="text-lg font-semibold text-gray-800">Email Accounts</h4>
                            </div>
                            <div className="space-y-3">
                              {server.accounts?.length > 0 ? (
                                <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-3">
                                  {server.accounts.map((account) => (
                                    <div
                                      key={account.id}
                                      className="bg-white border border-gray-200 rounded-lg shadow-sm hover:shadow-md transition-shadow p-3"
                                    >
                                      <div className="flex items-center space-x-2 mb-2">
                                        <div className="bg-indigo-50 p-1.5 rounded-md">
                                          <i className="fas fa-envelope text-indigo-600 text-xs"></i>
                                        </div>
                                        <div className="flex-1 min-w-0">
                                          <div className="font-medium text-gray-800 text-xs truncate">{account.email}</div>
                                          {account.is_active ? (
                                            <span className="inline-flex items-center px-1.5 py-0.5 rounded-full bg-green-100 text-green-700 text-xs font-medium">
                                              <i className="fas fa-check-circle mr-1"></i> Active
                                            </span>
                                          ) : (
                                            <span className="inline-flex items-center px-1.5 py-0.5 rounded-full bg-red-100 text-red-700 text-xs font-medium">
                                              <i className="fas fa-times-circle mr-1"></i> Inactive
                                            </span>
                                          )}
                                        </div>
                                      </div>
                                      <div className="flex items-center justify-between pt-2 border-t border-gray-100">
                                        <div className="flex items-center space-x-2 text-xs text-gray-600">
                                          <div className="flex items-center space-x-1">
                                            <i className="fas fa-clock text-orange-500 text-xs"></i>
                                            <span className="font-medium">{account.hourly_limit}</span>
                                          </div>
                                          <div className="flex items-center space-x-1">
                                            <i className="fas fa-calendar-day text-blue-500 text-xs"></i>
                                            <span className="font-medium">{account.daily_limit}</span>
                                          </div>
                                        </div>
                                        <div className="flex gap-1">
                                          <button
                                            onClick={() => handleEditAccount(server.id, account)}
                                            className="inline-flex items-center px-2 py-1 text-xs font-medium text-indigo-600 hover:bg-indigo-50 rounded transition-colors"
                                            title="Edit account"
                                          >
                                            <i className="fas fa-edit"></i>
                                          </button>
                                          <button
                                            onClick={() => handleDeleteAccount(server.id, account.id)}
                                            className="inline-flex items-center px-2 py-1 text-xs font-medium text-red-600 hover:bg-red-50 rounded transition-colors"
                                            title="Delete account"
                                          >
                                            <i className="fas fa-trash-alt"></i>
                                          </button>
                                        </div>
                                      </div>
                                    </div>
                                  ))}
                                </div>
                              ) : (
                                <div className="text-sm text-gray-500">
                                  No accounts configured
                                </div>
                              )}
                            </div>
                          </div>
                          <div className="border-t border-gray-200 pt-5 mt-5">
                            <div className="flex items-center space-x-2 mb-4">
                              <div className="bg-green-100 p-2 rounded-lg">
                                <i className="fas fa-plus text-green-600"></i>
                              </div>
                              <h4 className="text-lg font-semibold text-gray-800">Add New Account</h4>
                            </div>
                            <div className="bg-white border border-gray-200 rounded-lg p-4">
                            <div className="grid grid-cols-1 md:grid-cols-5 gap-4">
                              <div>
                                <label className="block text-sm font-medium text-gray-700 mb-2">
                                  Email
                                </label>
                                <input
                                  type="email"
                                  name="email"
                                  value={accountForm.email}
                                  onChange={handleAccountFormChange}
                                  className="block w-full px-4 py-2.5 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 text-sm"
                                  placeholder="user@example.com"
                                />
                              </div>
                              <div>
                                <label className="block text-sm font-medium text-gray-700 mb-2">
                                  Password
                                </label>
                                <input
                                  type="password"
                                  name="password"
                                  value={accountForm.password}
                                  onChange={handleAccountFormChange}
                                  className="block w-full px-4 py-2.5 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 text-sm"
                                  placeholder="Password"
                                />
                              </div>
                              <div>
                                <label className="block text-sm font-medium text-gray-700 mb-2">
                                  Hourly Limit
                                </label>
                                <input
                                  type="number"
                                  name="hourly_limit"
                                  value={accountForm.hourly_limit}
                                  onChange={handleAccountFormChange}
                                  className="block w-full px-4 py-2.5 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 text-sm"
                                />
                              </div>
                              <div>
                                <label className="block text-sm font-medium text-gray-700 mb-2">
                                  Daily Limit
                                </label>
                                <input
                                  type="number"
                                  name="daily_limit"
                                  value={accountForm.daily_limit}
                                  onChange={handleAccountFormChange}
                                  className="block w-full px-4 py-2.5 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 text-sm"
                                />
                              </div>
                              <div className="flex items-end">
                                <button
                                  onClick={() => handleAddAccount(server.id)}
                                  disabled={!accountForm.email || !accountForm.password}
                                  className={`w-full inline-flex items-center justify-center px-4 py-2.5 border border-transparent text-sm font-medium rounded-md text-white transition-colors ${
                                    !accountForm.email || !accountForm.password
                                      ? "bg-gray-300 cursor-not-allowed"
                                      : "bg-indigo-600 hover:bg-indigo-700"
                                  } focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500`}
                                >
                                  <i className="fas fa-plus mr-2"></i> Add
                                </button>
                              </div>
                            </div>
                            </div>
                          </div>
                        </td>
                      </tr>
                    )}
                  </React.Fragment>
                ))
              )}
            </tbody>
          </table>
        </div>

        {/* Pagination */}
        {servers.length > 0 && (
          <div className="flex flex-col items-center justify-center mt-6 px-1 gap-2 pb-4">
            <div className="text-sm text-gray-500 mb-2">
              Showing{" "}
              <span className="font-medium">
                {(pagination.page - 1) * pagination.rowsPerPage + 1}
              </span>{" "}
              to{" "}
              <span className="font-medium">
                {Math.min(pagination.page * pagination.rowsPerPage, servers.length)}
              </span>{" "}
              of <span className="font-medium">{servers.length}</span> servers
            </div>
            <div className="flex items-center gap-2">
              <button
                onClick={() => handlePageChange(1)}
                disabled={pagination.page === 1}
                className="p-2 border border-gray-300 rounded-lg hover:bg-gray-50 disabled:opacity-50 transition-colors font-bold text-gray-900"
              >
                <svg
                  className="w-5 h-5 text-gray-900"
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
                onClick={() => handlePageChange(pagination.page - 1)}
                disabled={pagination.page === 1}
                className="p-2 border border-gray-300 rounded-lg hover:bg-gray-50 disabled:opacity-50 transition-colors font-bold text-gray-900"
              >
                <svg
                  className="w-5 h-5 text-gray-900"
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
              <span className="text-sm font-bold text-gray-900">
                Page {pagination.page} of {Math.max(1, totalPages)}
              </span>
              <button
                onClick={() => handlePageChange(pagination.page + 1)}
                disabled={pagination.page >= totalPages}
                className="p-2 border border-gray-300 rounded-lg hover:bg-gray-50 disabled:opacity-50 transition-colors font-bold text-gray-900"
              >
                <svg
                  className="w-5 h-5 text-gray-900"
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
                onClick={() => handlePageChange(totalPages)}
                disabled={pagination.page >= totalPages}
                className="p-2 border border-gray-300 rounded-lg hover:bg-gray-50 disabled:opacity-50 transition-colors font-bold text-gray-900"
              >
                <svg
                  className="w-5 h-5 text-gray-900"
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
                onChange={handleRowsPerPageChange}
                className="border p-2 rounded-lg text-sm bg-white focus:ring-blue-500 focus:border-blue-500 transition-colors"
              >
                {[10, 25, 50, 100].map((n) => (
                  <option key={n} value={n}>
                    {n}
                  </option>
                ))}
              </select>
            </div>
          </div>
        )}
        </div>
      </div>

      {/* Add Server Modal */}
      {modalOpen && (
        <div className="fixed inset-0 z-[9999] bg-black/50 backdrop-blur-sm overflow-y-auto">
          <div className="min-h-screen px-4 text-center">
            <span className="inline-block h-screen align-middle" aria-hidden="true">&#8203;</span>
            <div className="inline-block w-full max-w-3xl my-8 text-left align-middle transition-all transform bg-white shadow-2xl rounded-xl flex flex-col" style={{ maxHeight: "90vh" }}>
              {/* Header */}
              <div className="px-6 py-4 bg-gradient-to-r from-indigo-50 to-purple-50 border-b border-gray-200 rounded-t-xl flex justify-between items-center">
                <div className="flex items-center space-x-3">
                  <div className="bg-white p-2.5 rounded-lg shadow-sm">
                    <i className="fas fa-plus-circle text-indigo-600 text-xl"></i>
                  </div>
                  <div>
                    <h3 className="text-xl font-bold text-gray-900">Add New SMTP Server</h3>
                    <p className="text-sm text-gray-600 mt-0.5">Configure server settings and accounts</p>
                  </div>
                </div>
                <button
                  onClick={() => setModalOpen(false)}
                  className="text-gray-400 hover:text-gray-600 hover:bg-white/50 rounded-lg p-2 transition-colors"
                >
                  <i className="fas fa-times text-xl"></i>
                </button>
              </div>

              {/* Scrollable Body */}
              <form className="overflow-y-auto px-6 py-6" style={{ maxHeight: "calc(90vh - 140px)" }} onSubmit={handleAdd}>
              {/* Section: Server Details */}
              <div className="mb-6">
                <h4 className="text-md font-semibold text-indigo-700 mb-3 flex items-center">
                  <i className="fas fa-server mr-2"></i> Server Details
                </h4>
                <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                  <div>
                    <label className="block text-sm font-medium text-gray-700 mb-1">
                      Name
                    </label>
                    <input
                      type="text"
                      name="name"
                      required
                      className="block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm"
                      placeholder="SMTP1"
                      value={form.name}
                      onChange={handleChange}
                    />
                  </div>
                  <div>
                    <label className="block text-sm font-medium text-gray-700 mb-1">
                      Host
                    </label>
                    <input
                      type="text"
                      name="host"
                      required
                      className="block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm"
                      placeholder="smtp.example.com"
                      value={form.host}
                      onChange={handleChange}
                    />
                  </div>
                  <div>
                    <label className="block text-sm font-medium text-gray-700 mb-1">
                      Port
                    </label>
                    <input
                      type="number"
                      name="port"
                      required
                      className="block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm"
                      placeholder="465"
                      value={form.port}
                      onChange={handleChange}
                    />
                  </div>
                  <div>
                    <label className="block text-sm font-medium text-gray-700 mb-1">
                      Encryption
                    </label>
                    <select
                      name="encryption"
                      required
                      className="block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm"
                      value={form.encryption}
                      onChange={handleChange}
                    >
                      <option value="ssl">SSL</option>
                      <option value="tls">TLS</option>
                      <option value="">None</option>
                    </select>
                  </div>
                  <div>
                    <label className="block text-sm font-medium text-gray-700 mb-1">
                      Received Email
                    </label>
                    <input
                      type="email"
                      name="received_email"
                      required
                      className="block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm"
                      placeholder="inbox@example.com"
                      value={form.received_email}
                      onChange={handleChange}
                    />
                  </div>
                  <div className="flex items-center mt-2">
                    <input
                      type="checkbox"
                      name="is_active"
                      id="add_is_active"
                      checked={form.is_active}
                      onChange={handleChange}
                      className="h-4 w-4 text-indigo-600 focus:ring-indigo-500 border-gray-300 rounded"
                    />
                    <label
                      htmlFor="add_is_active"
                      className="ml-2 block text-sm text-gray-700"
                    >
                      Active
                    </label>
                  </div>
                </div>
              </div>
              {/* Section: Email Accounts */}
              <div>
                <h4 className="text-sm font-medium text-gray-700 mb-4 flex items-center">
                  <i className="fas fa-users text-indigo-600 mr-2"></i>
                  Email Accounts
                </h4>
                {form.accounts.map((acc, idx) => (
                  <div
                    key={idx}
                    className="border border-gray-200 rounded-lg p-4 mb-3 bg-gray-50 relative"
                  >
                    <div className="grid grid-cols-1 md:grid-cols-3 gap-4">
                      <div>
                        <label className="block text-sm font-medium text-gray-700 mb-2">
                          Email
                        </label>
                        <input
                          type="email"
                          name="email"
                          required
                          className="block w-full px-4 py-2.5 border border-gray-300 rounded-md bg-white focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500"
                          placeholder="user@example.com"
                          value={acc.email}
                          onChange={e =>
                            handleAccountChange(idx, "email", e.target.value)
                          }
                        />
                      </div>
                      <div>
                        <label className="block text-sm font-medium text-gray-700 mb-2">
                          Password
                        </label>
                        <input
                          type="password"
                          name="password"
                          required
                          className="block w-full px-4 py-2.5 border border-gray-300 rounded-md bg-white focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500"
                          value={acc.password}
                          onChange={e =>
                            handleAccountChange(idx, "password", e.target.value)
                          }
                        />
                      </div>
                      <div className="flex items-end">
                        <div className="flex items-center">
                          <input
                            type="checkbox"
                            checked={acc.is_active}
                            onChange={e =>
                              handleAccountChange(
                                idx,
                                "is_active",
                                e.target.checked
                              )
                            }
                            className="h-4 w-4 text-indigo-600 border-gray-300 rounded"
                          />
                          <label className="ml-2 block text-sm text-gray-700">
                            Active
                          </label>
                        </div>
                      </div>
                    </div>
                    <div className="grid grid-cols-2 gap-4 mt-3">
                      <div>
                        <label className="block text-sm font-medium text-gray-700 mb-2">
                          Hourly Limit
                        </label>
                        <input
                          type="number"
                          name="hourly_limit"
                          required
                          className="block w-full px-4 py-2.5 border border-gray-300 rounded-md bg-white focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500"
                          value={acc.hourly_limit}
                          onChange={e =>
                            handleAccountChange(idx, "hourly_limit", e.target.value)
                          }
                        />
                      </div>
                      <div>
                        <label className="block text-sm font-medium text-gray-700 mb-2">
                          Daily Limit
                        </label>
                        <input
                          type="number"
                          name="daily_limit"
                          required
                          className="block w-full px-4 py-2.5 border border-gray-300 rounded-md bg-white focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500"
                          value={acc.daily_limit}
                          onChange={e =>
                            handleAccountChange(idx, "daily_limit", e.target.value)
                          }
                        />
                      </div>
                    </div>
                    {form.accounts.length > 1 && (
                      <button
                        type="button"
                        className="absolute top-3 right-3 text-red-500 hover:text-red-700"
                        onClick={() => removeAccount(idx)}
                        title="Remove this account"
                      >
                        <i className="fas fa-times-circle text-lg"></i>
                      </button>
                    )}
                  </div>
                ))}
                <button
                  type="button"
                  className="mt-2 text-indigo-600 hover:text-indigo-700 text-sm font-medium"
                  onClick={addAccount}
                >
                  <i className="fas fa-plus mr-2"></i>
                  Add Another Account
                </button>
              </div>
              {/* Actions */}
              <div className="flex justify-end pt-6 space-x-3">
                <button
                  type="button"
                  onClick={() => setModalOpen(false)}
                  className="inline-flex items-center px-4 py-2 border border-gray-300 text-sm font-medium rounded-md shadow-sm text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500"
                >
                  Cancel
                </button>
                <button
                  type="submit"
                  className="inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md shadow-sm text-white bg-indigo-600 hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500"
                >
                  <i className="fas fa-save mr-2"></i> Save Server
                </button>
              </div>
            </form>
            </div>
          </div>
        </div>
      )}

      {/* Edit Server Modal */}
      {editModalOpen && (
        <div className="fixed inset-0 z-[9999] bg-black/50 backdrop-blur-sm overflow-y-auto">
          <div className="min-h-screen px-4 text-center">
            <span className="inline-block h-screen align-middle" aria-hidden="true">&#8203;</span>
            <div className="inline-block w-full max-w-3xl my-8 text-left align-middle transition-all transform bg-white shadow-2xl rounded-xl flex flex-col" style={{ maxHeight: "90vh" }}>
              {/* Header */}
              <div className="px-6 py-4 bg-gradient-to-r from-indigo-50 to-purple-50 border-b border-gray-200 rounded-t-xl flex justify-between items-center">
                <div className="flex items-center space-x-3">
                  <div className="bg-white p-2.5 rounded-lg shadow-sm">
                    <i className="fas fa-server text-indigo-600 text-xl"></i>
                  </div>
                  <div>
                    <h3 className="text-xl font-bold text-gray-900">Edit SMTP Server</h3>
                    <p className="text-sm text-gray-600 mt-0.5">Update server configuration and accounts</p>
                  </div>
                </div>
                <button
                  onClick={() => setEditModalOpen(false)}
                  className="text-gray-400 hover:text-gray-600 hover:bg-white/50 rounded-lg p-2 transition-colors"
                >
                  <i className="fas fa-times text-xl"></i>
                </button>
              </div>

            {/* Scrollable Body */}
            <form className="overflow-y-auto px-6 py-6" style={{ maxHeight: "calc(90vh - 140px)" }} onSubmit={handleUpdate}>
              <input type="hidden" name="id" value={editId} />
              
              {/* Server Configuration Section */}
              <div className="mb-6">
                <h4 className="text-sm font-medium text-gray-700 mb-4 flex items-center">
                  <i className="fas fa-cog text-indigo-600 mr-2"></i>
                  Server Configuration
                </h4>
                <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                  <div>
                    <label className="block text-sm font-medium text-gray-700 mb-2">
                      Name
                    </label>
                    <input
                      type="text"
                      name="name"
                      required
                      className="block w-full px-4 py-2.5 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500"
                      value={form.name}
                      onChange={handleChange}
                    />
                  </div>
                  <div>
                    <label className="block text-sm font-medium text-gray-700 mb-2">
                      Host
                    </label>
                    <input
                      type="text"
                      name="host"
                      required
                      className="block w-full px-4 py-2.5 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500"
                      value={form.host}
                      onChange={handleChange}
                    />
                  </div>
                  <div>
                    <label className="block text-sm font-medium text-gray-700 mb-2">
                      Port
                    </label>
                    <input
                      type="number"
                      name="port"
                      required
                      className="block w-full px-4 py-2.5 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500"
                      value={form.port}
                      onChange={handleChange}
                    />
                  </div>
                  <div>
                    <label className="block text-sm font-medium text-gray-700 mb-2">
                      Encryption
                    </label>
                    <select
                      name="encryption"
                      required
                      className="block w-full px-4 py-2.5 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500"
                      value={form.encryption}
                      onChange={handleChange}
                    >
                      <option value="ssl">SSL</option>
                      <option value="tls">TLS</option>
                      <option value="">None</option>
                    </select>
                  </div>
                  <div className="md:col-span-2">
                    <label className="block text-sm font-medium text-gray-700 mb-2">
                      Received Email
                    </label>
                    <input
                      type="email"
                      name="received_email"
                      required
                      className="block w-full px-4 py-2.5 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500"
                      placeholder="inbox@example.com"
                      value={form.received_email}
                      onChange={handleChange}
                    />
                  </div>
                </div>
              </div>

              {/* Email Accounts Section */}
              <div className="mb-6">
                <h4 className="text-sm font-medium text-gray-700 mb-4 flex items-center">
                  <i className="fas fa-envelope text-indigo-600 mr-2"></i>
                  Email Accounts
                </h4>
                {form.accounts && form.accounts.map((acc, idx) => (
                  <div key={acc.id || idx} className="border border-gray-200 rounded-lg p-4 mb-3 bg-gray-50">
                    <div className="grid grid-cols-1 md:grid-cols-2 gap-4 mb-3">
                      <div>
                        <label className="block text-sm font-medium text-gray-700 mb-2">
                          Email
                        </label>
                        <input
                          type="email"
                          name="email"
                          required
                          className="block w-full px-4 py-2.5 border border-gray-300 rounded-md bg-white focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500"
                          value={acc.email}
                          onChange={(e) => handleAccountChange(idx, "email", e.target.value)}
                        />
                      </div>
                      <div>
                        <label className="block text-sm font-medium text-gray-700 mb-2">
                          Password
                          <span className="ml-2 text-xs text-gray-500">(leave blank to keep)</span>
                        </label>
                        <input
                          type="password"
                          name="password"
                          className="block w-full px-4 py-2.5 border border-gray-300 rounded-md bg-white focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500"
                          placeholder="Leave blank to keep current"
                          value={acc.password || ''}
                          onChange={(e) => handleAccountChange(idx, "password", e.target.value)}
                        />
                      </div>
                    </div>
                    <div className="grid grid-cols-2 gap-4 mb-3">
                      <div>
                        <label className="block text-sm font-medium text-gray-700 mb-2">
                          Hourly Limit
                        </label>
                        <input
                          type="number"
                          name="hourly_limit"
                          required
                          className="block w-full px-4 py-2.5 border border-gray-300 rounded-md bg-white focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500"
                          value={acc.hourly_limit}
                          onChange={(e) => handleAccountChange(idx, "hourly_limit", e.target.value)}
                        />
                      </div>
                      <div>
                        <label className="block text-sm font-medium text-gray-700 mb-2">
                          Daily Limit
                        </label>
                        <input
                          type="number"
                          name="daily_limit"
                          required
                          className="block w-full px-4 py-2.5 border border-gray-300 rounded-md bg-white focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500"
                          value={acc.daily_limit}
                          onChange={(e) => handleAccountChange(idx, "daily_limit", e.target.value)}
                        />
                      </div>
                    </div>
                    <div className="flex items-center justify-between pt-2 border-t border-gray-200">
                      <div className="flex items-center">
                        <input
                          type="checkbox"
                          checked={acc.is_active}
                          onChange={(e) => handleAccountChange(idx, "is_active", e.target.checked)}
                          className="h-4 w-4 text-indigo-600 border-gray-300 rounded focus:ring-indigo-500"
                        />
                        <label className="ml-2 text-sm font-medium text-gray-700">
                          Active
                        </label>
                      </div>
                      <button
                        type="button"
                        className="inline-flex items-center text-sm text-red-600 hover:text-red-700 font-medium"
                        onClick={() => removeAccount(idx)}
                      >
                        <i className="fas fa-trash-alt mr-1"></i>
                        Remove Account
                      </button>
                    </div>
                  </div>
                ))}
              </div>

              {/* Server Status */}
              <div className="bg-gray-50 rounded-lg p-4 border border-gray-200 mb-6">
                <div className="flex items-center justify-between">
                  <div className="flex items-center space-x-3">
                    <i className={`fas fa-${form.is_active ? 'check-circle' : 'times-circle'} text-lg ${form.is_active ? 'text-green-600' : 'text-gray-400'}`}></i>
                    <div>
                      <label htmlFor="edit_is_active" className="text-sm font-medium text-gray-700 cursor-pointer">
                        Server Status
                      </label>
                      <p className="text-xs text-gray-500">
                        {form.is_active ? 'Server is active and can send emails' : 'Server is disabled'}
                      </p>
                    </div>
                  </div>
                  <label className="relative inline-flex items-center cursor-pointer">
                    <input
                      type="checkbox"
                      name="is_active"
                      id="edit_is_active"
                      checked={form.is_active}
                      onChange={handleChange}
                      className="sr-only peer"
                    />
                    <div className="w-11 h-6 bg-gray-300 peer-focus:outline-none peer-focus:ring-4 peer-focus:ring-indigo-300 rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-indigo-600"></div>
                  </label>
                </div>
              </div>
            </form>

            {/* Footer */}
            <div className="flex justify-end px-6 py-4 space-x-3 bg-gray-50 rounded-b-lg border-t border-gray-200">
              <button
                type="button"
                onClick={() => setEditModalOpen(false)}
                className="inline-flex items-center px-4 py-2 border border-gray-300 text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500"
              >
                <i className="fas fa-times mr-2"></i>
                Cancel
              </button>
              <button
                type="submit"
                onClick={(e) => {
                  e.preventDefault();
                  document.querySelector('form').dispatchEvent(new Event('submit', { cancelable: true, bubbles: true }));
                }}
                className="inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md text-white bg-indigo-600 hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500"
              >
                <i className="fas fa-save mr-2"></i>
                Update Server
              </button>
            </div>
          </div>
        </div>
        </div>
      )}

      {/* Edit Account Modal */}
      {editAccountModalOpen && (
        <div className="fixed inset-0 z-[9999] bg-black/50 backdrop-blur-sm overflow-y-auto">
          <div className="min-h-screen px-4 text-center">
            <span className="inline-block h-screen align-middle" aria-hidden="true">&#8203;</span>
            <div className="inline-block w-full max-w-2xl my-8 text-left align-middle transition-all transform bg-white shadow-2xl rounded-xl">
              {/* Modal Header */}
              <div className="px-6 py-4 bg-gradient-to-r from-indigo-50 to-purple-50 border-b border-gray-200 rounded-t-xl">
                <div className="flex justify-between items-center">
                  <div className="flex items-center space-x-3">
                    <div className="bg-white p-2.5 rounded-lg shadow-sm">
                      <i className="fas fa-edit text-indigo-600 text-xl"></i>
                    </div>
                    <div>
                      <h3 className="text-xl font-bold text-gray-900">Edit Email Account</h3>
                      <p className="text-sm text-gray-600 mt-0.5">Update account settings and limits</p>
                    </div>
                  </div>
                  <button
                    onClick={() => setEditAccountModalOpen(false)}
                    className="text-gray-400 hover:text-gray-600 hover:bg-white/50 rounded-lg p-2 transition-colors"
                  >
                    <i className="fas fa-times text-xl"></i>
                  </button>
                </div>
              </div>

              {/* Modal Body */}
              <div className="px-6 py-6">
                <div className="space-y-5">
                  <div>
                    <label className="block text-sm font-semibold text-gray-700 mb-2">
                      Email Address
                    </label>
                    <input
                      type="email"
                      name="email"
                      value={editAccountForm.email}
                      onChange={handleEditAccountFormChange}
                      className="block w-full px-4 py-3 border-2 border-gray-200 rounded-xl bg-white/80 backdrop-blur-sm focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 transition-all shadow-sm hover:shadow-md text-sm"
                      placeholder="user@example.com"
                      required
                    />
                  </div>
                  <div>
                    <label className="block text-sm font-semibold text-gray-700 mb-2">
                      Password (leave blank to keep current)
                    </label>
                    <input
                      type="password"
                      name="password"
                      value={editAccountForm.password}
                      onChange={handleEditAccountFormChange}
                      className="block w-full px-4 py-3 border-2 border-gray-200 rounded-xl bg-white/80 backdrop-blur-sm focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 transition-all shadow-sm hover:shadow-md text-sm"
                      placeholder="New password (optional)"
                    />
                  </div>
                  <div className="grid grid-cols-1 md:grid-cols-2 gap-5">
                    <div>
                      <label className="block text-sm font-semibold text-gray-700 mb-2">
                        Hourly Limit
                      </label>
                      <input
                        type="number"
                        name="hourly_limit"
                        value={editAccountForm.hourly_limit}
                        onChange={handleEditAccountFormChange}
                        className="block w-full px-4 py-3 border-2 border-gray-200 rounded-xl bg-white/80 backdrop-blur-sm focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 transition-all shadow-sm hover:shadow-md text-sm"
                        min="0"
                        max="1000000"
                      />
                    </div>
                    <div>
                      <label className="block text-sm font-semibold text-gray-700 mb-2">
                        Daily Limit
                      </label>
                      <input
                        type="number"
                        name="daily_limit"
                        value={editAccountForm.daily_limit}
                        onChange={handleEditAccountFormChange}
                        className="block w-full px-4 py-3 border-2 border-gray-200 rounded-xl bg-white/80 backdrop-blur-sm focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 transition-all shadow-sm hover:shadow-md text-sm"
                        min="0"
                        max="1000000"
                      />
                    </div>
                  </div>
                  <div className="flex items-center space-x-3 pt-2">
                    <input
                      type="checkbox"
                      name="is_active"
                      id="edit_account_is_active"
                      checked={editAccountForm.is_active}
                      onChange={handleEditAccountFormChange}
                      className="h-5 w-5 text-indigo-600 focus:ring-indigo-500 border-gray-300 rounded transition-all cursor-pointer"
                    />
                    <label
                      htmlFor="edit_account_is_active"
                      className="block text-sm font-medium text-gray-700 cursor-pointer"
                    >
                      Active
                    </label>
                  </div>
                </div>
              </div>

              {/* Modal Footer */}
              <div className="flex justify-end gap-3 px-6 py-4 bg-gray-50 rounded-b-xl border-t border-gray-200">
                <button
                  type="button"
                  onClick={() => setEditAccountModalOpen(false)}
                  className="px-6 py-2.5 border-2 border-gray-300 text-sm font-semibold rounded-xl text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-gray-400 transition-all shadow-sm hover:shadow-md"
                >
                  <i className="fas fa-times mr-2"></i>
                  Cancel
                </button>
                <button
                  type="button"
                  onClick={handleUpdateAccount}
                  className="px-6 py-2.5 text-sm font-semibold rounded-xl text-white bg-gradient-to-r from-indigo-600 to-purple-600 hover:from-indigo-700 hover:to-purple-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 transition-all shadow-lg hover:shadow-xl hover:scale-105"
                >
                  <i className="fas fa-save mr-2"></i>
                  Update Account
                </button>
              </div>
            </div>
          </div>
        </div>
      )}
      </div>
    </div>
  );
};

export default Smtp;