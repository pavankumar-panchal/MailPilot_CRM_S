import React, { useState, useEffect } from "react";
import axios from "axios";

const API_PUBLIC_URL = "http://localhost/Verify_email/backend/public";

const Master = () => {
  const [campaigns, setCampaigns] = useState([]);
  const [smtpServers, setSmtpServers] = useState([]);
  const [loading, setLoading] = useState(true);
  const [message, setMessage] = useState(null);
  const [expandedCampaigns, setExpandedCampaigns] = useState({});
  const [distributions, setDistributions] = useState({});
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

  // Fetch campaigns and SMTP servers on mount
  useEffect(() => {
    const fetchData = async () => {
      try {
        const res = await axios.post(`${API_PUBLIC_URL}/campaigns_master.php`, {
          action: "list",
        });
        setCampaigns(res.data.data.campaigns || []);
        setSmtpServers(res.data.data.smtp_servers || []);
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
      await fetchDistributions(campaignId);
    }
  };

  // Fetch email counts
  const fetchEmailCounts = async (campaignId) => {
    try {
      const res = await axios.post("http://localhost/Verify_email/backend/public/campaigns_master.php", {
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

  // Fetch distributions
  const fetchDistributions = async (campaignId) => {
    try {
      const res = await axios.post("http://localhost/Verify_email/backend/public/campaigns_master.php", {
        action: "get_distribution",
        campaign_id: campaignId
      });
      setDistributions((prev) => ({
        ...prev,
        [campaignId]: res.data.data,
      }));
    } catch (error) {
      setMessage({ type: "error", text: "Failed to fetch distributions" });
    }
  };

  // Add distribution row
  const addDistribution = (campaignId) => {
    const campaign = campaigns.find((c) => c.campaign_id === campaignId);
    if (!campaign) return;

    const currentTotal = (distributions[campaignId] || []).reduce(
      (sum, d) => sum + (parseFloat(d.percentage) || 0), 0
    );
    const availablePercentage = 100 - currentTotal;

    if (availablePercentage <= 0) {
      setMessage({
        type: "error",
        text: "You have already allocated 100% of emails",
      });
      return;
    }

    if (!smtpServers.length) {
      setMessage({ type: "error", text: "No SMTP servers available" });
      return;
    }

    setDistributions((prev) => ({
      ...prev,
      [campaignId]: [
        ...(prev[campaignId] || []),
        {
          smtp_id: smtpServers[0]?.id || "",
          percentage: Math.min(10, availablePercentage).toFixed(1),
          email_count: Math.floor(campaign.valid_emails * Math.min(10, availablePercentage) / 100),
        },
      ],
    }));
  };

  // Remove distribution row
  const removeDistribution = (campaignId, index) => {
    setDistributions((prev) => ({
      ...prev,
      [campaignId]: prev[campaignId].filter((_, i) => i !== index),
    }));
  };

  // Update distribution field
  const updateDistribution = (campaignId, index, field, value) => {
    const campaign = campaigns.find((c) => c.campaign_id === campaignId);
    if (!campaign) return;

    setDistributions((prev) => {
      const newDistributions = prev[campaignId].map((item, i) =>
        i === index ? { ...item, [field]: value } : item
      );

      // Recalculate email counts if percentage changed
      if (field === "percentage") {
        let pct = parseFloat(value) || 0;
        if (pct < 1) pct = 1;
        const currentTotal = newDistributions.reduce(
          (sum, d, idx) => sum + (idx === index ? 0 : (parseFloat(d.percentage) || 0)),
          0
        );
        const maxAllowed = 100 - currentTotal;
        if (pct > maxAllowed) pct = maxAllowed;
        newDistributions[index].percentage = pct;
        newDistributions[index].email_count = Math.floor(
          campaign.valid_emails * pct / 100
        );
      }

      return {
        ...prev,
        [campaignId]: newDistributions,
      };
    });
  };

  // Calculate remaining percentage for a campaign
  const getRemainingPercentage = (campaignId) => {
    const campaign = campaigns.find((c) => c.campaign_id === campaignId);
    if (!campaign) return 0;

    // If distributions are loaded, use them; otherwise, use backend value
    if (distributions[campaignId] && distributions[campaignId].length > 0) {
      const currentTotal = distributions[campaignId].reduce(
        (sum, d) => sum + (parseFloat(d.percentage) || 0),
        0
      );
      return Math.max(0, 100 - currentTotal);
    } else {
      // Use backend value as fallback
      return Math.max(0, 100 - (parseFloat(campaign.distributed_percentage) || 0));
    }
  };

  // Save distribution
  const saveDistribution = async (campaignId) => {
    try {
      const total = (distributions[campaignId] || []).reduce(
        (sum, d) => sum + (parseFloat(d.percentage) || 0),
        0
      );

      if (total > 100) {
        setMessage({
          type: "error",
          text: `Total distribution percentage cannot exceed 100% (Current: ${total.toFixed(1)}%)`,
        });
        return;
      }

      // Check SMTP limits
      const campaign = campaigns.find((c) => c.campaign_id === campaignId);
      const overLimit = (distributions[campaignId] || []).some((dist) => {
        const smtp = smtpServers.find((s) => String(s.id) === String(dist.smtp_id));
        const emailCount = Math.floor(
          campaign.valid_emails * (parseFloat(dist.percentage) || 0) / 100
        );
        return smtp && emailCount > smtp.daily_limit;
      });

      if (overLimit) {
        setMessage({
          type: "error",
          text: "One or more SMTP distributions exceed daily limits. Please adjust percentages.",
        });
        return;
      }

      // Save
      const res = await axios.post(`${API_PUBLIC_URL}/campaigns_master.php`, {
        action: "distribute",
        campaign_id: campaignId,
        distribution: distributions[campaignId] // <-- FIX: use distributions state directly
      });

      setMessage({ type: "success", text: res.data.message });

      // Refresh campaigns to get updated data
      const listRes = await axios.post(`${API_PUBLIC_URL}/campaigns_master.php`, {
        action: "list",
      });
      setCampaigns(listRes.data.data.campaigns || []);
    } catch (error) {
      setMessage({
        type: "error",
        text: error.response?.data?.error || "Failed to save distribution",
      });
    }
  };

  // Auto-distribute
  const autoDistribute = async (campaignId) => {
    try {
      const res = await axios.post(`${API_PUBLIC_URL}/campaigns_master.php`, {
        action: "auto_distribute",
        campaign_id: campaignId,
      });

      setMessage({ type: "success", text: res.data.message });

      // Refresh data
      await fetchDistributions(campaignId);
      const listRes = await axios.post(`${API_PUBLIC_URL}/campaigns_master.php`, {
        action: "list",
      });
      setCampaigns(listRes.data.data.campaigns || []);
    } catch (error) {
      setMessage({
        type: "error",
        text: error.response?.data?.error || "Failed to auto-distribute",
      });
    }
  };

  // Start campaign
  const startCampaign = async (campaignId) => {
    try {
      const res = await axios.post(`${API_PUBLIC_URL}/campaigns_master.php`, {
        action: "start_campaign",
        campaign_id: campaignId
      });

      setMessage({ type: "success", text: res.data.message });

      // Refresh campaigns
      const listRes = await axios.post(`${API_PUBLIC_URL}/campaigns_master.php`, {
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
      const res = await axios.post(`${API_PUBLIC_URL}/campaigns_master.php`, {
        action: "pause_campaign",
        campaign_id: campaignId,
      });

      setMessage({ type: "success", text: res.data.message });

      // Refresh campaigns
      const listRes = await axios.post(`${API_PUBLIC_URL}/campaigns_master.php`, {
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
      const res = await axios.post(`${API_PUBLIC_URL}/campaigns_master.php`, {
        action: "retry_failed",
        campaign_id: campaignId,
      });

      setMessage({ type: "success", text: res.data.message });

      // Refresh campaigns
      const listRes = await axios.post(`${API_PUBLIC_URL}/campaigns_master.php`, {
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
            const remainingPercentage = getRemainingPercentage(campaign.campaign_id);
            const campaignDistributions = distributions[campaign.campaign_id] || [];
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
                        {remainingPercentage > 0 ? (
                          <span className="inline-flex items-center px-2.5 py-1 rounded-full bg-yellow-100 text-yellow-800 text-xs sm:text-sm font-medium">
                            <i className="fas fa-clock mr-1"></i>
                            {remainingPercentage}% Remaining
                          </span>
                        ) : (
                          <span className="inline-flex items-center px-2.5 py-1 rounded-full bg-blue-100 text-blue-800 text-xs sm:text-sm font-medium">
                            <i className="fas fa-check-circle mr-1"></i>
                            Fully Allocated
                          </span>
                        )}
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
                      <button
                        onClick={() => autoDistribute(campaign.campaign_id)}
                        className="px-3 sm:px-4 py-2 bg-purple-600 hover:bg-purple-700 text-white rounded-lg text-xs sm:text-sm font-medium transition-colors"
                      >
                        <i className="fas fa-magic mr-1"></i> Auto-Distribute
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
                          className="px-3 sm:px-4 py-2 bg-green-600 hover:bg-green-700 text-white rounded-lg text-xs sm:text-sm font-medium"
                        >
                          <i className="fas fa-play mr-1"></i> Start
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
                        {campaignDistributions.map((dist, index) => {
                          const smtp = smtpServers.find((s) => String(s.id) === String(dist.smtp_id));
                          const validEmails = Number(campaign.valid_emails) || 0;
                          const percentage = Number(dist.percentage) || 0;
                          const emailCount = Math.floor(validEmails * (percentage / 100));

                          let badgeClass = "bg-gray-200 text-gray-800";
                          let badgeMsg = "";

                          if (smtp) {
                            if (emailCount > smtp.daily_limit) {
                              badgeClass = "bg-red-100 text-red-800";
                              badgeMsg = (
                                <>
                                  {" "}
                                  <i className="fas fa-exclamation-triangle"></i> Exceeds daily limit
                                </>
                              );
                            } else if (emailCount > smtp.hourly_limit * 24) {
                              badgeClass = "bg-yellow-100 text-yellow-800";
                              badgeMsg = (
                                <>
                                  {" "}
                                  <i className="fas fa-exclamation-circle"></i> Review hourly limit
                                </>
                              );
                            }
                          }

                          // Calculate max for this row: its current value + remaining
                          const currentTotal = campaignDistributions.reduce(
                            (sum, d, idx) => sum + (idx === index ? 0 : (parseFloat(d.percentage) || 0)), 0
                          );
                          const maxAllowed = 100 - currentTotal;

                          return (
                            <div
                              key={index}
                              className="flex items-center space-x-4 p-3 bg-gray-50 rounded-lg"
                            >
                              <select
                                value={dist.smtp_id}
                                onChange={(e) =>
                                  updateDistribution(
                                    campaign.campaign_id,
                                    index,
                                    "smtp_id",
                                    e.target.value
                                  )
                                }
                                className="flex-1 min-w-0 text-sm border border-gray-300 rounded-lg px-3 py-2 focus:ring-blue-500 focus:border-blue-500"
                              >
                                {smtpServers.length === 0 ? (
                                  <option value="">No SMTP servers available</option>
                                ) : (
                                  smtpServers.map((server) => (
                                    <option key={server.id} value={server.id}>
                                      {server.name} ({server.daily_limit.toLocaleString()}/day)
                                    </option>
                                  ))
                                )}
                              </select>

                              <div className="relative w-32">
                                <input
                                  type="number"
                                  min="1"
                                  max={maxAllowed}
                                  step="0.1"
                                  value={dist.percentage}
                                  onChange={(e) => {
                                    let val = e.target.value;
                                    if (val === "") val = "";
                                    else if (parseFloat(val) < 1) val = 1;
                                    else if (parseFloat(val) > maxAllowed) val = maxAllowed;
                                    updateDistribution(
                                      campaign.campaign_id,
                                      index,
                                      "percentage",
                                      val
                                    );
                                  }}
                                  className="text-sm border border-gray-300 rounded-lg px-3 py-2 pr-8 w-full focus:ring-blue-500 focus:border-blue-500"
                                />
                                <span className="absolute right-3 top-1/2 transform -translate-y-1/2 text-xs text-gray-500">
                                  %
                                </span>
                              </div>

                              <div className="flex items-center space-x-2">
                                <span
                                  className={`email-count ${badgeClass} text-xs font-medium px-2.5 py-0.5 rounded-full`}
                                >
                                  ~{emailCount.toLocaleString()} emails
                                  {badgeMsg}
                                </span>
                                <button
                                  type="button"
                                  className="text-red-500 hover:text-red-700"
                                  onClick={() => removeDistribution(campaign.campaign_id, index)}
                                >
                                  <i className="fas fa-trash-alt"></i>
                                </button>
                              </div>
                            </div>
                          );
                        })}
                      </div>

                      <div className="flex justify-between items-center">
                        <button
                          type="button"
                          disabled={remainingPercentage <= 0}
                          onClick={() => addDistribution(campaign.campaign_id)}
                          className={`px-3 py-1.5 bg-white border border-gray-300 rounded-lg text-sm font-medium text-gray-700 hover:bg-gray-50
                            ${remainingPercentage <= 0 ? "opacity-50 cursor-not-allowed" : ""}
                          `}
                        >
                          <i className="fas fa-plus mr-1"></i> Add SMTP Server
                        </button>

                        <div className="flex space-x-3">
                          <span className="text-sm text-gray-600">
                            {remainingPercentage > 0 ? (
                              <>
                                <i className="fas fa-info-circle text-blue-500 mr-1"></i>
                                {remainingPercentage}% remaining to allocate
                              </>
                            ) : (
                              <>
                                <i className="fas fa-check-circle text-green-500 mr-1"></i>
                                Fully allocated
                              </>
                            )}
                          </span>
                          <button
                            type="button"
                            onClick={() => saveDistribution(campaign.campaign_id)}
                            className="px-4 py-1.5 bg-blue-600 hover:bg-blue-700 text-white rounded-lg text-sm font-medium"
                          >
                            <i className="fas fa-save mr-1"></i> Save Distribution
                          </button>
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