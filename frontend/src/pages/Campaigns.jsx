import React, { useEffect, useState } from "react";

const emptyCampaign = {
  description: "",
  mail_subject: "",
  mail_body: "",
  attachment: null,
};

// Glassmorphism Status Message Popup
const StatusMessage = ({ message, onClose }) =>
  message && (
    <div
      className={`
        fixed top-6 left-1/2 transform -translate-x-1/2 z-50
        px-6 py-3 rounded-xl shadow text-base font-semibold
        flex items-center gap-3
        transition-all duration-300
        backdrop-blur-md
        ${message.type === "error"
          ? "bg-red-200/60 border border-red-400 text-red-800"
          : "bg-green-200/60 border border-green-400 text-green-800"
        }
      `}
      style={{
        minWidth: 250,
        maxWidth: 400,
        boxShadow: "0 8px 32px 0 rgba(0, 0, 0, 0.23)",
        background:
          message.type === "error"
            ? "rgba(255, 0, 0, 0.29)"
            : "rgba(0, 200, 83, 0.29)",
        borderRadius: "16px",
        backdropFilter: "blur(8px)",
        WebkitBackdropFilter: "blur(8px)",
      }}
      role="alert"
    >
      <i
        className={`fas text-lg ${message.type === "error"
          ? "fa-exclamation-circle text-red-500"
          : "fa-check-circle text-green-500"
          }`}
      ></i>
      <span className="flex-1">{message.text}</span>
      <button
        onClick={onClose}
        className="ml-2 text-gray-500 hover:text-gray-700 focus:outline-none"
        aria-label="Close"
      >
        <i className="fas fa-times"></i>
      </button>
    </div>
  );

const Campaigns = () => {
  const [campaigns, setCampaigns] = useState([]);
  const [loading, setLoading] = useState(true);
  const [modalOpen, setModalOpen] = useState(false);
  const [editModalOpen, setEditModalOpen] = useState(false);
  const [form, setForm] = useState(emptyCampaign);
  const [attachmentFile, setAttachmentFile] = useState(null);
  const [editId, setEditId] = useState(null);
  const [message, setMessage] = useState(null);

  // Pagination state
  const [pagination, setPagination] = useState({
    page: 1,
    rowsPerPage: 10,
    total: 0,
  });

  const API_URL = "http://localhost/Verify_email/backend/routes/api.php/api/master/campaigns";

  // Fetch campaigns
  const fetchCampaigns = async () => {
    setLoading(true);
    try {
      const res = await fetch(API_URL);
      const data = await res.json();
      if (Array.isArray(data)) {
        setCampaigns(data);
        setPagination((prev) => ({
          ...prev,
          total: data.length,
        }));
      } else {
        setCampaigns([]);
        setPagination((prev) => ({
          ...prev,
          total: 0,
        }));
      }
    } catch {
      setMessage({ type: "error", text: "Failed to load campaigns." });
      setCampaigns([]);
      setPagination((prev) => ({
        ...prev,
        total: 0,
      }));
    }
    setLoading(false);
  };

  useEffect(() => {
    fetchCampaigns();
  }, []);

  // Handle form input
  const handleChange = (e) => {
    const { name, value, files } = e.target;
    if (name === "attachment") {
      setAttachmentFile(files[0]);
    } else {
      setForm((f) => ({
        ...f,
        [name]: value,
      }));
    }
  };

  // Add campaign
  const handleAdd = async (e) => {
    e.preventDefault();
    const formData = new FormData();
    formData.append("description", form.description);
    formData.append("mail_subject", form.mail_subject);
    formData.append("mail_body", form.mail_body);
    if (attachmentFile) {
      formData.append("attachment", attachmentFile);
    }

    try {
      const res = await fetch(API_URL, {
        method: "POST",
        body: formData,
      });
      const data = await res.json();
      if (data.success) {
        setMessage({ type: "success", text: "Campaign added successfully!" });
        setModalOpen(false);
        setForm(emptyCampaign);
        setAttachmentFile(null);
        fetchCampaigns();
      } else {
        setMessage({
          type: "error",
          text: data.message || "Failed to add campaign.",
        });
      }
    } catch {
      setMessage({ type: "error", text: "Failed to add campaign." });
    }
  };

  // Edit campaign (no change needed for attachment)
  const handleEdit = (campaign) => {
    setEditId(campaign.campaign_id);
    setForm({
      description: campaign.description,
      mail_subject: campaign.mail_subject,
      mail_body: campaign.mail_body,
      attachment: null,
    });
    setAttachmentFile(null);
    setEditModalOpen(true);
  };

  // Update campaign
  const handleUpdate = async (e) => {
    e.preventDefault();
    const formData = new FormData();
    formData.append("description", form.description);
    formData.append("mail_subject", form.mail_subject);
    formData.append("mail_body", form.mail_body);
    if (attachmentFile) {
      formData.append("attachment", attachmentFile);
    }
    // Tell backend this is an update
    formData.append("_method", "PUT");

    try {
      const res = await fetch(`${API_URL}?id=${editId}`, {
        method: "POST", // Still POST for file upload, backend checks _method
        body: formData,
      });
      const data = await res.json();
      if (data.success) {
        setMessage({ type: "success", text: "Campaign updated successfully!" });
        setEditModalOpen(false);
        setForm(emptyCampaign);
        setAttachmentFile(null);
        fetchCampaigns();
      } else {
        setMessage({
          type: "error",
          text: data.message || "Failed to update campaign.",
        });
      }
    } catch {
      setMessage({ type: "error", text: "Failed to update campaign." });
    }
  };

  // Delete campaign (optimistic UI update)
  const handleDelete = async (id) => {
    // Optimistically remove from UI
    setCampaigns((prev) => prev.filter((c) => c.campaign_id !== id));
    setPagination((prev) => ({
      ...prev,
      total: prev.total - 1,
    }));

    try {
      const res = await fetch(`${API_URL}?id=${id}`, { method: "DELETE" });
      const data = await res.json();

      if (data.success) {
        setMessage({ type: "success", text: "Campaign deleted successfully!" });
        // No need to reload, already removed
      } else {
        setMessage({
          type: "error",
          text: data.message || "Failed to delete campaign.",
        });
        // Restore if failed
        fetchCampaigns();
      }
    } catch {
      setMessage({ type: "error", text: "Failed to delete campaign." });
      fetchCampaigns();
    }
  };


  // Reuse campaign
  const handleReuse = async (id) => {
    try {
      const res = await fetch(`${API_URL}?id=${id}`);
      const data = await res.json();
      setForm({
        description: data.description,
        mail_subject: data.mail_subject,
        mail_body: data.mail_body,
        attachment: null,
      });
      setEditId(null); // <-- Clear editId so handleAdd will be used
      setAttachmentFile(null); // <-- Clear any previous file
      setModalOpen(true);
    } catch {
      setMessage({ type: "error", text: "Failed to load campaign for reuse." });
    }
  };

  // Auto-hide message after 3 seconds
  useEffect(() => {
    if (message) {
      const timer = setTimeout(() => setMessage(null), 3000);
      return () => clearTimeout(timer);
    }
  }, [message]);

  // Preview first 30 words
  const preview = (body) => {
    const words = body.split(/\s+/);
    return words.slice(0, 30).join(" ") + (words.length > 30 ? "..." : "");
  };

  // Pagination logic
  const totalPages = Math.max(1, Math.ceil(pagination.total / pagination.rowsPerPage));
  const paginatedCampaigns = campaigns.slice(
    (pagination.page - 1) * pagination.rowsPerPage,
    pagination.page * pagination.rowsPerPage
  );

  return (
    <div className="container mx-auto mt-12 px-2 sm:px-4 py-8 max-w-7xl">
      {/* Status Message */}
      <StatusMessage message={message} onClose={() => setMessage(null)} />

      <div className="flex justify-between items-center mb-6">
        <h1 className="text-2xl font-bold text-gray-800">
          <i className="fas fa-bullhorn mr-2 text-blue-600"></i>
          Email Campaigns
        </h1>
        <button
          onClick={() => {
            setForm(emptyCampaign);
            setModalOpen(true);
          }}
          className="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-md flex items-center"
        >
          <i className="fas fa-plus mr-2"></i> Add Campaign
        </button>
      </div>

      {/* Responsive Campaigns List */}
      <div className="block sm:hidden">
        {paginatedCampaigns.map((c) => (
          <div
            key={c.campaign_id}
            className="bg-white rounded-2xl shadow p-4 mb-4 flex flex-col"
          >
            <div className="flex justify-between items-start">
              <div>
                <div className="text-lg font-bold text-gray-900 mb-1">
                  ID: {c.campaign_id}
                </div>
                <div className="font-semibold text-gray-900">Description:</div>
                <div className="text-gray-600 text-base mb-2 break-words">
                  {c.description}
                </div>
              </div>
              <div className="flex flex-col gap-2 items-end ml-2">
                <button
                  onClick={() => handleEdit(c)}
                  className="text-blue-600 hover:text-blue-800 p-1 rounded"
                  title="Edit"
                >
                  <i className="fas fa-edit text-xl"></i>
                </button>
                <button
                  onClick={() => handleReuse(c.campaign_id)}
                  className="text-green-600 hover:text-green-800 p-1 rounded"
                  title="Reuse"
                >
                  <i className="fas fa-copy text-xl"></i>
                </button>
                <button
                  onClick={() => {
                    if (window.confirm("Are you sure you want to delete this campaign?")) {
                      handleDelete(c.campaign_id);
                    }
                  }}
                  className="text-red-600 hover:text-red-800 p-1 rounded"
                  title="Delete"
                >
                  <i className="fas fa-trash text-xl"></i>
                </button>
              </div>
            </div>
            <div className="mt-2">
              <div className="font-semibold text-gray-900">Subject:</div>
              <div className="text-gray-700 text-sm break-words mb-1">{c.mail_subject}</div>
              <div className="font-semibold text-gray-900">Email Preview:</div>
              <div className="text-gray-500 text-sm break-words">{preview(c.mail_body)}</div>
            </div>
          </div>
        ))}
      </div>

      {/* Desktop Table */}
      <div className="hidden sm:block bg-white rounded-lg shadow overflow-hidden">
        <div className="overflow-x-auto">
          <table className="min-w-full divide-y divide-gray-200">
            <thead className="bg-gray-50">
              <tr>
                <th className="w-16 px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                  ID
                </th>
                <th className="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                  Description
                </th>
                <th className="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                  Subject
                </th>
                <th className="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                  Email Preview
                </th>
                <th className="w-40 px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">
                  Actions
                </th>
              </tr>
            </thead>
            <tbody className="bg-white divide-y divide-gray-200">
              {loading ? (
                <tr>
                  <td
                    colSpan={5}
                    className="px-6 py-4 text-center text-sm text-gray-500"
                  >
                    Loading...
                  </td>
                </tr>
              ) : campaigns.length === 0 ? (
                <tr>
                  <td
                    colSpan={5}
                    className="px-6 py-4 text-center text-sm text-gray-500"
                  >
                    No campaigns found. Add one to get started.
                  </td>
                </tr>
              ) : (
                paginatedCampaigns.map((c) => (
                  <tr
                    key={c.campaign_id}
                    className="hover:bg-gray-50 transition-colors duration-150"
                  >
                    <td className="px-4 py-3 whitespace-nowrap text-sm font-medium text-gray-500">
                      {c.campaign_id}
                    </td>
                    <td className="px-4 py-3">
                      <div className="text-sm font-medium text-gray-900 truncate max-w-xs">
                        {c.description}
                      </div>
                    </td>
                    <td className="px-4 py-3">
                      <div className="text-sm text-gray-900 truncate max-w-xs">
                        {c.mail_subject}
                      </div>
                    </td>
                    <td className="px-4 py-3">
                      <div
                        className="text-sm text-gray-500 truncate max-w-xs"
                        title={preview(c.mail_body)}
                      >
                        {preview(c.mail_body)}
                      </div>
                    </td>
                    <td className="px-4 py-3 whitespace-nowrap text-right text-sm font-medium">
                      <div className="flex justify-end space-x-2">
                        <button
                          onClick={() => handleEdit(c)}
                          className="text-blue-600 hover:text-blue-800 p-1 rounded hover:bg-blue-50"
                          title="Edit"
                        >
                          <i className="fas fa-edit"></i>
                        </button>
                        <button
                          onClick={() => handleReuse(c.campaign_id)}
                          className="text-green-600 hover:text-green-800 p-1 rounded hover:bg-green-50"
                          title="Reuse"
                        >
                          <i className="fas fa-copy"></i>
                        </button>
                        <button
                          onClick={() => {
                            if (window.confirm("Are you sure you want to delete this campaign?")) {
                              handleDelete(c.campaign_id);
                            }
                          }}
                          className="text-red-600 hover:text-red-800 p-1 rounded hover:bg-red-50"
                          title="Delete"
                        >
                          <i className="fas fa-trash"></i>
                        </button>
                      </div>
                    </td>
                  </tr>
                ))
              )}
            </tbody>
          </table>
        </div>
      </div>

      {/* Pagination Controls */}
      {campaigns.length > 0 && (
        <div className="flex flex-col items-center justify-center mt-6 px-1 gap-2">
          <div className="text-xs sm:text-sm text-gray-500 mb-2">
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
            of <span className="font-medium">{pagination.total}</span>{" "}
            campaigns
          </div>
          <div className="flex flex-wrap items-center gap-2 pb-5">
            <button
              onClick={() =>
                setPagination((prev) => ({ ...prev, page: 1 }))
              }
              disabled={pagination.page === 1}
              className="p-2 border border-gray-300 rounded-lg hover:bg-gray-50 disabled:opacity-50 transition-colors"
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
                  page: prev.page - 1,
                }))
              }
              disabled={pagination.page === 1}
              className="p-2 border border-gray-300 rounded-lg hover:bg-gray-50 disabled:opacity-50 transition-colors"
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
            <span className="text-xs sm:text-sm font-medium text-gray-700">
              Page {pagination.page} of {totalPages}
            </span>
            <button
              onClick={() =>
                setPagination((prev) => ({
                  ...prev,
                  page: Math.min(totalPages, prev.page + 1),
                }))
              }
              disabled={pagination.page >= totalPages}
              className="p-2 border border-gray-300 rounded-lg hover:bg-gray-50 disabled:opacity-50 transition-colors"
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
                  page: totalPages,
                }))
              }
              disabled={pagination.page >= totalPages}
              className="p-2 border border-gray-300 rounded-lg hover:bg-gray-50 disabled:opacity-50 transition-colors"
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
              className="border p-2 rounded-lg text-xs sm:text-sm bg-white focus:ring-blue-500 focus:border-blue-500 transition-colors"
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

      {/* Add Campaign Modal */}
      {modalOpen && (
        <div className="fixed inset-0 bg-gr bg-black/30 backdrop-blur-md backdrop-saturate-150 border border-white/20 shadow-xl overflow-y-auto h-full w-full z-50 flex items-center justify-center">
          <div className="relative mx-auto p-5 border w-11/12 md:w-2/3 lg:w-1/2 shadow-lg rounded-md bg-white">
            <div className="flex justify-between items-center mb-4">
              <h3 className="text-lg font-medium text-gray-900">
                <i className="fas fa-plus-circle mr-2 text-blue-600"></i>
                Add New Campaign
              </h3>
              <button
                onClick={() => setModalOpen(false)}
                className="text-gray-400 hover:text-gray-500"
              >
                <i className="fas fa-times"></i>
              </button>
            </div>
            <form className="space-y-4" onSubmit={handleAdd} encType="multipart/form-data">
              <div>
                <label className="block text-sm font-medium text-gray-700 mb-1">
                  Description
                </label>
                <input
                  type="text"
                  name="description"
                  required
                  className="block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 sm:text-sm"
                  placeholder="Campaign description"
                  value={form.description}
                  onChange={handleChange}
                />
              </div>
              <div>
                <label className="block text-sm font-medium text-gray-700 mb-1">
                  Email Subject
                </label>
                <input
                  type="text"
                  name="mail_subject"
                  required
                  className="block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 sm:text-sm"
                  placeholder="Your email subject"
                  value={form.mail_subject}
                  onChange={handleChange}
                />
              </div>
              <div>
                <label className="block text-sm font-medium text-gray-700 mb-1">
                  Email Body
                </label>
                <textarea
                  name="mail_body"
                  rows={8}
                  required
                  className="block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 sm:text-sm font-mono text-sm"
                  placeholder="Compose your email content here..."
                  value={form.mail_body}
                  onChange={handleChange}
                ></textarea>
              </div>
              <div>
                <label className="block text-sm font-medium text-black-700 mb-1">
                  Attachment
                </label>
                <input
                  type="file"
                  name="attachment"
                  className="block w-full text-sm file:mr-4 file:py-2 file:px-4 file:rounded-md file:border-0 file:text-sm file:font-semibold file:bg-blue-50 file:text-blue-700 hover:file:bg-blue-100"
                  onChange={handleChange}
                  // Example: accept only PDF, images, and docs. Adjust as needed.
                  accept=".pdf,.jpg,.jpeg,.png,.doc,.docx,.xls,.xlsx,.csv,.txt"
                  id="attachment-input"
                />
                <div className="text-xs text-gray-500 mt-1">
                  {attachmentFile
                    ? `Selected: ${attachmentFile.name}`
                    : "No file chosen"}
                </div>
              </div>
              <div className="flex justify-end pt-4 space-x-3">
                <button
                  type="button"
                  onClick={() => setModalOpen(false)}
                  className="bg-white py-2 px-4 border border-gray-300 rounded-md shadow-sm text-sm font-medium text-gray-700 hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500"
                >
                  Cancel
                </button>
                <button
                  type="submit"
                  className="bg-blue-600 hover:bg-blue-700 text-white py-2 px-4 rounded-md shadow-sm text-sm font-medium focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500"
                >
                  <i className="fas fa-save mr-2"></i> Save Campaign
                </button>
              </div>
            </form>
          </div>
        </div>
      )}

      {/* Edit Campaign Modal */}
      {editModalOpen && (
        <div className="fixed inset-0  bg-black/30 backdrop-blur-md backdrop-saturate-150 overflow-y-auto h-full w-full z-50 flex items-center justify-center">
          <div className="relative mx-auto p-5 border w-11/12 md:w-2/3 lg:w-1/2 shadow-lg rounded-md bg-white">
            <div className="flex justify-between items-center mb-4">
              <h3 className="text-lg font-medium text-gray-900">
                <i className="fas fa-edit mr-2 text-blue-600"></i>
                Edit Campaign
              </h3>
              <button
                onClick={() => setEditModalOpen(false)}
                className="text-gray-400 hover:text-gray-500"
              >
                <i className="fas fa-times"></i>
              </button>
            </div>
            <form className="space-y-4" onSubmit={handleUpdate} encType="multipart/form-data">
              <div>
                <label className="block text-sm font-medium text-gray-700 mb-1">
                  Description
                </label>
                <input
                  type="text"
                  name="description"
                  required
                  className="block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 sm:text-sm"
                  value={form.description}
                  onChange={handleChange}
                />
              </div>
              <div>
                <label className="block text-sm font-medium text-gray-700 mb-1">
                  Email Subject
                </label>
                <input
                  type="text"
                  name="mail_subject"
                  required
                  className="block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 sm:text-sm"
                  value={form.mail_subject}
                  onChange={handleChange}
                />
              </div>
              <div>
                <label className="block text-sm font-medium text-gray-700 mb-1">
                  Email Body
                </label>
                <textarea
                  name="mail_body"
                  rows={8}
                  required
                  className="block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 sm:text-sm font-mono text-sm"
                  value={form.mail_body}
                  onChange={handleChange}
                ></textarea>
              </div>
              <div>
                <label className="block text-sm font-medium text-gray-700 mb-1">
                  Attachment
                </label>
                <input
                  type="file"
                  name="attachment"
                  className="block w-full text-sm file:mr-4 file:py-2 file:px-4 file:rounded-md file:border-0 file:text-sm file:font-semibold file:bg-blue-50 file:text-blue-700 hover:file:bg-blue-100"
                  onChange={handleChange}
                  accept="*"
                />
                {attachmentFile && (
                  <div className="text-xs text-gray-500 mt-1">
                    Selected: {attachmentFile.name}
                  </div>
                )}
              </div>
              <div className="flex justify-end pt-4 space-x-3">
                <button
                  type="button"
                  onClick={() => setEditModalOpen(false)}
                  className="bg-white py-2 px-4 border border-gray-300 rounded-md shadow-sm text-sm font-medium text-gray-700 hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500"
                >
                  Cancel
                </button>
                <button
                  type="submit"
                  className="bg-blue-600 hover:bg-blue-700 text-white py-2 px-4 rounded-md shadow-sm text-sm font-medium focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500"
                >
                  <i className="fas fa-save mr-2"></i> Update
                </button>
              </div>
            </form>
          </div>
        </div>
      )}
    </div>
  );
};

export default Campaigns;
