import React, { useEffect, useState } from "react";

import { TableSkeleton } from "../components/SkeletonLoader";
import { API_CONFIG } from "../config";
import { authFetch } from "../utils/authFetch";

// Use centralized config for API endpoints
const API_BASE = API_CONFIG.API_WORKERS;

const emptyWorker = {
  workername: "",
  ip: "",
  is_active: 1,
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
        ${status.type === "error"
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
        className={`fas text-lg ${status.type === "error"
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

function isValidIP(ip) {
  // IPv4
  const ipv4 = /^(25[0-5]|2[0-4]\d|1\d{2}|[1-9]?\d)(\.(25[0-5]|2[0-4]\d|1\d{2}|[1-9]?\d)){3}$/;
  // IPv6 (simple, not exhaustive)
  const ipv6 = /^([0-9a-fA-F]{1,4}:){7}[0-9a-fA-F]{1,4}$/;
  return ipv4.test(ip) || ipv6.test(ip);
}

const Workers = () => {
  const [workers, setWorkers] = useState([]);
  const [loading, setLoading] = useState(true);
  const [modalOpen, setModalOpen] = useState(false);
  const [editModalOpen, setEditModalOpen] = useState(false);
  const [form, setForm] = useState(emptyWorker);
  const [editId, setEditId] = useState(null);
  const [status, setStatus] = useState(null);
  const [pagination, setPagination] = useState({
    page: 1,
    rowsPerPage: 10,
  });

  // Toggle worker active status
  const toggleWorkerStatus = async (worker) => {
    const newStatus = worker.is_active === 1 ? 0 : 1;
    const statusText = newStatus === 1 ? 'activate' : 'deactivate';
    
    if (!window.confirm(`Are you sure you want to ${statusText} worker "${worker.workername}"?`)) {
      return;
    }

    try {
      const res = await authFetch(API_BASE, {
        method: "PUT",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify({ 
          id: worker.id, 
          is_active: newStatus 
        }),
      });
      const data = await res.json();
      if (res.ok || data.success) {
        setStatus({
          type: "success",
          message: data.message || `Worker ${statusText}d successfully!`,
        });
        fetchWorkers();
      } else {
        setStatus({
          type: "error",
          message: data.error || data.message || `Failed to ${statusText} worker.`,
        });
      }
    } catch {
      setStatus({ type: "error", message: `Failed to ${statusText} worker.` });
    }
  };

  // Fetch workers
  const fetchWorkers = async () => {
    setLoading(true);
    try {
      const res = await authFetch(API_BASE);
      const data = await res.json();
      if (Array.isArray(data)) {
        setWorkers(data);
      } else if (Array.isArray(data.data)) {
        setWorkers(data.data);
      } else {
        setWorkers([]);
      }
    } catch {
      setStatus({ type: "error", message: "Failed to load workers." });
      setWorkers([]);
    }
    setLoading(false);
  };

  useEffect(() => {
    fetchWorkers();
  }, []);

  // Handle form input
  const handleChange = (e) => {
    const { name, value } = e.target;
    setForm((f) => ({
      ...f,
      [name]: value,
    }));
  };

  // Add new worker
  const handleAdd = async (e) => {
    e.preventDefault();
    if (!isValidIP(form.ip)) {
      setStatus({ type: "error", message: "Invalid IP address format." });
      return;
    }
    try {
      const res = await authFetch(API_BASE, {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify(form),
      });
      const data = await res.json();
      if (res.ok || data.success) {
        setStatus({
          type: "success",
          message: data.message || "Worker added successfully!",
        });
        setModalOpen(false);
        setForm(emptyWorker);
        fetchWorkers();
      } else {
        setStatus({
          type: "error",
          message: data.error || data.message || "Failed to add worker.",
        });
      }
    } catch {
      setStatus({ type: "error", message: "Failed to add worker." });
    }
  };

  // Edit worker
  const handleEdit = (worker) => {
    setEditId(worker.id);
    setForm({
      workername: worker.workername,
      ip: worker.ip,
      is_active: worker.is_active,
    });
    setEditModalOpen(true);
  };

  // Update worker
  const handleUpdate = async (e) => {
    e.preventDefault();
    if (!isValidIP(form.ip)) {
      setStatus({ type: "error", message: "Invalid IP address format." });
      return;
    }
    try {
      const res = await authFetch(API_BASE, {
        method: "PUT",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify({ id: editId, ...form }),
      });
      const data = await res.json();
      if (res.ok || data.success) {
        setStatus({
          type: "success",
          message: data.message || "Worker updated successfully!",
        });
        setEditModalOpen(false);
        setForm(emptyWorker);
        setEditId(null);
        fetchWorkers();
      } else {
        setStatus({
          type: "error",
          message: data.error || data.message || "Failed to update worker.",
        });
      }
    } catch {
      setStatus({ type: "error", message: "Failed to update worker." });
    }
  };

  // Delete worker
  const handleDelete = async (id) => {
    if (!window.confirm("Are you sure you want to delete this worker?")) return;
    try {
      const res = await authFetch(API_BASE, {
        method: "DELETE",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify({ id }),
      });
      const data = await res.json();
      if (res.ok || data.success) {
        setStatus({
          type: "success",
          message: data.message || "Worker deleted successfully!",
        });
        fetchWorkers();
      } else {
        setStatus({
          type: "error",
          message: data.error || data.message || "Failed to delete worker.",
        });
      }
    } catch {
      setStatus({ type: "error", message: "Failed to delete worker." });
    }
  };

  // Auto-hide status message
  useEffect(() => {
    if (status) {
      const timer = setTimeout(() => setStatus(null), 3000);
      return () => clearTimeout(timer);
    }
  }, [status]);

  // Pagination helpers
  const paginatedWorkers = workers.slice(
    (pagination.page - 1) * pagination.rowsPerPage,
    pagination.page * pagination.rowsPerPage
  );

  const totalPages = Math.ceil(workers.length / pagination.rowsPerPage);

  const handlePageChange = (newPage) => {
    setPagination({ ...pagination, page: newPage });
  };

  const handleRowsPerPageChange = (e) => {
    setPagination({ page: 1, rowsPerPage: parseInt(e.target.value) });
  };

  return (
    <div className="min-h-screen bg-gradient-to-br from-blue-50 via-white to-purple-50">
      <div className="container mx-auto px-2 sm:px-4 py-4 sm:py-6 lg:py-8 max-w-7xl">
        <StatusMessage status={status} onClose={() => setStatus(null)} />

        {/* Workers Section */}
        <div className="glass-effect rounded-xl shadow-xl border border-white/20 p-5 sm:p-6 lg:p-8 mb-5 sm:mb-6 hover:shadow-2xl transition-all duration-300">
          <div className="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4 mb-6">
            <div className="flex items-center gap-3">
              <div className="bg-gradient-to-br from-blue-500 to-indigo-600 p-3 rounded-xl shadow-lg">
                <svg className="w-6 h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z" />
                </svg>
              </div>
              <h2 className="text-lg sm:text-xl font-bold text-gray-800">Workers</h2>
            </div>
            <button
              onClick={() => {
                setForm(emptyWorker);
                setModalOpen(true);
              }}
              className="px-6 py-3 bg-gradient-to-r from-blue-600 to-blue-700 text-white font-semibold rounded-xl shadow-xl hover:shadow-2xl hover:scale-105 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2 transition-all duration-300 flex items-center gap-2"
            >
              <svg className="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M12 4v16m8-8H4" />
              </svg>
              Add Worker
            </button>
          </div>

          {/* Workers Table */}
          <div className="overflow-hidden rounded-xl border-2 border-gray-200/50 shadow-inner bg-white/50 backdrop-blur-sm">
        <div className="overflow-x-auto">
          <table className="min-w-full divide-y divide-gray-200">
            <thead className="bg-gradient-to-r from-gray-50 to-gray-100">
              <tr>
                <th className="px-6 py-3.5 text-left text-xs font-bold text-gray-700 uppercase tracking-wider">
                  ID
                </th>
                <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                  Worker Name
                </th>
                <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                  IP Address
                </th>
                <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                  Status
                </th>
                <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                  Actions
                </th>
              </tr>
            </thead>
            <tbody className="bg-white divide-y divide-gray-200">
              {loading ? (
                <TableSkeleton rows={5} columns={5} />
              ) : workers.length === 0 ? (
                <tr>
                  <td colSpan={5} className="px-6 py-8 text-center">
                    <div className="flex flex-col items-center">
                      <i className="fas fa-users text-6xl text-gray-300 mb-4"></i>
                      <p className="text-gray-500">No workers found. Add one to get started.</p>
                    </div>
                  </td>
                </tr>
              ) : (
                paginatedWorkers.map((worker) => (
                  <tr key={worker.id} className={worker.is_active === 0 ? 'bg-gray-50' : ''}>
                    <td className="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                      {worker.id}
                    </td>
                    <td className="px-6 py-4 whitespace-nowrap">
                      <div className="text-sm font-medium text-gray-900">
                        {worker.workername}
                      </div>
                    </td>
                    <td className="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                      {worker.ip}
                    </td>
                    <td className="px-6 py-4 whitespace-nowrap">
                      <button
                        onClick={() => toggleWorkerStatus(worker)}
                        className={`inline-flex items-center px-3 py-1 rounded-full text-xs font-medium transition-colors ${
                          worker.is_active === 1
                            ? 'bg-green-100 text-green-800 hover:bg-green-200'
                            : 'bg-red-100 text-red-800 hover:bg-red-200'
                        }`}
                        title={worker.is_active === 1 ? 'Click to deactivate' : 'Click to activate'}
                      >
                        <i className={`fas ${worker.is_active === 1 ? 'fa-check-circle' : 'fa-times-circle'} mr-1`}></i>
                        {worker.is_active === 1 ? 'Active' : 'Inactive'}
                      </button>
                    </td>
                    <td className="px-6 py-4 whitespace-nowrap text-sm font-medium">
                      <button
                        onClick={() => handleEdit(worker)}
                        className="text-blue-600 hover:text-blue-900 mr-3"
                        title="Edit"
                      >
                        <i className="fas fa-edit mr-1"></i>
                      </button>
                      <button
                        onClick={() => handleDelete(worker.id)}
                        className="text-red-600 hover:text-red-900"
                        title="Delete"
                      >
                        <i className="fas fa-trash mr-1"></i>
                      </button>
                    </td>
                  </tr>
                ))
              )}
            </tbody>
          </table>
        </div>

        {/* Pagination */}
        {workers.length > 0 && (
          <div className="flex flex-col items-center justify-center mt-6 px-1 gap-2 pb-4">
            <div className="text-sm text-gray-500 mb-2">
              Showing{" "}
              <span className="font-medium">
                {(pagination.page - 1) * pagination.rowsPerPage + 1}
              </span>{" "}
              to{" "}
              <span className="font-medium">
                {Math.min(pagination.page * pagination.rowsPerPage, workers.length)}
              </span>{" "}
              of <span className="font-medium">{workers.length}</span> workers
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

      {/* Add Worker Modal */}
      {modalOpen && (
        <div className="fixed inset-0 z-[9999] bg-black/50 backdrop-blur-sm overflow-y-auto">
          <div className="min-h-screen px-4 text-center">
            <span className="inline-block h-screen align-middle" aria-hidden="true">&#8203;</span>
            <div className="inline-block w-full max-w-2xl my-8 text-left align-middle transition-all transform bg-white shadow-2xl rounded-xl">
              {/* Header */}
              <div className="px-6 py-4 bg-gradient-to-r from-indigo-50 to-purple-50 border-b border-gray-200 rounded-t-xl flex justify-between items-center">
                <div className="flex items-center space-x-3">
                  <div className="bg-white p-2.5 rounded-lg shadow-sm">
                    <i className="fas fa-plus-circle text-indigo-600 text-xl"></i>
                  </div>
                  <div>
                    <h3 className="text-xl font-bold text-gray-900">Add New Worker</h3>
                    <p className="text-sm text-gray-600 mt-0.5">Configure worker name and IP address</p>
                  </div>
                </div>
                <button
                  onClick={() => setModalOpen(false)}
                  className="text-gray-400 hover:text-gray-600 hover:bg-white/50 rounded-lg p-2 transition-colors"
                >
                  <i className="fas fa-times text-xl"></i>
                </button>
              </div>

              {/* Body */}
              <form className="px-6 py-6 space-y-5" onSubmit={handleAdd}>
              <div>
                <label className="block text-sm font-semibold text-gray-700 mb-2">
                  Worker Name
                </label>
                <input
                  type="text"
                  name="workername"
                  required
                  maxLength={50}
                  className="block w-full px-4 py-3 border-2 border-gray-200 rounded-xl bg-white/80 backdrop-blur-sm focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 transition-all shadow-sm hover:shadow-md text-sm"
                  placeholder="Enter worker name"
                  value={form.workername}
                  onChange={handleChange}
                />
              </div>
              <div>
                <label className="block text-sm font-semibold text-gray-700 mb-2">
                  IP Address
                </label>
                <input
                  type="text"
                  name="ip"
                  required
                  maxLength={39}
                  className="block w-full px-4 py-3 border-2 border-gray-200 rounded-xl bg-white/80 backdrop-blur-sm focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 transition-all shadow-sm hover:shadow-md text-sm"
                  placeholder="Enter IP address"
                  value={form.ip}
                  onChange={handleChange}
                />
              </div>
              <div>
                <label className="block text-sm font-semibold text-gray-700 mb-2">
                  Status
                </label>
                <select
                  name="is_active"
                  className="block w-full px-4 py-3 border-2 border-gray-200 rounded-xl bg-white/80 backdrop-blur-sm focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 transition-all shadow-sm hover:shadow-md text-sm"
                  value={form.is_active !== undefined ? form.is_active : 1}
                  onChange={handleChange}
                >
                  <option value={1}>Active</option>
                  <option value={0}>Inactive</option>
                </select>
              </div>
              </form>
              
              {/* Footer */}
              <div className="flex justify-end gap-3 px-6 py-4 bg-gray-50 rounded-b-xl border-t border-gray-200">
                <button
                  type="button"
                  onClick={() => setModalOpen(false)}
                  className="px-6 py-2.5 border-2 border-gray-300 text-sm font-semibold rounded-xl text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-gray-400 transition-all shadow-sm hover:shadow-md"
                >
                  <i className="fas fa-times mr-2"></i>
                  Cancel
                </button>
                <button
                  type="submit"
                  onClick={(e) => {
                    e.preventDefault();
                    const form = e.target.closest('.inline-block').querySelector('form');
                    form.dispatchEvent(new Event('submit', { cancelable: true, bubbles: true }));
                  }}
                  className="px-6 py-2.5 text-sm font-semibold rounded-xl text-white bg-gradient-to-r from-indigo-600 to-purple-600 hover:from-indigo-700 hover:to-purple-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 transition-all shadow-lg hover:shadow-xl hover:scale-105"
                >
                  <i className="fas fa-save mr-2"></i>
                  Add Worker
                </button>
              </div>
            </div>
          </div>
        </div>
      )}

      {/* Edit Worker Modal */}
      {editModalOpen && (
        <div className="fixed inset-0 z-[9999] bg-black/50 backdrop-blur-sm overflow-y-auto">
          <div className="min-h-screen px-4 text-center">
            <span className="inline-block h-screen align-middle" aria-hidden="true">&#8203;</span>
            <div className="inline-block w-full max-w-2xl my-8 text-left align-middle transition-all transform bg-white shadow-2xl rounded-xl">
              {/* Header */}
              <div className="px-6 py-4 bg-gradient-to-r from-indigo-50 to-purple-50 border-b border-gray-200 rounded-t-xl flex justify-between items-center">
                <div className="flex items-center space-x-3">
                  <div className="bg-white p-2.5 rounded-lg shadow-sm">
                    <i className="fas fa-edit text-indigo-600 text-xl"></i>
                  </div>
                  <div>
                    <h3 className="text-xl font-bold text-gray-900">Edit Worker</h3>
                    <p className="text-sm text-gray-600 mt-0.5">Update worker configuration</p>
                  </div>
                </div>
                <button
                  onClick={() => setEditModalOpen(false)}
                  className="text-gray-400 hover:text-gray-600 hover:bg-white/50 rounded-lg p-2 transition-colors"
                >
                  <i className="fas fa-times text-xl"></i>
                </button>
              </div>

              {/* Body */}
              <form className="px-6 py-6 space-y-5" onSubmit={handleUpdate}>
              <div>
                <label className="block text-sm font-semibold text-gray-700 mb-2">
                  Worker Name
                </label>
                <input
                  type="text"
                  name="workername"
                  required
                  maxLength={50}
                  className="block w-full px-4 py-3 border-2 border-gray-200 rounded-xl bg-white/80 backdrop-blur-sm focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 transition-all shadow-sm hover:shadow-md text-sm"
                  placeholder="Enter worker name"
                  value={form.workername}
                  onChange={handleChange}
                />
              </div>
              <div>
                <label className="block text-sm font-semibold text-gray-700 mb-2">
                  IP Address
                </label>
                <input
                  type="text"
                  name="ip"
                  required
                  maxLength={39}
                  className="block w-full px-4 py-3 border-2 border-gray-200 rounded-xl bg-white/80 backdrop-blur-sm focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 transition-all shadow-sm hover:shadow-md text-sm"
                  placeholder="Enter IP address"
                  value={form.ip}
                  onChange={handleChange}
                />
              </div>
              <div>
                <label className="block text-sm font-semibold text-gray-700 mb-2">
                  Status
                </label>
                <select
                  name="is_active"
                  className="block w-full px-4 py-3 border-2 border-gray-200 rounded-xl bg-white/80 backdrop-blur-sm focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 transition-all shadow-sm hover:shadow-md text-sm"
                  value={form.is_active !== undefined ? form.is_active : 1}
                  onChange={handleChange}
                >
                  <option value={1}>Active</option>
                  <option value={0}>Inactive</option>
                </select>
              </div>
              </form>
              
              {/* Footer */}
              <div className="flex justify-end gap-3 px-6 py-4 bg-gray-50 rounded-b-xl border-t border-gray-200">
                <button
                  type="button"
                  onClick={() => setEditModalOpen(false)}
                  className="px-6 py-2.5 border-2 border-gray-300 text-sm font-semibold rounded-xl text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-gray-400 transition-all shadow-sm hover:shadow-md"
                >
                  <i className="fas fa-times mr-2"></i>
                  Cancel
                </button>
                <button
                  type="submit"
                  onClick={(e) => {
                    e.preventDefault();
                    const form = e.target.closest('.inline-block').querySelector('form');
                    form.dispatchEvent(new Event('submit', { cancelable: true, bubbles: true }));
                  }}
                  className="px-6 py-2.5 text-sm font-semibold rounded-xl text-white bg-gradient-to-r from-indigo-600 to-purple-600 hover:from-indigo-700 hover:to-purple-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 transition-all shadow-lg hover:shadow-xl hover:scale-105"
                >
                  <i className="fas fa-save mr-2"></i>
                  Update Worker
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

export default Workers;