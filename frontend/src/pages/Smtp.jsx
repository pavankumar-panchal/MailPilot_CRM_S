import React, { useEffect, useState } from "react";

// Set your API base here (change to your production URL as needed)
const API_BASE = "http://localhost/Verify_email/backend/routes/api.php/api/master/smtps";
// For production, use:
// const API_BASE = "https://payrollsoft.in/Verify_email/backend/routes/api.php/api/master/smtps";

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
      hourly_limit: 100,
      is_active: true,
    },
  ],
};

const StatusMessage = ({ status, onClose }) =>
  status && (
    <div
      className={`
        fixed top-6 left-1/2 transform -translate-x-1/2 z-50
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
  const [accountForm, setAccountForm] = useState({
    email: "",
    password: "",
    daily_limit: 500,
    hourly_limit: 100,
    is_active: true,
  });

  // Fetch SMTP servers
  const fetchServers = async () => {
    setLoading(true);
    try {
      const res = await fetch(API_BASE);
      const data = await res.json();
      if (Array.isArray(data.data)) {
        setServers(data.data);
      } else if (Array.isArray(data)) {
        setServers(data);
      } else {
        setServers([]);
      }
    } catch (err) {
      setStatus({ type: "error", message: "Failed to load SMTP servers." });
      setServers([]);
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
      const res = await fetch(API_BASE, {
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
    } catch (err) {
      setStatus({ type: "error", message: "Failed to add server." });
    }
  };

  // Edit SMTP server
  const handleEdit = (server) => {
    setEditId(server.id);
    setForm({
      ...server,
      is_active: !!server.is_active,
      accounts: server.accounts || [],
    });
    setEditModalOpen(true);
  };

  // Update SMTP server
  const handleUpdate = async (e) => {
    e.preventDefault();
    try {
      const res = await fetch(`${API_BASE}?id=${editId}`, {
        method: "PUT",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify({
          server: {
            name: form.name,
            host: form.host,
            port: form.port,
            encryption: form.encryption,
            is_active: form.is_active,
            received_email: form.received_email, // <-- Add this line!
          },
          accounts: form.accounts,
        }),
      });
      const data = await res.json();
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
        setStatus({
          type: "error",
          message: data.message || "Failed to update server.",
        });
      }
    } catch (err) {
      setStatus({ type: "error", message: "Failed to update server." });
    }
  };

  // Delete SMTP server
  const handleDelete = async (id) => {
    if (!window.confirm("Are you sure you want to delete this SMTP server?"))
      return;
    try {
      const res = await fetch(`${API_BASE}?id=${id}`, { method: "DELETE" });
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
    } catch (err) {
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
      const res = await fetch(`${API_BASE}/${serverId}/accounts`, {
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
          hourly_limit: 100,
          is_active: true,
        });
        fetchServers();
      } else {
        setStatus({
          type: "error",
          message: data.message || "Failed to add email account.",
        });
      }
    } catch (err) {
      setStatus({ type: "error", message: "Failed to add email account." });
    }
  };

  // Delete account from server
  const handleDeleteAccount = async (serverId, accountId) => {
    if (!window.confirm("Are you sure you want to delete this email account?"))
      return;
    try {
      const res = await fetch(`${API_BASE}/${serverId}/accounts/${accountId}`, { method: "DELETE" });
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
    } catch (err) {
      setStatus({ type: "error", message: "Failed to delete email account." });
    }
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
          hourly_limit: 100,
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

  return (
    <main className="max-w-7xl mx-auto px-4 mt-14 sm:px-6 py-6">
      {/* Glassmorphism Status Popup */}
      <StatusMessage status={status} onClose={() => setStatus(null)} />

      <div className="flex justify-between items-center mb-6">
        <h1 className="text-2xl font-bold text-gray-900 flex items-center">
          <i className="fas fa-server mr-3 text-indigo-600"></i>
          SMTP Records
        </h1>
        <button
          onClick={() => {
            setForm(emptyServer);
            setModalOpen(true);
          }}
          className="inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md shadow-sm text-white bg-indigo-600 hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500"
        >
          <i className="fas fa-plus mr-2"></i> Add SMTP Server
        </button>
      </div>

      {/* SMTP Servers Table */}
      <div className="card overflow-hidden bg-white/80 backdrop-blur-md rounded-xl shadow-lg">
        <div className="overflow-x-auto">
          <table className="min-w-full divide-y divide-gray-200">
            <thead className="bg-gray-50/80">
              <tr>
                <th className="px-6 py-3 text-left text-xs font-bold text-gray-600 uppercase tracking-wider">
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
            <tbody className="bg-white/60 divide-y divide-gray-200">
              {loading ? (
                <tr>
                  <td colSpan={5} className="px-6 py-8 text-center text-sm text-gray-500">
                    <div className="flex justify-center">
                      <div className="animate-spin rounded-full h-8 w-8 border-b-2 border-indigo-600"></div>
                    </div>
                  </td>
                </tr>
              ) : servers.length === 0 ? (
                <tr>
                  <td colSpan={5} className="px-6 py-8 text-center text-sm text-gray-500">
                    No SMTP servers found. Add one to get started.
                  </td>
                </tr>
              ) : (
                servers.map((server) => (
                  <React.Fragment key={server.id}>
                    <tr className="hover:bg-indigo-50/30 transition">
                      <td className="px-6 py-4">
                        <div className="flex items-center">
                          <div>
                            <div className="text-base font-semibold text-gray-900">
                              {server.name}
                            </div>
                            <div className="text-xs text-gray-500">
                              {server.host}:{server.port} (
                              {server.encryption?.toUpperCase() || "None"})
                            </div>
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
                      <tr className="bg-indigo-50/40">
                        <td colSpan={5} className="px-6 py-6">
                          <div className="mb-4">
                            <h4 className="text-base font-semibold text-indigo-700 mb-2 flex items-center">
                              <i className="fas fa-users mr-2"></i> Email Accounts
                            </h4>
                            <div className="space-y-2">
                              {server.accounts?.length > 0 ? (
                                <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                                  {server.accounts.map((account) => (
                                    <div
                                      key={account.id}
                                      className="flex flex-col md:flex-row items-start md:items-center justify-between bg-white border rounded-lg shadow-sm p-4"
                                    >
                                      <div>
                                        <div className="font-semibold text-gray-800 flex items-center">
                                          <i className="fas fa-envelope mr-2 text-indigo-500"></i>
                                          {account.email}
                                          {account.is_active ? (
                                            <span className="ml-3 px-2 py-0.5 rounded bg-green-100 text-green-700 text-xs font-medium">
                                              Active
                                            </span>
                                          ) : (
                                            <span className="ml-3 px-2 py-0.5 rounded bg-red-100 text-red-700 text-xs font-medium">
                                              Inactive
                                            </span>
                                          )}
                                        </div>
                                        <div className="text-xs text-gray-500 mt-1">
                                          Hourly:{" "}
                                          <span className="font-medium">
                                            {account.hourly_limit}
                                          </span>{" "}
                                          &nbsp;|&nbsp; Daily:{" "}
                                          <span className="font-medium">
                                            {account.daily_limit}
                                          </span>
                                        </div>
                                      </div>
                                      <button
                                        onClick={() =>
                                          handleDeleteAccount(server.id, account.id)
                                        }
                                        className="mt-2 md:mt-0 text-red-500 hover:text-red-700 text-sm flex items-center"
                                        title="Delete account"
                                      >
                                        <i className="fas fa-trash-alt mr-1"></i> Delete
                                      </button>
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
                          <div className="border-t pt-4 mt-4">
                            <h4 className="text-base font-semibold text-indigo-700 mb-2 flex items-center">
                              <i className="fas fa-plus mr-2"></i> Add New Account
                            </h4>
                            <div className="grid grid-cols-1 md:grid-cols-5 gap-4">
                              <div>
                                <label className="block text-xs font-medium text-gray-700 mb-1">
                                  Email
                                </label>
                                <input
                                  type="email"
                                  name="email"
                                  value={accountForm.email}
                                  onChange={handleAccountFormChange}
                                  className="block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm"
                                  placeholder="user@example.com"
                                />
                              </div>
                              <div>
                                <label className="block text-xs font-medium text-gray-700 mb-1">
                                  Password
                                </label>
                                <input
                                  type="password"
                                  name="password"
                                  value={accountForm.password}
                                  onChange={handleAccountFormChange}
                                  className="block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm"
                                  placeholder="Password"
                                />
                              </div>
                              <div>
                                <label className="block text-xs font-medium text-gray-700 mb-1">
                                  Hourly Limit
                                </label>
                                <input
                                  type="number"
                                  name="hourly_limit"
                                  value={accountForm.hourly_limit}
                                  onChange={handleAccountFormChange}
                                  className="block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm"
                                />
                              </div>
                              <div>
                                <label className="block text-xs font-medium text-gray-700 mb-1">
                                  Daily Limit
                                </label>
                                <input
                                  type="number"
                                  name="daily_limit"
                                  value={accountForm.daily_limit}
                                  onChange={handleAccountFormChange}
                                  className="block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm"
                                />
                              </div>
                              <div className="flex items-end">
                                <button
                                  onClick={() => handleAddAccount(server.id)}
                                  disabled={!accountForm.email || !accountForm.password}
                                  className={`inline-flex items-center px-3 py-2 border border-transparent text-sm leading-4 font-medium rounded-md shadow-sm text-white ${
                                    !accountForm.email || !accountForm.password
                                      ? "bg-gray-300 cursor-not-allowed"
                                      : "bg-indigo-600 hover:bg-indigo-700"
                                  } focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500`}
                                >
                                  <i className="fas fa-plus mr-1"></i> Add
                                </button>
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
      </div>

      {/* Add Server Modal */}
      {modalOpen && (
        <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/30 backdrop-blur-md">
          <div className="relative w-full max-w-2xl mx-auto bg-white rounded-lg shadow-lg flex flex-col"
               style={{ maxHeight: "90vh" }}>
            {/* Sticky header */}
            <div className="sticky top-0 z-10 bg-white border-b flex justify-between items-center px-5 py-3 rounded-t-lg">
              <h3 className="text-lg font-medium text-gray-900 flex items-center">
                <i className="fas fa-plus-circle mr-2 text-indigo-600"></i>
                Add New SMTP Server
              </h3>
              <button
                onClick={() => setModalOpen(false)}
                className="text-gray-400 hover:text-gray-500"
              >
                <i className="fas fa-times"></i>
              </button>
            </div>
            {/* Scrollable content */}
            <form
              className="overflow-y-auto px-5 py-4"
              style={{ maxHeight: "75vh" }}
              onSubmit={handleAdd}
            >
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
                <h4 className="text-md font-semibold text-indigo-700 mb-3 flex items-center">
                  <i className="fas fa-users mr-2"></i> Email Accounts
                </h4>
                {form.accounts.map((acc, idx) => (
                  <div
                    key={idx}
                    className="border rounded p-3 mb-2 bg-gray-50 relative"
                  >
                    <div className="grid grid-cols-1 md:grid-cols-3 gap-4">
                      <div>
                        <label className="block text-sm font-medium text-gray-700 mb-1">
                          Email
                        </label>
                        <input
                          type="email"
                          name="email"
                          required
                          className="block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm"
                          placeholder="user@example.com"
                          value={acc.email}
                          onChange={e =>
                            handleAccountChange(idx, "email", e.target.value)
                          }
                        />
                      </div>
                      <div>
                        <label className="block text-sm font-medium text-gray-700 mb-1">
                          Password
                        </label>
                        <input
                          type="password"
                          name="password"
                          required
                          className="block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm"
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
                    <div className="grid grid-cols-2 gap-4 mt-2">
                      <div>
                        <label className="block text-sm font-medium text-gray-700 mb-1">
                          Hourly Limit
                        </label>
                        <input
                          type="number"
                          name="hourly_limit"
                          required
                          className="block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm"
                          value={acc.hourly_limit}
                          onChange={e =>
                            handleAccountChange(idx, "hourly_limit", e.target.value)
                          }
                        />
                      </div>
                      <div>
                        <label className="block text-sm font-medium text-gray-700 mb-1">
                          Daily Limit
                        </label>
                        <input
                          type="number"
                          name="daily_limit"
                          required
                          className="block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm"
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
                        className="absolute top-2 right-2 text-red-500 hover:text-red-700 text-xs"
                        onClick={() => removeAccount(idx)}
                        title="Remove this account"
                      >
                        <i className="fas fa-times-circle"></i>
                      </button>
                    )}
                  </div>
                ))}
                <button
                  type="button"
                  className="mt-2 text-indigo-600 text-xs"
                  onClick={addAccount}
                >
                  <i className="fas fa-plus mr-1"></i> Add Another Account
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
      )}

      {/* Edit Server Modal */}
      {editModalOpen && (
        <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/30 backdrop-blur-md">
          <div className="relative w-full max-w-xl mx-auto bg-white rounded-lg shadow-lg flex flex-col"
               style={{ maxHeight: "90vh" }}>
            {/* Sticky header */}
            <div className="sticky top-0 z-10 bg-white border-b flex justify-between items-center px-5 py-3 rounded-t-lg">
              <h3 className="text-lg font-medium text-gray-900 flex items-center">
                <i className="fas fa-edit mr-2 text-indigo-600"></i>
                Edit SMTP Server
              </h3>
              <button
                onClick={() => setEditModalOpen(false)}
                className="text-gray-400 hover:text-gray-500"
              >
                <i className="fas fa-times"></i>
              </button>
            </div>
            {/* Scrollable content */}
            <form
              className="overflow-y-auto px-5 py-4"
              style={{ maxHeight: "75vh" }}
              onSubmit={handleUpdate}
            >
              <input type="hidden" name="id" value={editId} />
              {/* Name + Host + Received Email */}
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
              </div>
              {/* Accounts Section */}
              <div className="mt-6">
                <h4 className="text-md font-semibold text-gray-800 mb-3">
                  Email Accounts
                </h4>
                {form.accounts.map((acc, idx) => (
                  <div
                    key={idx}
                    className="border rounded p-3 mb-2 bg-gray-50"
                  >
                    <div className="grid grid-cols-1 md:grid-cols-3 gap-4">
                      <div>
                        <label className="block text-sm font-medium text-gray-700 mb-1">
                          Email
                        </label>
                        <input
                          type="email"
                          name="email"
                          required
                          className="block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm"
                          value={acc.email}
                          onChange={(e) =>
                            handleAccountChange(idx, "email", e.target.value)
                          }
                        />
                      </div>
                      <div>
                        <label className="block text-sm font-medium text-gray-700 mb-1">
                          Password
                        </label>
                        <input
                          type="password"
                          name="password"
                          required
                          className="block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm"
                          value={acc.password}
                          onChange={(e) =>
                            handleAccountChange(idx, "password", e.target.value)
                          }
                        />
                      </div>
                      <div className="flex items-end">
                        <div className="flex items-center">
                          <input
                            type="checkbox"
                            checked={acc.is_active}
                            onChange={(e) =>
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
                    <div className="grid grid-cols-2 gap-4 mt-2">
                      <div>
                        <label className="block text-sm font-medium text-gray-700 mb-1">
                          Hourly Limit
                        </label>
                        <input
                          type="number"
                          name="hourly_limit"
                          required
                          className="block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm"
                          value={acc.hourly_limit}
                          onChange={(e) =>
                            handleAccountChange(
                              idx,
                              "hourly_limit",
                              e.target.value
                            )
                          }
                        />
                      </div>
                      <div>
                        <label className="block text-sm font-medium text-gray-700 mb-1">
                          Daily Limit
                        </label>
                        <input
                          type="number"
                          name="daily_limit"
                          required
                          className="block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm"
                          value={acc.daily_limit}
                          onChange={(e) =>
                            handleAccountChange(
                              idx,
                              "daily_limit",
                              e.target.value
                            )
                          }
                        />
                      </div>
                    </div>
                    {form.accounts.length > 1 && (
                      <button
                        type="button"
                        className="mt-2 text-red-600 text-xs"
                        onClick={() => removeAccount(idx)}
                      >
                        Remove Account
                      </button>
                    )}
                  </div>
                ))}
                {/* <button
                  type="button"
                  className="mt-2 text-indigo-600 text-xs"
                  onClick={addAccount}
                >
                  + Add Another Account
                </button> */}
              </div>

              <div className="flex items-center">
                <input
                  type="checkbox"
                  name="is_active"
                  id="edit_is_active"
                  checked={form.is_active}
                  onChange={handleChange}
                  className="h-4 w-4 text-indigo-600 focus:ring-indigo-500 border-gray-300 rounded"
                />
                <label
                  htmlFor="edit_is_active"
                  className="ml-2 block text-sm text-gray-700"
                >
                  Active
                </label>
              </div>
              <div className="flex justify-end pt-4 space-x-3">
                <button
                  type="button"
                  onClick={() => setEditModalOpen(false)}
                  className="inline-flex items-center px-4 py-2 border border-gray-300 text-sm font-medium rounded-md shadow-sm text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500"
                >
                  Cancel
                </button>
                <button
                  type="submit"
                  className="inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md shadow-sm text-white bg-indigo-600 hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500"
                >
                  <i className="fas fa-save mr-2"></i> Update Server
                </button>
              </div>
            </form>
          </div>
        </div>
      )}
    </main>
  );
};

export default Smtp;