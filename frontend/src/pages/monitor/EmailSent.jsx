import React, { useEffect, useState, useRef } from "react";

const statusColors = {
  pending: "bg-yellow-500",
  running: "bg-blue-600",
  paused: "bg-gray-500",
  completed: "bg-green-600",
  failed: "bg-red-600",
};

const ROWS_PER_PAGE = 10;

const EmailSent = () => {
  const [campaigns, setCampaigns] = useState([]);
  const [loading, setLoading] = useState(true);
  const [message, setMessage] = useState(null);
  const isFirstLoad = useRef(true);

  // Pagination state
  const [pagination, setPagination] = useState({
    page: 1,
    rowsPerPage: ROWS_PER_PAGE,
    total: 0,
  });

  // Fetch campaigns
  const fetchCampaigns = async () => {
    // Only show loading on first load
    if (isFirstLoad.current) setLoading(true);
    try {
      const res = await fetch("http://localhost/Verify_email/backend/routes/api.php/api/monitor/campaigns");
      const data = await res.json();
      setCampaigns(Array.isArray(data) ? data : []);
      setPagination((prev) => ({
        ...prev,
        total: Array.isArray(data) ? data.length : 0,
      }));
    } catch {
      setMessage({ type: "error", text: "Failed to load campaigns." });
    }
    setLoading(false);
    isFirstLoad.current = false;
  };

  useEffect(() => {
    fetchCampaigns();
    // Auto-refresh every 5 seconds
    const interval = setInterval(fetchCampaigns, 5000);
    return () => clearInterval(interval);
  }, []);

  // Calculate pagination
  const totalPages = Math.ceil(pagination.total / pagination.rowsPerPage);
  const paginatedCampaigns = campaigns.slice(
    (pagination.page - 1) * pagination.rowsPerPage,
    pagination.page * pagination.rowsPerPage
  );

  // Reset to first page if campaigns change and page is out of range
  useEffect(() => {
    if (pagination.page > totalPages && totalPages > 0) {
      setPagination((prev) => ({ ...prev, page: 1 }));
    }
  }, [campaigns, totalPages]);

  return (
    <div className="container mx-auto px-4 py-8 max-w-7xl">
      <h1 className="text-2xl font-bold text-gray-800 mb-6 flex items-center">
        <i className="fas fa-chart-line mr-2 text-blue-600"></i>
        Campaign Monitor
      </h1>
      {message && (
        <div className="bg-red-100 border-l-4 border-red-500 text-red-700 p-4 mb-6 rounded-md flex items-start">
          <div className="ml-3">
            <p className="text-sm font-medium">{message.text}</p>
          </div>
          <div className="ml-auto pl-3">
            <button onClick={() => setMessage(null)} className="text-gray-500 hover:text-gray-700">
              <i className="fas fa-times"></i>
            </button>
          </div>
        </div>
      )}
      <div className="bg-white rounded-lg shadow overflow-hidden">
        <div className="overflow-x-auto">
          <table className="min-w-full divide-y divide-gray-200">
            <thead className="bg-gray-50">
              <tr>
                <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">ID</th>
                <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Description</th>
                <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Progress</th>
                <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Emails</th>
              </tr>
            </thead>
            <tbody className="bg-white divide-y divide-gray-200">
              {loading ? (
                <tr>
                  <td colSpan={5} className="px-6 py-4 text-center text-sm text-gray-500">
                    Loading...
                  </td>
                </tr>
              ) : paginatedCampaigns.length === 0 ? (
                <tr>
                  <td colSpan={5} className="px-6 py-4 text-center text-sm text-gray-500">
                    No campaigns found.
                  </td>
                </tr>
              ) : (
                paginatedCampaigns.map((campaign) => {
                  const total = Math.max(campaign.total_emails || 0, 1);
                  const sent = Math.min(campaign.sent_emails || 0, total);
                  const progress = Math.round((sent / total) * 100);
                  const status = (campaign.campaign_status || "pending").toLowerCase();
                  return (
                    <tr key={campaign.campaign_id} className="hover:bg-gray-50">
                      <td className="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                        {campaign.campaign_id}
                      </td>
                      <td className="px-6 py-4">
                        <div className="text-sm font-medium text-gray-900">
                          {campaign.description}
                        </div>
                      </td>
                      <td className="px-6 py-4 whitespace-nowrap">
                        <span
                          className={`status-badge px-2 py-1 rounded text-xs font-semibold ${statusColors[status] || "bg-gray-400"
                            } text-white`}
                        >
                          {campaign.campaign_status || "Not started"}
                        </span>
                      </td>
                      <td className="px-6 py-4 whitespace-nowrap">
                        <div className="progress-bar h-5 bg-gray-200 rounded">
                          <div
                            className="progress-fill bg-blue-600 h-5 rounded"
                            style={{ width: `${progress}%` }}
                          ></div>
                        </div>
                        <div className="text-xs text-gray-500 mt-1">
                          {progress}% ({campaign.sent_emails || 0}/{campaign.total_emails || 0})
                        </div>
                      </td>
                      <td className="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                        <div>Total: {campaign.total_emails || 0}</div>
                        <div>Pending: {campaign.pending_emails || 0}</div>
                        <div>Sent: {campaign.sent_emails || 0}</div>
                        <div>Failed: {campaign.failed_emails || 0}</div>
                      </td>
                    </tr>
                  );
                })
              )}
            </tbody>
          </table>
        </div>
      </div>
      {/* Pagination Controls */}
      {pagination.total > 0 && (
        <div className="flex flex-col items-center justify-center mt-6 px-1 gap-2">
          <div className="flex items-center gap-4 mb-2">
            <div className="text-xs sm:text-sm text-gray-500">
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
          </div>
          <div className="flex flex-wrap items-center gap-2">
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
            {/* Add rows per page select to the right */}
            <div className="flex items-center gap-1 ml-4">

              <select
                id="rowsPerPage"
                value={pagination.rowsPerPage}
                onChange={e => {
                  setPagination(prev => ({
                    ...prev,
                    rowsPerPage: Number(e.target.value),
                    page: 1, // Reset to first page
                  }));
                }}
                className="border border-gray-300 rounded px-2 py-2 text-xs sm:text-sm"
              >
                {[10, 25, 50, 100].map(opt => (
                  <option key={opt} value={opt}>{opt}</option>
                ))}
              </select>
            </div>
          </div>
        </div>
      )}
    </div>
  );
};

export default EmailSent;