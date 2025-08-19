import React, { useState, useEffect } from "react";
import axios from "axios";

const API_PUBLIC_URL = "http://localhost/Verify_email/backend/routes/api.php";

const Master = () => {
  const [campaigns, setCampaigns] = useState([]);
  const [loading, setLoading] = useState(true);
  const [message, setMessage] = useState(null);
  const [expandedCampaigns, setExpandedCampaigns] = useState({});
  const [emailCounts, setEmailCounts] = useState({});

  // Pagination state
  const [pagination, setPagination] = useState({
    page: 1,
    rowsPerPage: 10,
    total: 0,
  });

  // Fade out status message after 3s
  useEffect(() => {
    if (message) {
      const timer = setTimeout(() => setMessage(null), 3000);
      return () => clearTimeout(timer);
    }
  }, [message]);

  // Fetch campaigns on mount
  useEffect(() => {
    const fetchData = async () => {
      try {
        const res = await axios.post(`${API_PUBLIC_URL}/api/master/campaigns_master`, { action: "list" });
        setCampaigns(res.data.data.campaigns || []);
        setPagination((prev) => ({
          ...prev,
          total: (res.data.data.campaigns || []).length,
        }));
        setLoading(false);
      } catch (error) {
        setMessage({ type: "error", text: "Failed to load data" });
        setLoading(false);
      }
    };
    fetchData();
  }, []);

  // Pagination logic
  const totalPages = Math.max(1, Math.ceil(pagination.total / pagination.rowsPerPage));
  const paginatedCampaigns = campaigns.slice(
    (pagination.page - 1) * pagination.rowsPerPage,
    pagination.page * pagination.rowsPerPage
  );

  // Toggle campaign details
  const toggleCampaignDetails = async (campaignId) => {
    const isExpanded = !expandedCampaigns[campaignId];
    setExpandedCampaigns((prev) => ({ ...prev, [campaignId]: isExpanded }));

    if (isExpanded) {
      await fetchEmailCounts(campaignId);
    }
  };

  // Fetch email counts
  const fetchEmailCounts = async (campaignId) => {
    try {
      const res = await axios.post(`${API_PUBLIC_URL}/api/master/campaigns_master`, {
        action: "email_counts",
        campaign_id: campaignId
      });
      setEmailCounts((prev) => ({
        ...prev,
        [campaignId]: res.data.data,
      }));
    } catch (error) {
      setMessage({ type: "error", text: "Failed to fetch email counts" });
    }
  };

  // Start campaign
  const startCampaign = async (campaignId) => {
    try {
      const res = await axios.post(`${API_PUBLIC_URL}/api/master/campaigns_master`, {
        action: "start_campaign",
        campaign_id: campaignId
      });

      setMessage({ type: "success", text: res.data.message });

      const listRes = await axios.post(`${API_PUBLIC_URL}/api/master/campaigns_master`, {
        action: "list",
      });
      setCampaigns(listRes.data.data.campaigns || []);
    } catch (error) {
      setMessage({
        type: "error",
        text: error.response?.data?.error || "Failed to start campaign",
      });
    }
  };

  // Pause campaign
  const pauseCampaign = async (campaignId) => {
    try {
      const res = await axios.post(`${API_PUBLIC_URL}/api/master/campaigns_master`, {
        action: "pause_campaign",
        campaign_id: campaignId,
      });

      setMessage({ type: "success", text: res.data.message });

      const listRes = await axios.post(`${API_PUBLIC_URL}/api/master/campaigns_master`, {
        action: "list",
      });
      setCampaigns(listRes.data.data.campaigns || []);
    } catch (error) {
      setMessage({
        type: "error",
        text: error.response?.data?.error || "Failed to pause campaign",
      });
    }
  };

  // Retry failed emails
  const retryFailedEmails = async (campaignId) => {
    try {
      const res = await axios.post(`${API_PUBLIC_URL}/api/master/campaigns_master`, {
        action: "retry_failed",
        campaign_id: campaignId,
      });

      setMessage({ type: "success", text: res.data.message });

      const listRes = await axios.post(`${API_PUBLIC_URL}/api/master/campaigns_master`, {
        action: "list",
      });
      setCampaigns(listRes.data.data.campaigns || []);
    } catch (error) {
      setMessage({
        type: "error",
        text: error.response?.data?.error || "Failed to retry failed emails",
      });
    }
  };

  // Status badge component
  const StatusBadge = ({ status }) => {
    const statusClass = (status || "").toLowerCase();
    const statusText = status || "Not started";

    return (
      <span className={`px-2 py-1 rounded text-xs font-semibold ${statusClass === 'running' ? 'bg-blue-500 text-white' :
        statusClass === 'paused' ? 'bg-gray-500 text-white' :
          statusClass === 'completed' ? 'bg-green-500 text-white' :
            statusClass === 'failed' ? 'bg-red-500 text-white' :
              'bg-yellow-500 text-white'
        }`}>
        {statusText}
      </span>
    );
  };

  // Status Message Popup
  const StatusMessage = ({ message, onClose }) => {
    if (!message) return null;

    return (
      <div className={`fixed top-6 left-1/2 transform -translate-x-1/2 z-50
        px-6 py-3 rounded-xl shadow text-base font-semibold
        flex items-center gap-3
        ${message.type === "error"
          ? "bg-red-200/60 border border-red-400 text-red-800"
          : "bg-green-200/60 border border-green-400 text-green-800"
        }`}
        style={{
          minWidth: 250,
          maxWidth: 400,
          boxShadow: "0 8px 32px 0 rgba(0, 0, 0, 0.23)",
          backdropFilter: "blur(8px)",
          WebkitBackdropFilter: "blur(8px)",
        }}
        role="alert"
      >
        <i className={`fas text-lg ${message.type === "error"
          ? "fa-exclamation-circle text-red-500"
          : "fa-check-circle text-green-500"
          }`}></i>
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
  };

  if (loading) {
    return (
      <div className="flex justify-center items-center h-screen">
        <div className="animate-spin rounded-full h-12 w-12 border-t-2 border-b-2 border-blue-500"></div>
      </div>
    );
  }

  return (
    <div className="bg-gray-100 min-h-screen mt-14">
      <div className="container mx-auto px-2 sm:px-4 py-6 w-full max-w-7xl">
        <StatusMessage message={message} onClose={() => setMessage(null)} />

        <div className="grid grid-cols-1 gap-4 sm:gap-6">
          {paginatedCampaigns.map((campaign) => {
            const counts = emailCounts[campaign.campaign_id] || {};

            return (
              <div
                key={campaign.campaign_id}
                className="bg-white rounded-xl shadow-md overflow-hidden transition-all hover:shadow-lg"
              >
                <div className="p-4 sm:p-6">
                  <div className="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-3">
                    <div className="flex-1 min-w-0">
                      <h2 className="text-lg sm:text-xl font-semibold text-gray-800 mb-1 break-words">
                        {campaign.description}
                      </h2>
                      <p className="text-xs sm:text-sm text-gray-600 mb-2 break-words">
                        {campaign.mail_subject}
                      </p>
                      <div className="flex flex-wrap items-center gap-2 sm:gap-4">
                        <span className="inline-flex items-center px-2.5 py-1 rounded-full bg-green-100 text-green-800 text-xs sm:text-sm font-medium">
                          <i className="fas fa-envelope mr-1"></i>
                          {Number(campaign.valid_emails)?.toLocaleString()} Emails
                        </span>
                        <StatusBadge status={campaign.campaign_status} />
                      </div>
                    </div>
                    <div className="flex flex-row flex-wrap gap-2 items-center mt-2 sm:mt-0">
                      <button
                        onClick={() => toggleCampaignDetails(campaign.campaign_id)}
                        className="text-gray-500 hover:text-gray-700 px-2 py-1 rounded-lg"
                      >
                        <i
                          className={`fas ${expandedCampaigns[campaign.campaign_id]
                            ? "fa-chevron-up"
                            : "fa-chevron-down"
                            } text-sm`}
                        ></i>
                      </button>
                      {campaign.campaign_status === "running" ? (
                        <button
                          onClick={() => pauseCampaign(campaign.campaign_id)}
                          className="px-3 sm:px-4 py-2 bg-yellow-600 hover:bg-yellow-700 text-white rounded-lg text-xs sm:text-sm font-medium"
                        >
                          <i className="fas fa-pause mr-1"></i> Pause
                        </button>
                      ) : campaign.campaign_status === "completed" ? (
                        <span className="px-3 sm:px-4 py-2 bg-gray-200 text-gray-600 rounded-lg text-xs sm:text-sm font-medium">
                          <i className="fas fa-check-circle mr-1"></i> Completed
                        </span>
                      ) : (
                        <button
                          onClick={() => startCampaign(campaign.campaign_id)}
                          className="px-3 sm:px-4 py-2 bg-green-500 hover:bg-green-700 text-white rounded-lg text-xs sm:text-sm font-medium"
                        >
                          <i className="fas fa-paper-plane mr-1"></i> Send
                        </button>
                      )}
                      {campaign.failed_emails > 0 &&
                        campaign.campaign_status !== "completed" && (
                          <button
                            onClick={() => retryFailedEmails(campaign.campaign_id)}
                            className="px-3 sm:px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white rounded-lg text-xs sm:text-sm font-medium"
                          >
                            <i className="fas fa-redo mr-1"></i> Retry Failed
                          </button>
                        )}
                    </div>
                  </div>

                  {expandedCampaigns[campaign.campaign_id] && (
                    <div className="mt-6">
                      <div className="space-y-3 mb-4">
                        <div className="flex flex-wrap gap-4">
                          <div className="bg-gray-50 rounded-lg p-3 flex flex-col items-center min-w-[120px]">
                            <span className="text-xs text-gray-500">Pending</span>
                            <span className="font-bold text-lg text-blue-700">{counts.pending || campaign.pending_emails || 0}</span>
                          </div>
                          <div className="bg-gray-50 rounded-lg p-3 flex flex-col items-center min-w-[120px]">
                            <span className="text-xs text-gray-500">Sent</span>
                            <span className="font-bold text-lg text-green-700">{counts.sent || campaign.sent_emails || 0}</span>
                          </div>
                          <div className="bg-gray-50 rounded-lg p-3 flex flex-col items-center min-w-[120px]">
                            <span className="text-xs text-gray-500">Failed</span>
                            <span className="font-bold text-lg text-red-700">{counts.failed || campaign.failed_emails || 0}</span>
                          </div>
                        </div>
                        <div className="mt-4 text-xs text-gray-500">
                          Started: {campaign.start_time ? campaign.start_time : "N/A"}<br />
                          Ended: {campaign.end_time ? campaign.end_time : "N/A"}
                        </div>
                      </div>
                    </div>
                  )}
                </div>
              </div>
            );
          })}
        </div>

        {/* Pagination Controls (only below) */}
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
      </div>
    </div>
  );
};

export default Master;