import React, { useEffect, useState } from "react";

// API endpoint for workers
const API_BASE = "http://localhost/Verify_email/backend/routes/api.php/api/workers";

const emptyWorker = {
  workername: "",
  ip: "",
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

  // Fetch workers
  const fetchWorkers = async () => {
    setLoading(true);
    try {
      const res = await fetch(API_BASE);
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
      const res = await fetch(API_BASE, {
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
      const res = await fetch(API_BASE, {
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
      const res = await fetch(API_BASE, {
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

  return (
    <main className="max-w-7xl mx-auto px-4 mt-14 sm:px-6 py-6">
      <StatusMessage status={status} onClose={() => setStatus(null)} />

      <div className="flex justify-between items-center mb-6">
        <h1 className="text-2xl font-bold text-gray-900 flex items-center">
          <i className="fas fa-users mr-3 text-blue-600"></i>
          Workers
        </h1>
        <button
          onClick={() => {
            setForm(emptyWorker);
            setModalOpen(true);
          }}
          className="inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md shadow-sm text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500"
        >
          <i className="fas fa-plus mr-2"></i> Add Worker
        </button>
      </div>

      {/* Workers Table */}
      <div className="card overflow-hidden bg-white rounded-xl shadow">
        <div className="overflow-x-auto">
          <table className="min-w-full divide-y divide-gray-200">
            <thead className="bg-gray-50">
              <tr>
                <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                  ID
                </th>
                <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                  Worker Name
                </th>
                <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                  IP Address
                </th>
                <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                  Actions
                </th>
              </tr>
            </thead>
            <tbody className="bg-white divide-y divide-gray-200">
              {loading ? (
                <tr>
                  <td colSpan={4} className="px-6 py-4 text-center text-sm text-gray-500">
                    Loading...
                  </td>
                </tr>
              ) : workers.length === 0 ? (
                <tr>
                  <td colSpan={4} className="px-6 py-4 text-center text-sm text-gray-500">
                    No workers found. Add one to get started.
                  </td>
                </tr>
              ) : (
                workers.map((worker) => (
                  <tr key={worker.id}>
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
      </div>

      {/* Add Worker Modal */}
      {modalOpen && (
        <div className="fixed inset-0 bg-black/30 backdrop-blur-md backdrop-saturate-150 border border-white/20 shadow-xl overflow-y-auto h-full w-full z-50 flex items-center justify-center">
          {/* StatusMessage inside modal */}
          <StatusMessage status={status} onClose={() => setStatus(null)} />
          <div className="relative mx-auto p-5 border w-full max-w-md shadow-lg rounded-md bg-white">
            <div className="flex justify-between items-center mb-4">
              <h3 className="text-lg font-medium text-gray-900">
                <i className="fas fa-plus-circle mr-2 text-blue-600"></i>
                Add New Worker
              </h3>
              <button
                onClick={() => setModalOpen(false)}
                className="text-gray-400 hover:text-gray-500"
              >
                <i className="fas fa-times"></i>
              </button>
            </div>
            <form className="space-y-4" onSubmit={handleAdd}>
              <div>
                <label className="block text-sm font-medium text-gray-700 mb-1">
                  Worker Name
                </label>
                <input
                  type="text"
                  name="workername"
                  required
                  maxLength={50}
                  className="block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 sm:text-sm"
                  placeholder="Enter worker name"
                  value={form.workername}
                  onChange={handleChange}
                />
              </div>
              <div>
                <label className="block text-sm font-medium text-gray-700 mb-1">
                  IP Address
                </label>
                <input
                  type="text"
                  name="ip"
                  required
                  maxLength={39}
                  className="block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 sm:text-sm"
                  placeholder="Enter IP address"
                  value={form.ip}
                  onChange={handleChange}
                />
              </div>
              <div className="flex justify-end pt-4 space-x-3">
                <button
                  type="button"
                  onClick={() => setModalOpen(false)}
                  className="inline-flex items-center px-4 py-2 border border-gray-300 text-sm font-medium rounded-md shadow-sm text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500"
                >
                  Cancel
                </button>
                <button
                  type="submit"
                  className="inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md shadow-sm text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500"
                >
                  <i className="fas fa-save mr-2"></i> Save Worker
                </button>
              </div>
            </form>
          </div>
        </div>
      )}

      {/* Edit Worker Modal */}
      {editModalOpen && (
        <div className="fixed inset-0 bg-black/30 backdrop-blur-md backdrop-saturate-150 border border-white/20 shadow-xl overflow-y-auto h-full w-full z-50 flex items-center justify-center">
          {/* StatusMessage inside modal */}
          <StatusMessage status={status} onClose={() => setStatus(null)} />
          <div className="relative mx-auto p-5 border w-full max-w-md shadow-lg rounded-md bg-white">
            <div className="flex justify-between items-center mb-4">
              <h3 className="text-lg font-medium text-gray-900">
                <i className="fas fa-edit mr-2 text-blue-600"></i>
                Edit Worker
              </h3>
              <button
                onClick={() => setEditModalOpen(false)}
                className="text-gray-400 hover:text-gray-500"
              >
                <i className="fas fa-times"></i>
              </button>
            </div>
            <form className="space-y-4" onSubmit={handleUpdate}>
              <div>
                <label className="block text-sm font-medium text-gray-700 mb-1">
                  Worker Name
                </label>
                <input
                  type="text"
                  name="workername"
                  required
                  maxLength={50}
                  className="block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 sm:text-sm"
                  placeholder="Enter worker name"
                  value={form.workername}
                  onChange={handleChange}
                />
              </div>
              <div>
                <label className="block text-sm font-medium text-gray-700 mb-1">
                  IP Address
                </label>
                <input
                  type="text"
                  name="ip"
                  required
                  maxLength={39}
                  className="block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 sm:text-sm"
                  placeholder="Enter IP address"
                  value={form.ip}
                  onChange={handleChange}
                />
              </div>
              <div className="flex justify-end pt-4 space-x-3">
                <button
                  type="button"
                  onClick={() => setEditModalOpen(false)}
                  className="inline-flex items-center px-4 py-2 border border-gray-300 text-sm font-medium rounded-md shadow-sm text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500"
                >
                  Cancel
                </button>
                <button
                  type="submit"
                  className="inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md shadow-sm text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500"
                >
                  <i className="fas fa-save mr-2"></i> Update Worker
                </button>
              </div>
            </form>
          </div>
        </div>
      )}
    </main>
  );
};

export default Workers;