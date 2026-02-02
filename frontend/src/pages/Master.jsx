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
  const [showTemplatePreview, setShowTemplatePreview] = useState(false);
  const [templatePreviewData, setTemplatePreviewData] = useState(null);
  const [loadingPreview, setLoadingPreview] = useState(false);
  const [currentEmailIndex, setCurrentEmailIndex] = useState(0);
  const [pageNumberInput, setPageNumberInput] = useState('');

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
      console.log('🔄 Fetching CSV lists from:', API_CONFIG.GET_CSV_LIST);
      const res = await axios.get(API_CONFIG.GET_CSV_LIST, {
        params: { limit: 'all' }
      });
      console.log('✅ CSV Lists API Response:', res.data);
      if (res.data?.debug) {
        console.log('🔍 Debug Info:', res.data.debug);
      }
      if (res.data && res.data.success && Array.isArray(res.data.data)) {
        console.log('📋 Setting CSV lists:', res.data.data.length, 'lists found');
        if (res.data.data.length > 0) {
          console.log('📄 First list:', res.data.data[0]);
        }
        setCsvLists(res.data.data);
      } else {
        console.warn('⚠️ Unexpected CSV list response format:', res.data);
        setCsvLists([]);
      }
    } catch (error) {
      console.error('❌ Failed to fetch CSV lists:', error);
      console.error('🔍 Error details:', {
        message: error.message,
        response: error.response?.data,
        status: error.response?.status,
        url: error.config?.url
      });
      setCsvLists([]);
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
      console.log('🔄 Saving CSV list selection:', { campaignId, csvListId });
      const url = `${API_CONFIG.API_CAMPAIGNS}&id=${campaignId}`;
      console.log('📡 Request URL:', url);
      console.log('📦 Request body:', { csv_list_id: csvListId || null });
      
      // Save CSV list ID to database immediately
      const response = await axios.post(
        url,
        { csv_list_id: csvListId || null },
        { headers: { 'Content-Type': 'application/json' } }
      );
      
      console.log('✅ CSV list save response:', response.data);
      
      // Update local state
      setCampaignCsvListSelections(prev => ({
        ...prev,
        [campaignId]: csvListId
      }));
      
      // Refresh campaigns to get updated csv_list_valid_count
      await fetchData();
      
      // Refresh email counts to reflect the new CSV list selection
      await fetchEmailCounts(campaignId);
      
      setMessage({ type: "success", text: "CSV list selection saved" });
    } catch (error) {
      console.error('❌ Failed to save CSV list:', error);
      console.error('🔍 Error response:', error.response?.data);
      setMessage({
        type: "error",
        text: error.response?.data?.message || error.response?.data?.error || "Failed to save CSV list selection"
      });
    }
  }, [fetchEmailCounts, fetchData]);

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

  // Show template preview
  const showTemplatePreviewModal = useCallback(async (campaignId, emailIndex = 0) => {
    setLoadingPreview(true);
    setShowTemplatePreview(true);
    setCurrentEmailIndex(emailIndex);
    try {
      const res = await axios.post(API_PUBLIC_URL, {
        action: 'get_template_preview',
        campaign_id: campaignId,
        email_index: emailIndex
      });
      if (res.data.success) {
        console.log('Template preview data received:', res.data.data);
        console.log('Current email data:', res.data.data.current_email);
        setTemplatePreviewData(res.data.data);
      } else {
        setMessage({ type: 'error', text: res.data.message || 'Failed to load template preview' });
        setShowTemplatePreview(false);
      }
    } catch (error) {
      console.error('Template preview error:', error);
      setMessage({ type: 'error', text: 'Failed to load template preview' });
      setShowTemplatePreview(false);
    } finally {
      setLoadingPreview(false);
    }
  }, []);

  // Navigate to different email in preview
  const changePreviewEmail = useCallback((campaignId, newIndex) => {
    showTemplatePreviewModal(campaignId, newIndex);
  }, [showTemplatePreviewModal]);

  // Close template preview
  const closeTemplatePreview = useCallback(() => {
    setShowTemplatePreview(false);
    setTemplatePreviewData(null);
    setCurrentEmailIndex(0);
    setPageNumberInput('');
  }, []);

  // Handle page number input change
  const handlePageNumberChange = useCallback((e) => {
    const value = e.target.value;
    // Only allow numbers
    if (value === '' || /^\d+$/.test(value)) {
      setPageNumberInput(value);
    }
  }, []);

  // Go to specific page number
  const goToPageNumber = useCallback(() => {
    if (!templatePreviewData || !pageNumberInput) return;
    
    const pageNum = parseInt(pageNumberInput);
    const totalPages = templatePreviewData.total_emails;
    
    if (pageNum >= 1 && pageNum <= totalPages) {
      const newIndex = pageNum - 1; // Convert to 0-based index
      changePreviewEmail(templatePreviewData.campaign_id, newIndex);
      setPageNumberInput(''); // Clear input after navigation
    } else {
      setMessage({ 
        type: 'error', 
        text: `Please enter a valid page number between 1 and ${totalPages.toLocaleString()}` 
      });
    }
  }, [templatePreviewData, pageNumberInput, changePreviewEmail]);

  // Handle Enter key in page number input
  const handlePageNumberKeyPress = useCallback((e) => {
    if (e.key === 'Enter') {
      goToPageNumber();
    }
  }, [goToPageNumber]);

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
    <div className="min-h-screen bg-gradient-to-br from-blue-50 via-white to-purple-50">
      <div className="container mx-auto px-2 sm:px-4 py-4 sm:py-6 lg:py-8 max-w-7xl">      
        {loading && (
          <div className="grid grid-cols-1 gap-3 sm:gap-4">
            <CardSkeleton cards={3} />
          </div>
        )}
        {!loading && (
        <>
          <StatusMessage message={message} onClose={() => setMessage(null)} />

          {/* Master Control Section */}
          <div className="glass-effect rounded-xl shadow-xl border border-white/20 p-5 sm:p-6 lg:p-8 mb-5 sm:mb-6 hover:shadow-2xl transition-all duration-300">
            <div className="flex items-center gap-3 mb-6">
              <div className="bg-gradient-to-br from-blue-500 to-indigo-600 p-3 rounded-xl shadow-lg">
                <svg className="w-6 h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M9 3v2m6-2v2M9 19v2m6-2v2M5 9H3m2 6H3m18-6h-2m2 6h-2M7 19h10a2 2 0 002-2V7a2 2 0 00-2-2H7a2 2 0 00-2 2v10a2 2 0 002 2zM9 9h6v6H9V9z" />
                </svg>
              </div>
              <h2 className="text-lg sm:text-xl font-bold text-gray-800">Master Control</h2>
            </div>

          <div className="grid grid-cols-1 gap-3 sm:gap-4">
          {paginatedCampaigns.map((campaign) => {
            const counts = emailCounts[campaign.campaign_id] || {};
            const selectedCsvList = getSelectedCsvList(campaign.campaign_id);
            const isTemplateImport = campaign.import_batch_id || campaign.email_source === 'imported_recipients';
            
            // Determine email count based on source
            let emailCount;
            if (campaign.csv_list_valid_count !== undefined && campaign.csv_list_valid_count !== null) {
              emailCount = Number(campaign.csv_list_valid_count);
            } else if (selectedCsvList?.valid_count) {
              emailCount = Number(selectedCsvList.valid_count);
            } else {
              emailCount = Number(campaign.valid_emails || 0);
            }

            return (
              <div
                key={campaign.campaign_id}
                className="glass-effect rounded-xl shadow-lg overflow-hidden transition-all hover:shadow-xl"
              >
                <div className="p-4 sm:p-5 lg:p-6">
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
                          {emailCount.toLocaleString()} Emails
                        </span>
                        {isTemplateImport && (
                          <>
                            <span className="inline-flex items-center px-2.5 py-1 rounded-full bg-purple-100 text-purple-800 text-xs sm:text-sm font-medium">
                              <i className="fas fa-file-excel mr-1"></i>
                              Excel Import
                            </span>
                            {campaign.template_id && (
                              <button
                                onClick={() => showTemplatePreviewModal(campaign.campaign_id)}
                                className="inline-flex items-center px-2.5 py-1 rounded-full bg-indigo-100 text-indigo-800 text-xs sm:text-sm font-medium hover:bg-indigo-200 transition-colors"
                              >
                                <i className="fas fa-eye mr-1"></i>
                                Preview Template
                              </button>
                            )}
                          </>
                        )}
                        {!isTemplateImport && selectedCsvList && (
                          <span className="inline-flex items-center px-2.5 py-1 rounded-full bg-blue-100 text-blue-800 text-xs sm:text-sm font-medium">
                            <i className="fas fa-list mr-1"></i>
                            {selectedCsvList.list_name}
                          </span>
                        )}
                        {!isTemplateImport && campaign.csv_list_name && !selectedCsvList && (
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
                          {isTemplateImport ? (
                            <div className="px-3 py-2 border border-gray-300 rounded-lg text-xs bg-gray-50 min-w-[180px] text-left opacity-60 cursor-not-allowed">
                              <div className="flex items-center text-gray-500">
                                <i className="fas fa-lock mr-2"></i>
                                <span>Using Imported Emails</span>
                              </div>
                            </div>
                          ) : (
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
                          )}
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
                              {counts.pending !== undefined ? counts.pending : (campaign.pending_emails || 0)}
                            </span>
                          </div>
                          <div className="bg-gray-50 rounded-lg p-3 flex flex-col items-center min-w-[120px]">
                            <span className="text-xs text-gray-500">Sent</span>
                            <span className="font-bold text-lg text-green-700">
                              {counts.sent !== undefined ? counts.sent : (campaign.sent_emails || 0)}
                            </span>
                          </div>
                          <div className="bg-gray-50 rounded-lg p-3 flex flex-col items-center min-w-[120px]">
                            <span className="text-xs text-gray-500">Failed</span>
                            <span className="font-bold text-lg text-red-700">
                              {counts.failed !== undefined ? counts.failed : (campaign.failed_emails || 0)}
                            </span>
                          </div>
                          {(campaign.retryable_count > 0 || (counts.retryable !== undefined && counts.retryable > 0)) && (
                            <div className="bg-orange-50 rounded-lg p-3 flex flex-col items-center min-w-[120px]">
                              <span className="text-xs text-orange-600">Retryable</span>
                              <span className="font-bold text-lg text-orange-700">
                                {counts.retryable !== undefined ? counts.retryable : (campaign.retryable_count || 0)}
                              </span>
                            </div>
                          )}
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

      {/* CSV List Selection Modal with Search */}
      {showCsvModal && (
        <div className="fixed inset-0 bg-black/50 backdrop-blur-sm flex items-center justify-center z-50 p-2 sm:p-4">
          <div className="bg-white rounded-lg sm:rounded-xl shadow-2xl max-w-2xl w-full max-h-[90vh] flex flex-col">
            {/* Modal Header */}
            <div className="px-3 sm:px-6 py-3 sm:py-4 border-b border-gray-200">
              <div className="flex justify-between items-center mb-3">
                <h3 className="text-base sm:text-xl font-semibold text-gray-900 flex items-center">
                  <i className="fas fa-list-alt text-blue-600 mr-2 text-sm sm:text-base"></i>
                  <span className="hidden sm:inline">Select CSV List for Campaign</span>
                  <span className="sm:hidden">Select CSV List</span>
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
            <div className="flex-1 overflow-y-auto px-3 sm:px-6 py-3 sm:py-4">
              {/* All Recipients Option */}
              <button
                onClick={() => confirmCsvSelection('')}
                className={`w-full text-left px-3 sm:px-4 py-3 sm:py-4 rounded-lg border-2 mb-2 transition-all hover:border-blue-500 hover:bg-blue-50 touch-manipulation ${
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
              
              {/* Empty State - No CSV Lists */}
              {!csvSearchQuery && csvLists.length === 0 && (
                <div className="text-center py-12 text-gray-500">
                  <div className="mb-4">
                    <i className="fas fa-inbox text-6xl text-gray-300"></i>
                  </div>
                  <h4 className="text-lg font-semibold text-gray-700 mb-2">
                    No CSV Lists Found
                  </h4>
                  <p className="text-sm text-gray-600">
                    Please check the Email Verification page to see your uploaded lists.
                  </p>
                </div>
              )}
            </div>

            {/* Modal Footer */}
            <div className="px-3 sm:px-6 py-3 sm:py-4 border-t border-gray-200 bg-gray-50 rounded-b-xl">
              <div className="flex flex-col sm:flex-row justify-between items-stretch sm:items-center gap-3">
                <div className="text-xs sm:text-sm text-gray-600 text-center sm:text-left order-2 sm:order-1">
                  <i className="fas fa-info-circle mr-1"></i>
                  {csvLists.length > 0 
                    ? `${csvLists.length} CSV list${csvLists.length !== 1 ? 's' : ''} available`
                    : 'No CSV lists available'}
                </div>
                <button
                  onClick={() => setShowCsvModal(false)}
                  className="w-full sm:w-auto px-4 py-2.5 sm:py-2 border border-gray-300 text-gray-700 rounded-lg hover:bg-gray-50 transition-colors font-medium order-1 sm:order-2"
                >
                  <i className="fas fa-times mr-2"></i>
                  Close
                </button>
              </div>
            </div>
          </div>
        </div>
      )}

      {/* Template Preview Modal */}
      {showTemplatePreview && (
        <div className="fixed inset-0 bg-black/50 backdrop-blur-sm flex items-start sm:items-center justify-center z-50 overflow-auto">
          <div className="bg-white w-full sm:rounded-xl shadow-2xl sm:max-w-7xl sm:my-4 min-h-screen sm:min-h-0 sm:max-h-[95vh] flex flex-col">
            {/* Modal Header */}
            <div className="px-3 sm:px-6 py-3 sm:py-4 border-b border-gray-200 bg-gradient-to-r from-indigo-50 to-purple-50">
              <div className="flex justify-between items-start sm:items-center gap-2">
                <div className="flex-1 min-w-0">
                  <h3 className="text-base sm:text-xl font-bold text-gray-900 flex items-center">
                    <i className="fas fa-envelope-open-text text-indigo-600 mr-2 sm:mr-3 text-sm sm:text-base"></i>
                    <span className="truncate">Email Template Preview</span>
                  </h3>
                  {templatePreviewData && (
                    <p className="text-xs sm:text-sm text-gray-600 mt-1 flex flex-col sm:flex-row sm:items-center gap-1 sm:gap-3">
                      <span className="font-medium truncate">{templatePreviewData.template_name}</span>
                      <span className="text-gray-400 hidden sm:inline">•</span>
                      <span className="truncate">{templatePreviewData.campaign_name}</span>
                    </p>
                  )}
                </div>
                <button
                  onClick={closeTemplatePreview}
                  className="text-gray-400 hover:text-gray-600 p-1.5 sm:p-2 rounded-lg hover:bg-white transition-colors flex-shrink-0"
                >
                  <i className="fas fa-times text-lg sm:text-xl"></i>
                </button>
              </div>

              {/* Email Navigation */}
              {templatePreviewData && templatePreviewData.has_sample_data && (
                <div className="mt-3 sm:mt-4 bg-white rounded-lg shadow-sm p-3 sm:p-4 border border-gray-200">
                  <div className="flex flex-col sm:flex-row items-stretch sm:items-center justify-between gap-3 sm:gap-4">
                    {/* Navigation Controls */}
                    <div className="flex items-center justify-between sm:justify-start gap-2 sm:gap-3">
                      <button
                        type="button"
                        onClick={(e) => {
                          e.preventDefault();
                          e.stopPropagation();
                          changePreviewEmail(templatePreviewData.campaign_id, Math.max(0, currentEmailIndex - 1));
                        }}
                        disabled={currentEmailIndex === 0 || loadingPreview}
                        className="px-3 sm:px-4 py-2 bg-indigo-600 text-white rounded-lg text-xs sm:text-sm font-medium hover:bg-indigo-700 disabled:opacity-50 disabled:cursor-not-allowed transition-all shadow-sm hover:shadow-md flex-1 sm:flex-initial"
                      >
                        {loadingPreview ? <i className="fas fa-spinner fa-spin sm:mr-2"></i> : <i className="fas fa-chevron-left sm:mr-2"></i>}
                        <span className="hidden sm:inline">Previous</span>
                      </button>
                      <div className="px-3 sm:px-4 py-2 bg-gradient-to-r from-indigo-50 to-purple-50 rounded-lg border border-indigo-200">
                        <span className="text-xs sm:text-sm font-bold text-indigo-900 whitespace-nowrap">
                          <span className="hidden sm:inline">Email </span>{currentEmailIndex + 1} <span className="text-indigo-400 mx-1">of</span> {templatePreviewData.total_emails.toLocaleString()}
                        </span>
                      </div>
                      <button
                        type="button"
                        onClick={(e) => {
                          e.preventDefault();
                          e.stopPropagation();
                          changePreviewEmail(templatePreviewData.campaign_id, Math.min(templatePreviewData.total_emails - 1, currentEmailIndex + 1));
                        }}
                        disabled={currentEmailIndex >= templatePreviewData.total_emails - 1 || loadingPreview}
                        className="px-3 sm:px-4 py-2 bg-indigo-600 text-white rounded-lg text-xs sm:text-sm font-medium hover:bg-indigo-700 disabled:opacity-50 disabled:cursor-not-allowed transition-all shadow-sm hover:shadow-md flex-1 sm:flex-initial"
                      >
                        <span className="hidden sm:inline">Next</span>
                        {loadingPreview ? <i className="fas fa-spinner fa-spin sm:ml-2"></i> : <i className="fas fa-chevron-right sm:ml-2"></i>}
                      </button>
                    </div>

                    {/* Page Number Input */}
                    <div className="flex items-center gap-2 bg-gray-50 rounded-lg p-2 border border-gray-200">
                      <label htmlFor="pageNumberInput" className="text-xs sm:text-sm text-gray-700 font-medium whitespace-nowrap">
                        <i className="fas fa-hashtag text-indigo-600 mr-1"></i>
                        Go to page:
                      </label>
                      <input
                        id="pageNumberInput"
                        type="text"
                        inputMode="numeric"
                        pattern="[0-9]*"
                        value={pageNumberInput}
                        onChange={handlePageNumberChange}
                        onKeyPress={handlePageNumberKeyPress}
                        placeholder={`1-${templatePreviewData.total_emails.toLocaleString()}`}
                        disabled={loadingPreview}
                        className="w-20 sm:w-24 px-2 py-1.5 border border-gray-300 rounded text-xs sm:text-sm text-center focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-transparent disabled:opacity-50 disabled:cursor-not-allowed"
                      />
                      <button
                        type="button"
                        onClick={(e) => {
                          e.preventDefault();
                          e.stopPropagation();
                          goToPageNumber();
                        }}
                        disabled={!pageNumberInput || loadingPreview}
                        className="px-3 py-1.5 bg-indigo-600 text-white rounded text-xs sm:text-sm font-medium hover:bg-indigo-700 disabled:opacity-50 disabled:cursor-not-allowed transition-all whitespace-nowrap"
                        title="Jump to page"
                      >
                        {loadingPreview ? <i className="fas fa-spinner fa-spin"></i> : <i className="fas fa-arrow-right"></i>}
                        <span className="hidden sm:inline ml-1">Go</span>
                      </button>
                    </div>

                    {/* Current Email Info */}
                    {templatePreviewData.current_email && (
                      <div className="mt-3 sm:mt-0 sm:flex-1 sm:ml-4 sm:pl-4 pt-3 sm:pt-0 border-t sm:border-t-0 sm:border-l-2 border-indigo-200">
                        <div className="flex items-start gap-2">
                          <i className="fas fa-user-circle text-indigo-600 text-lg sm:text-xl mt-0.5 flex-shrink-0"></i>
                          <div className="flex-1 min-w-0">
                            <div className="font-semibold text-gray-900 flex flex-col sm:flex-row sm:items-center gap-1 sm:gap-2">
                              <span className="text-sm sm:text-base truncate">
                                {templatePreviewData.current_email.Emails || 
                                 templatePreviewData.current_email.Email || 
                                 templatePreviewData.current_email.email ||
                                 templatePreviewData.current_email.raw_emailid ||
                                 templatePreviewData.recipient_email ||
                                 templatePreviewData.to ||
                                 'Email not available'}
                              </span>
                              <span className="px-2 py-0.5 bg-green-100 text-green-700 text-xs rounded-full font-medium inline-flex items-center self-start">
                                <i className="fas fa-check-circle mr-1"></i>Valid
                              </span>
                            </div>
                            <div className="text-xs text-gray-600 mt-1 flex flex-wrap items-center gap-2 sm:gap-3">
                              {/* Show only key fields - max 4 */}
                              {(templatePreviewData.current_email['Billed Name'] || 
                                templatePreviewData.current_email.BilledName || 
                                templatePreviewData.current_email.Name) && (
                                <span className="flex items-center">
                                  <i className="fas fa-building text-gray-400 mr-1"></i>
                                  {templatePreviewData.current_email['Billed Name'] || 
                                   templatePreviewData.current_email.BilledName || 
                                   templatePreviewData.current_email.Name}
                                </span>
                              )}
                              {templatePreviewData.current_email.Amount && (
                                <span className="flex items-center">
                                  <i className="fas fa-rupee-sign text-gray-400 mr-1"></i>
                                  {templatePreviewData.current_email.Amount}
                                </span>
                              )}
                              {templatePreviewData.current_email.Days && (
                                <span className="flex items-center">
                                  <i className="fas fa-calendar-day text-gray-400 mr-1"></i>
                                  {templatePreviewData.current_email.Days} days
                                </span>
                              )}
                              {(templatePreviewData.current_email['Bill Number'] || 
                                templatePreviewData.current_email.BillNumber) && (
                                <span className="flex items-center">
                                  <i className="fas fa-file-invoice text-gray-400 mr-1"></i>
                                  {templatePreviewData.current_email['Bill Number'] || 
                                   templatePreviewData.current_email.BillNumber}
                                </span>
                              )}
                            </div>
                          </div>
                        </div>
                      </div>
                    )}
                  </div>
                </div>
              )}
            </div>

            {/* Modal Body */}
            <div className="flex-1 overflow-hidden flex relative">
              {/* Loading Overlay - Only shown during navigation */}
              {loadingPreview && templatePreviewData && (
                <div className="absolute inset-0 bg-white/80 backdrop-blur-sm z-10 flex items-center justify-center transition-opacity duration-200">
                  <div className="text-center">
                    <i className="fas fa-spinner fa-spin text-4xl text-indigo-600 mb-3"></i>
                    <p className="text-gray-700 font-medium">Loading email...</p>
                  </div>
                </div>
              )}

              {/* Initial Loading State - Only shown on first load */}
              {loadingPreview && !templatePreviewData ? (
                <div className="flex-1 flex items-center justify-center bg-gray-50">
                  <div className="text-center">
                    <i className="fas fa-spinner fa-spin text-5xl text-indigo-600 mb-4"></i>
                    <p className="text-gray-600 font-medium">Loading email preview...</p>
                    <p className="text-sm text-gray-500 mt-2">Merging template with recipient data</p>
                  </div>
                </div>
              ) : templatePreviewData ? (
                <div className="flex-1 overflow-y-auto bg-gray-100 p-3 sm:p-6">
                  {!templatePreviewData.has_sample_data && (
                    <div className="bg-yellow-50 border-l-4 border-yellow-400 rounded-lg p-3 sm:p-4 mb-4 sm:mb-6 shadow-sm">
                      <div className="flex items-start">
                        <i className="fas fa-exclamation-triangle text-yellow-600 text-lg sm:text-xl mt-0.5 mr-2 sm:mr-3 flex-shrink-0"></i>
                        <div>
                          <p className="font-semibold text-yellow-800">No Sample Data Available</p>
                          <p className="text-sm text-yellow-700 mt-1">
                            Showing template without merged data. Upload Excel data to see personalized preview with actual values.
                          </p>
                        </div>
                      </div>
                    </div>
                  )}
                  
                  {/* Email Preview Frame */}
                  <div className="mx-auto w-full" style={{ maxWidth: '800px' }}>
                    {/* Email Client Mock Header */}
                    <div className="bg-white rounded-t-lg sm:rounded-t-xl shadow-lg border border-gray-200 p-3 sm:p-4">
                      <div className="flex items-center justify-between mb-2 sm:mb-3 pb-2 sm:pb-3 border-b border-gray-200">
                        <div className="flex items-center gap-2">
                          <div className="w-3 h-3 rounded-full bg-red-500"></div>
                          <div className="w-3 h-3 rounded-full bg-yellow-500"></div>
                          <div className="w-3 h-3 rounded-full bg-green-500"></div>
                        </div>
                        <div className="text-xs text-gray-500 font-medium">Email Preview</div>
                        <div className="flex items-center gap-2">
                          <button className="text-gray-400 hover:text-gray-600">
                            <i className="fas fa-search text-sm"></i>
                          </button>
                          <button className="text-gray-400 hover:text-gray-600">
                            <i className="fas fa-ellipsis-v text-sm"></i>
                          </button>
                        </div>
                      </div>
                      
                      {/* Email Meta Info */}
                      <div className="space-y-2 text-sm">
                        <div className="flex items-start">
                          <span className="text-gray-500 font-medium w-16">From:</span>
                          <span className="text-gray-900 flex-1">Your Company &lt;noreply@yourcompany.com&gt;</span>
                        </div>
                        {templatePreviewData.current_email && (
                          <div className="flex items-start">
                            <span className="text-gray-500 font-medium w-16">To:</span>
                            <span className="text-gray-900 flex-1">{templatePreviewData.current_email.Emails || templatePreviewData.current_email.Email || 'recipient@example.com'}</span>
                          </div>
                        )}
                        <div className="flex items-start">
                          <span className="text-gray-500 font-medium w-16">Subject:</span>
                          <span className="text-gray-900 flex-1 font-semibold">
                            {templatePreviewData.campaign_name}
                          </span>
                        </div>
                      </div>
                    </div>
                    
                    {/* Email Body Frame */}
                    <div className="bg-white shadow-lg border-x border-b border-gray-200 rounded-b-xl overflow-hidden">
                      <div 
                        className="email-preview-body p-6"
                        style={{
                          minHeight: '400px',
                          fontFamily: 'system-ui, -apple-system, sans-serif'
                        }}
                        onClick={(e) => {
                          // Prevent link clicks from navigating
                          if (e.target.tagName === 'A') {
                            e.preventDefault();
                            e.stopPropagation();
                          }
                        }}
                        dangerouslySetInnerHTML={{ __html: templatePreviewData.template_html }}
                      />
                    </div>
                  </div>
                </div>
              ) : (
                <div className="flex-1 flex items-center justify-center text-gray-500 bg-gray-50">
                  <div className="text-center">
                    <i className="fas fa-file-alt text-5xl mb-4 text-gray-300"></i>
                    <p className="text-lg font-medium">No template data available</p>
                  </div>
                </div>
              )}
            </div>

            {/* Modal Footer */}
            <div className="px-3 sm:px-6 py-3 sm:py-4 border-t border-gray-200 bg-gray-50">
              <div className="flex flex-col sm:flex-row justify-between items-stretch sm:items-center gap-3">
                <div className="text-xs sm:text-sm text-gray-600 flex items-center gap-2 order-2 sm:order-1">
                  {templatePreviewData && templatePreviewData.has_sample_data && (
                    <>
                      <i className="fas fa-info-circle text-indigo-600 flex-shrink-0"></i>
                      <span className="hidden md:inline">Use <kbd className="px-2 py-1 bg-white border border-gray-300 rounded text-xs font-mono">Previous</kbd> / <kbd className="px-2 py-1 bg-white border border-gray-300 rounded text-xs font-mono">Next</kbd> or jump to specific page</span>
                      <span className="md:hidden">Navigate with buttons or page number</span>
                    </>
                  )}
                </div>
                <div className="flex items-center gap-2 sm:gap-3 order-1 sm:order-2">
                  <button
                    onClick={closeTemplatePreview}
                    className="flex-1 sm:flex-initial px-4 sm:px-5 py-2 bg-gray-600 hover:bg-gray-700 text-white rounded-lg transition-colors font-medium shadow-sm hover:shadow-md text-sm"
                  >
                    <i className="fas fa-times mr-2"></i>
                    Close Preview
                  </button>
                </div>
              </div>
            </div>
          </div>
        </div>
      )}
        </>
        )}
      </div>
    </div>
  );
};

// Memoized status badge component
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
    <div className={`fixed top-6 left-1/2 transform -translate-x-1/2
      px-6 py-3 rounded-xl shadow-lg text-base font-bold
      flex items-center gap-3
      ${message.type === "error"
        ? "bg-red-50 border-2 border-red-500 text-red-700"
        : "bg-green-50 border-2 border-green-500 text-green-700"
      }`}
      style={{
        minWidth: 250,
        maxWidth: 600,
        zIndex: 99999,
        boxShadow: message.type === "error" 
          ? "0 8px 32px 0 rgba(220, 38, 38, 0.4)"
          : "0 8px 32px 0 rgba(34, 197, 94, 0.4)",
        background: message.type === "error"
          ? "rgba(254, 226, 226, 0.95)"
          : "rgba(220, 252, 231, 0.95)",
        borderRadius: "16px",
        backdropFilter: "blur(8px)",
        WebkitBackdropFilter: "blur(8px)",
      }}
      role="alert"
    >
      <i className={`fas text-xl ${message.type === "error"
        ? "fa-exclamation-circle text-red-600"
        : "fa-check-circle text-green-600"
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