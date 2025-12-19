import axios from "../api/axios";
import React, { useState, useEffect, useMemo, useCallback } from "react";

import PageLoader from "../components/PageLoader";
import { CardSkeleton } from "../components/SkeletonLoader";
import { API_CONFIG } from "../config";

// Use centralized config for API endpoints
const API_PUBLIC_URL = API_CONFIG.API_MASTER_CAMPAIGNS;

const Master = () => {
  const [campaigns, setCampaigns] = useState([]);
  const [loading, setLoading] = useState(true);
  const [message, setMessage] = useState(null);
  const [expandedCampaigns, setExpandedCampaigns] = useState({});
  const [emailCounts, setEmailCounts] = useState({});
  const [csvLists, setCsvLists] = useState([]);
  const [campaignCsvListSelections, setCampaignCsvListSelections] = useState({});
  const [showCsvModal, setShowCsvModal] = useState(false);
  const [selectedCampaignForModal, setSelectedCampaignForModal] = useState(null);
  const [csvSearchQuery, setCsvSearchQuery] = useState('');

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

  // Fetch CSV lists
  const fetchCsvLists = useCallback(async () => {
    try {
      const res = await axios.get(API_CONFIG.GET_CSV_LIST, {
        params: { limit: 'all' }
      });
      if (res.data && res.data.data) {
        setCsvLists(res.data.data);
      }
    } catch (error) {
      console.error('Failed to fetch CSV lists:', error);
    }
  }, []);

  useEffect(() => {
    fetchCsvLists();
  }, [fetchCsvLists]);

  // Fetch campaigns function (memoized)
  const fetchData = useCallback(async () => {
    try {
      const res = await axios.post(API_PUBLIC_URL, { action: "list" });
      // campaigns_master.php returns { success: true, data: { campaigns: [...] } }
      if (!res?.data) throw new Error("No response data");
      if (res.data.success === false) throw new Error(res.data.message || "API returned failure");

      const campaigns = (res.data.data && res.data.data.campaigns) || [];
      setCampaigns(campaigns);
      setPagination((prev) => ({
        ...prev,
        total: campaigns.length,
      }));
      setLoading(false);
      return campaigns;
    } catch (error) {
      // Log the error for easier debugging in browser console
      console.error("Master.fetchData error:", error?.response || error?.message || error);
      setMessage({ type: "error", text: `Failed to load data: ${error?.response?.data?.message || error?.message || 'Unknown error'}` });
      setLoading(false);
      return [];
    }
  }, []);

  // Check if any campaigns are running (determines polling frequency)
  const hasRunningCampaigns = useMemo(() => {
    return campaigns.some(c => c.campaign_status === 'running');
  }, [campaigns]);

  // Smart polling - only poll when needed
  useEffect(() => {
    fetchData();
    
    // Adaptive polling interval
    const pollInterval = hasRunningCampaigns ? 5000 : 30000; // 5s if running, 30s if idle
    
    // Only poll when page is visible
    let interval;
    const handleVisibilityChange = () => {
      if (document.hidden) {
        clearInterval(interval);
      } else {
        fetchData();
        interval = setInterval(fetchData, pollInterval);
      }
    };

    // Initial interval
    interval = setInterval(fetchData, pollInterval);
    
    // Listen for visibility changes
    document.addEventListener('visibilitychange', handleVisibilityChange);
    
    return () => {
      clearInterval(interval);
      document.removeEventListener('visibilitychange', handleVisibilityChange);
    };
  }, [fetchData, hasRunningCampaigns]);

  // Fetch email counts (memoized) - MUST be defined before the auto-refresh useEffect
  const fetchEmailCounts = useCallback(async (campaignId) => {
    try {
      const res = await axios.post(API_PUBLIC_URL, {
        action: "email_counts",
        campaign_id: campaignId
      });
      if (res?.data?.success === false) throw new Error(res.data.message || 'Failed to get counts');
      setEmailCounts((prev) => ({
        ...prev,
        [campaignId]: res.data.data || res.data,
      }));
    } catch (err) {
      console.error('fetchEmailCounts error:', err);
      setMessage({ type: "error", text: "Failed to fetch email counts" });
    }
  }, []);

  // Auto-refresh email counts for running campaigns
  useEffect(() => {
    if (!hasRunningCampaigns) return;

    const refreshInterval = setInterval(() => {
      // Refresh counts for all expanded running campaigns
      setCampaigns(currentCampaigns => {
        setExpandedCampaigns(currentExpanded => {
          currentCampaigns.forEach(campaign => {
            if (campaign.campaign_status === 'running' && currentExpanded[campaign.campaign_id]) {
              fetchEmailCounts(campaign.campaign_id);
            }
          });
          return currentExpanded;
        });
        return currentCampaigns;
      });
    }, 3000); // Refresh every 3 seconds for live updates

    return () => clearInterval(refreshInterval);
  }, [hasRunningCampaigns, fetchEmailCounts]);

  // Pagination logic (memoized)
  const totalPages = useMemo(() => 
    Math.max(1, Math.ceil(pagination.total / pagination.rowsPerPage)),
    [pagination.total, pagination.rowsPerPage]
  );
  
  const paginatedCampaigns = useMemo(() => 
    campaigns.slice(
      (pagination.page - 1) * pagination.rowsPerPage,
      pagination.page * pagination.rowsPerPage
    ),
    [campaigns, pagination.page, pagination.rowsPerPage]
  );

  // Toggle campaign details (memoized)
  const toggleCampaignDetails = useCallback(async (campaignId) => {
    setExpandedCampaigns((prev) => {
      const isExpanded = !prev[campaignId];
      
      if (isExpanded) {
        // Fetch counts if not already fetched
        setEmailCounts(currentCounts => {
          if (!currentCounts[campaignId]) {
            fetchEmailCounts(campaignId);
          }
          return currentCounts;
        });
      }
      
      return { ...prev, [campaignId]: isExpanded };
    });
  }, [fetchEmailCounts]);

  // Start campaign (memoized)
  const startCampaign = useCallback(async (campaignId) => {
    try {
      const res = await axios.post(API_PUBLIC_URL, {
        action: "start_campaign",
        campaign_id: campaignId
      });
      if (res?.data?.success === false) throw new Error(res.data.message || 'Failed to start campaign');
      setMessage({ type: "success", text: res.data.message || 'Campaign started' });

      await fetchData();
    } catch (error) {
      setMessage({
        type: "error",
        text: error.response?.data?.error || error.message || "Failed to start campaign",
      });
    }
  }, [fetchData]);

  // Pause campaign (memoized)
  const pauseCampaign = useCallback(async (campaignId) => {
    try {
      const res = await axios.post(API_PUBLIC_URL, {
        action: "pause_campaign",
        campaign_id: campaignId,
      });
      if (res?.data?.success === false) throw new Error(res.data.message || 'Failed to pause campaign');
      setMessage({ type: "success", text: res.data.message || 'Campaign paused' });

      await fetchData();
    } catch (error) {
      setMessage({
        type: "error",
        text: error.response?.data?.error || "Failed to pause campaign",
      });
    }
  }, [fetchData]);

  // Handle CSV list selection change
  const handleCsvListChange = useCallback(async (campaignId, csvListId) => {
    try {
      // Save CSV list ID to database immediately
      await axios.post(
        `${API_CONFIG.API_CAMPAIGNS}?id=${campaignId}`,
        { csv_list_id: csvListId || null },
        { headers: { 'Content-Type': 'application/json' } }
      );
      
      // Update local state
      setCampaignCsvListSelections(prev => ({
        ...prev,
        [campaignId]: csvListId
      }));
      
      // Refresh email counts to reflect the new CSV list selection
      await fetchEmailCounts(campaignId);
      
      setMessage({ type: "success", text: "CSV list selection saved" });
    } catch (error) {
      setMessage({
        type: "error",
        text: error.response?.data?.error || "Failed to save CSV list selection"
      });
    }
  }, [fetchEmailCounts]);

  // Open CSV selection modal
  const openCsvModal = useCallback((campaignId) => {
    setSelectedCampaignForModal(campaignId);
    setCsvSearchQuery('');
    setShowCsvModal(true);
  }, []);

  // Confirm CSV list selection from modal
  const confirmCsvSelection = useCallback((csvListId) => {
    if (selectedCampaignForModal) {
      handleCsvListChange(selectedCampaignForModal, csvListId);
      setShowCsvModal(false);
      setSelectedCampaignForModal(null);
    }
  }, [selectedCampaignForModal, handleCsvListChange]);

  // Get selected CSV list details
  const getSelectedCsvList = useCallback((campaignId) => {
    const csvListId = campaignCsvListSelections[campaignId];
    if (!csvListId) return null;
    return csvLists.find(list => list.id === parseInt(csvListId));
  }, [campaignCsvListSelections, csvLists]);

  // Initialize CSV list selections from campaigns
  useEffect(() => {
    const selections = {};
    campaigns.forEach(campaign => {
      if (campaign.csv_list_id) {
        selections[campaign.campaign_id] = campaign.csv_list_id;
      }
    });
    setCampaignCsvListSelections(selections);
  }, [campaigns]);

  return (
    <div className="bg-gray-100 min-h-screen mt-14">      
      {loading && (
        <div className="container mx-auto px-2 sm:px-4 py-6 w-full max-w-7xl">
          <div className="grid grid-cols-1 gap-4 sm:gap-6">
            <CardSkeleton cards={3} />
          </div>
        </div>
      )}
      {!loading && (
      <div className="container mx-auto px-2 sm:px-4 py-6 w-full max-w-7xl">
        <StatusMessage message={message} onClose={() => setMessage(null)} />

        <div className="grid grid-cols-1 gap-4 sm:gap-6">
          {paginatedCampaigns.map((campaign) => {
            const counts = emailCounts[campaign.campaign_id] || {};
            const selectedCsvList = getSelectedCsvList(campaign.campaign_id);
            const emailCount = selectedCsvList 
              ? (selectedCsvList.valid_count || 0) 
              : Number(campaign.valid_emails || 0);

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
                          {emailCount.toLocaleString()} {selectedCsvList ? 'Valid' : ''} Emails
                        </span>
                        {selectedCsvList && (
                          <span className="inline-flex items-center px-2.5 py-1 rounded-full bg-blue-100 text-blue-800 text-xs sm:text-sm font-medium">
                            <i className="fas fa-list mr-1"></i>
                            {selectedCsvList.list_name}
                          </span>
                        )}
                        {campaign.csv_list_name && !selectedCsvList && (
                          <span className="inline-flex items-center px-2.5 py-1 rounded-full bg-blue-100 text-blue-800 text-xs sm:text-sm font-medium">
                            <i className="fas fa-list mr-1"></i>
                            {campaign.csv_list_name}
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
                        <>
                          <button
                            onClick={() => openCsvModal(campaign.campaign_id)}
                            className="px-3 py-2 border border-gray-300 rounded-lg text-xs bg-white hover:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-500 transition-colors min-w-[180px] text-left"
                          >
                            {(() => {
                              const selected = getSelectedCsvList(campaign.campaign_id);
                              return selected ? (
                                <div className="flex flex-col">
                                  <span className="font-medium text-gray-900">{selected.list_name}</span>
                                  <span className="text-xs text-gray-500">{campaign.csv_list_valid_count || selected.valid_count || 0} valid emails</span>
                                </div>
                              ) : (
                                <span className="text-gray-600"><i className="fas fa-list mr-1"></i>Select CSV List</span>
                              );
                            })()}
                          </button>
                          <button
                            onClick={() => startCampaign(campaign.campaign_id)}
                            className="px-3 sm:px-4 py-2 bg-green-500 hover:bg-green-700 text-white rounded-lg text-xs sm:text-sm font-medium"
                          >
                            <i className="fas fa-paper-plane mr-1"></i> Send
                          </button>
                        </>
                      )}
                    </div>
                  </div>

                  {expandedCampaigns[campaign.campaign_id] && (
                    <div className="mt-6">
                      <div className="space-y-3 mb-4">                                                                                              
                        <div className="flex flex-wrap gap-4">                                                                                                                                              
                          <div className="bg-gray-50 rounded-lg p-3 flex flex-col items-center min-w-[120px]">
                            <span className="text-xs text-gray-500">Pending</span>                                                                        
                            <span className="font-bold text-lg text-blue-700">
                              {counts.pending !== undefined ? counts.pending : (selectedCsvList ? 0 : (campaign.pending_emails || 0))}
                            </span>
                          </div>
                          <div className="bg-gray-50 rounded-lg p-3 flex flex-col items-center min-w-[120px]">
                            <span className="text-xs text-gray-500">Sent</span>
                            <span className="font-bold text-lg text-green-700">
                              {counts.sent !== undefined ? counts.sent : (selectedCsvList ? 0 : (campaign.sent_emails || 0))}
                            </span>
                          </div>
                          <div className="bg-gray-50 rounded-lg p-3 flex flex-col items-center min-w-[120px]">
                            <span className="text-xs text-gray-500">Failed</span>
                            <span className="font-bold text-lg text-red-700">
                              {counts.failed !== undefined ? counts.failed : (selectedCsvList ? 0 : (campaign.failed_emails || 0))}
                            </span>
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
      )}

      {/* CSV List Selection Modal with Search */}
      {showCsvModal && (
        <div className="fixed inset-0 bg-black/50 backdrop-blur-sm flex items-center justify-center z-50 p-4">
          <div className="bg-white rounded-xl shadow-2xl max-w-2xl w-full max-h-[80vh] flex flex-col">
            {/* Modal Header */}
            <div className="px-6 py-4 border-b border-gray-200">
              <div className="flex justify-between items-center mb-3">
                <h3 className="text-xl font-semibold text-gray-900 flex items-center">
                  <i className="fas fa-list-alt text-blue-600 mr-2"></i>
                  Select CSV List for Campaign
                </h3>
                <button
                  onClick={() => setShowCsvModal(false)}
                  className="text-gray-400 hover:text-gray-500 p-2 rounded-lg hover:bg-gray-100 transition-colors"
                >
                  <i className="fas fa-times text-lg"></i>
                </button>
              </div>
              {/* Search Input */}
              <div className="relative">
                <i className="fas fa-search absolute left-3 top-1/2 transform -translate-y-1/2 text-gray-400"></i>
                <input
                  type="text"
                  placeholder="Search CSV lists..."
                  value={csvSearchQuery}
                  onChange={(e) => setCsvSearchQuery(e.target.value)}
                  className="w-full pl-10 pr-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-colors"
                  autoFocus
                />
              </div>
            </div>

            {/* CSV Lists - Scrollable */}
            <div className="flex-1 overflow-y-auto px-6 py-4">
              {/* All Recipients Option */}
              <button
                onClick={() => confirmCsvSelection('')}
                className={`w-full text-left px-4 py-3 rounded-lg border-2 mb-2 transition-all hover:border-blue-500 hover:bg-blue-50 ${
                  !campaignCsvListSelections[selectedCampaignForModal]
                    ? 'border-blue-500 bg-blue-50'
                    : 'border-gray-200 bg-white'
                }`}
              >
                <div className="flex items-center justify-between">
                  <div>
                    <div className="font-semibold text-gray-900 flex items-center">
                      <i className="fas fa-globe mr-2 text-blue-600"></i>
                      All Recipients
                    </div>
                    <div className="text-sm text-gray-600 mt-1">
                      Send to all valid emails in the system
                    </div>
                  </div>
                  {!campaignCsvListSelections[selectedCampaignForModal] && (
                    <i className="fas fa-check-circle text-blue-600 text-xl"></i>
                  )}
                </div>
              </button>

              {/* Filtered CSV Lists */}
              {csvLists
                .filter(list => 
                  list.list_name.toLowerCase().includes(csvSearchQuery.toLowerCase())
                )
                .map((list) => (
                  <button
                    key={list.id}
                    onClick={() => confirmCsvSelection(list.id)}
                    className={`w-full text-left px-4 py-3 rounded-lg border-2 mb-2 transition-all hover:border-blue-500 hover:bg-blue-50 ${
                      campaignCsvListSelections[selectedCampaignForModal] === list.id.toString()
                        ? 'border-blue-500 bg-blue-50'
                        : 'border-gray-200 bg-white'
                    }`}
                  >
                    <div className="flex items-center justify-between">
                      <div className="flex-1">
                        <div className="font-semibold text-gray-900 flex items-center">
                          <i className="fas fa-file-csv mr-2 text-green-600"></i>
                          {list.list_name}
                        </div>
                        <div className="text-sm text-gray-600 mt-1 flex items-center gap-4">
                          <span className="flex items-center">
                            <i className="fas fa-check-circle text-green-600 mr-1"></i>
                            {list.valid_count || 0} valid emails
                          </span>
                          <span className="flex items-center text-gray-400">
                            <i className="fas fa-envelope mr-1"></i>
                            {list.total_count || 0} total
                          </span>
                        </div>
                      </div>
                      {campaignCsvListSelections[selectedCampaignForModal] === list.id.toString() && (
                        <i className="fas fa-check-circle text-blue-600 text-xl"></i>
                      )}
                    </div>
                  </button>
                ))}

              {/* No Results */}
              {csvSearchQuery && csvLists.filter(list => 
                list.list_name.toLowerCase().includes(csvSearchQuery.toLowerCase())
              ).length === 0 && (
                <div className="text-center py-8 text-gray-500">
                  <i className="fas fa-search text-4xl mb-2"></i>
                  <p>No CSV lists found matching "{csvSearchQuery}"</p>
                </div>
              )}
            </div>

            {/* Modal Footer */}
            <div className="px-6 py-4 border-t border-gray-200 bg-gray-50 rounded-b-xl">
              <div className="flex justify-between items-center">
                <div className="text-sm text-gray-600">
                  <i className="fas fa-info-circle mr-1"></i>
                  {csvLists.length} CSV list{csvLists.length !== 1 ? 's' : ''} available
                </div>
                <button
                  onClick={() => setShowCsvModal(false)}
                  className="px-4 py-2 border border-gray-300 text-gray-700 rounded-lg hover:bg-white transition-colors font-medium"
                >
                  Close
                </button>
              </div>
            </div>
          </div>
        </div>
      )}
    </div>
  );
};// Memoized status badge component
const StatusBadge = React.memo(({ status }) => {
  const statusClass = (status || "pending").toLowerCase();
  const statusColors = {
    'pending': 'bg-yellow-500',
    'running': 'bg-blue-500',
    'paused': 'bg-gray-500',
    'completed': 'bg-green-500',
    'failed': 'bg-red-500'
  };
  const statusText = status || "Pending";

  return (
    <span className={`px-2 py-1 rounded text-xs font-semibold text-white ${statusColors[statusClass] || 'bg-yellow-500'}`}>
      {statusText}
    </span>
  );
});

// Memoized status message popup
const StatusMessage = React.memo(({ message, onClose }) => {
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
});

StatusBadge.displayName = 'StatusBadge';
StatusMessage.displayName = 'StatusMessage';

export default Master;