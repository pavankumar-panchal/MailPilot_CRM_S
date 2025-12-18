import React, { useState, useEffect, useRef, useCallback } from "react";

import EmailsList from "./EmailsListOptimized";
import { API_CONFIG } from "../config";

const checkRetryProgress = async () => {
  try {
    const res = await fetch(`${API_CONFIG.RETRY_SMTP}?progress=1`);
    return await res.json();
  } catch (error) {
    console.error("Error checking retry progress:", error);
    return {
      processed: 0,
      total: 0,
      percent: 0,
      stage: "error",
    };
  }
};

const EmailVerification = () => {
  // Form state
  const [formData, setFormData] = useState({
    listName: "",
    fileName: "",
    csvFile: null,
  });

  // UI state
  const [status, setStatus] = useState(null);
  const [loading, setLoading] = useState(false);
  const [showProgress, setShowProgress] = useState(false);
  const [listsLoading, setListsLoading] = useState(true);

  // Lists state
  const [lists, setLists] = useState([]);
  const [listPagination, setListPagination] = useState({
    page: 1,
    rowsPerPage: 10,
    total: 0,
    search: "",
  });

  // Details state
  const [expandedListId, setExpandedListId] = useState(null);

  const progressInterval = useRef(null);
  const searchTimeout = useRef();

  // Retry failed state - retryingList tracks per-list retry status
  const [retryingList, setRetryingList] = useState({}); // { [listId]: boolean }

  // Search input state
  const [searchInput, setSearchInput] = useState("");

  // Fetch lists (wrapped in useCallback to fix dependency warnings)
  const fetchLists = useCallback(async () => {
    try {
      setListsLoading(true);
      const params = new URLSearchParams({
        page: listPagination.page,
        limit: listPagination.rowsPerPage,
        search: listPagination.search,
      });

      const res = await fetch(`${API_CONFIG.GET_CSV_LIST}?${params}`);
      const data = await res.json();

      setLists(Array.isArray(data.data) ? data.data : []);
      setListPagination((prev) => ({ ...prev, total: data.total || 0 }));
    } catch (error) {
      console.error("Error fetching lists:", error);
      setLists([]);
      setListPagination((prev) => ({ ...prev, total: 0 }));
      setStatus({ type: "error", message: "Failed to load lists" });
    } finally {
      setListsLoading(false);
    }
  }, [listPagination.page, listPagination.rowsPerPage, listPagination.search]);

  // Fetch retry failed count (unused function retained for commented code)
  const fetchRetryFailedCount = async () => {
    try {
      const res = await fetch(`${API_CONFIG.GET_RESULTS}?retry_failed=1`);
      const data = await res.json();
      // Intentionally empty - for future use when uncommenting retry features
      console.log("Retry failed count:", data.total);
    } catch (error) {
      console.error("Error fetching retry failed count:", error);
    }
  };

  // Fetch lists on mount and when pagination changes
  useEffect(() => {
    fetchLists();
  }, [fetchLists]);

  const handleInputChange = (e) => {
    const { name, value } = e.target;
    setFormData((prev) => ({ ...prev, [name]: value }));
  };

  const MAX_CSV_SIZE = 5 * 1024 * 1024; // 5 MB

  const handleFileChange = (e) => {
    const file = e.target.files[0];
    if (file && file.size > MAX_CSV_SIZE) {
      setStatus({ type: "error", message: "CSV file size must be 5 MB or less." });
      return;
    }
    setFormData((prev) => ({ ...prev, csvFile: file }));
  };

  const handleSubmit = async (e) => {
    e.preventDefault();
    setStatus(null);

    if (!formData.csvFile || !formData.listName || !formData.fileName) {
      setStatus({ type: "error", message: "All fields are required" });
      return;
    }

    // Check if CSV file has data
    const fileText = await formData.csvFile.text();
    const lines = fileText.split(/\r?\n/).filter(line => line.trim() !== "");
    if (lines.length < 2) { // Assuming first line is header
      setStatus({ type: "error", message: "CSV file must contain at least one data row." });
      return;
    }

    const formDataObj = new FormData();
    formDataObj.append("csv_file", formData.csvFile);
    formDataObj.append("list_name", formData.listName);
    formDataObj.append("file_name", formData.fileName);

    try {
      setLoading(true);
      const res = await fetch(API_CONFIG.API_UPLOAD, {
          method: "POST",
          body: formDataObj,
        }
      );
      const data = await res.json();

      if (data.status === "success") {
        setStatus({
          type: "success",
          message: data.message || "Upload successful",
        });
        setShowProgress(true);
        startProgressTracking();
        setFormData({ listName: "", fileName: "", csvFile: null });
        fetchLists(); // Fetch latest lists
        fetchRetryFailedCount(); // Fetch latest retry count
      } else {
        setStatus({ type: "error", message: data.message || "Upload failed" });
      }
    } catch (error) {
      console.error("Error uploading file:", error);
      setStatus({ type: "error", message: "Network error" });
    } finally {
      setLoading(false);
    }
  };

  const exportEmails = async (type, listId) => {
    try {
      const url = `${API_CONFIG.GET_RESULTS}?export=${type}&csv_list_id=${listId}`;
      const res = await fetch(url);
      const blob = await res.blob();

      const downloadUrl = window.URL.createObjectURL(blob);
      const link = document.createElement("a");
      link.href = downloadUrl;
      link.download = `${type}_emails_list_${listId}.csv`;
      document.body.appendChild(link);
      link.click();
      link.remove();

      setStatus({ type: "success", message: `Exported ${type} emails list` });
    } catch {
      setStatus({ type: "error", message: `Failed to export ${type} emails` });
    }
  };

  const startProgressTracking = () => {
    if (progressInterval.current) clearInterval(progressInterval.current);

    progressInterval.current = setInterval(async () => {
      try {
        const res = await fetch("/api/verify/progress");
        const data = await res.json();
        // Progress tracking logic disabled - setProgress removed
        console.log("Progress:", data);

        fetchLists();

        // FIX: Replace invalid unicode 'U+0030' with 0
        if (data.total > 0 && data.processed >= data.total) {
          clearInterval(progressInterval.current);
          setTimeout(() => {
            setShowProgress(false);
            setStatus({ type: "success", message: "Verification completed!" });
            fetchLists();
          }, 1000);
        }
      } catch (error) {
        console.error("Error fetching progress:", error);
        clearInterval(progressInterval.current);
        setShowProgress(false);
      }
    }, 2000);
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

  // Auto-hide status message
  useEffect(() => {
    if (status) {
      const timer = setTimeout(() => setStatus(null), 4000);
      return () => clearTimeout(timer);
    }
  }, [status]);

  useEffect(() => {
    let interval;
    if (showProgress) {
      interval = setInterval(() => {
        fetchLists();
      }, 2000);
    }
    return () => clearInterval(interval);
  }, [showProgress, fetchLists]);

  // Update handleSearchChange to update local input state immediately
  const handleSearchChange = (e) => {
    const value = e.target.value;
    setSearchInput(value); // Update input immediately
    clearTimeout(searchTimeout.current);
    searchTimeout.current = setTimeout(() => {
      setListPagination((prev) => ({
        ...prev,
        search: value,
        page: 1,
      }));
    }, 400);
  };

  // When listPagination.search changes (e.g. after debounce), update input field if needed
  useEffect(() => {
    setSearchInput(listPagination.search);
  }, [listPagination.search]);

  // Unused retry handler - retained for commented-out JSX
  const _handleRetryFailed = async () => {
    setLoading(true);
    setStatus(null);

    try {
      // Always fetch latest retry-failed count before retry
      await fetchRetryFailedCount();

      // Start by checking how many need retry
      const resCount = await fetch(`${API_CONFIG.GET_RESULTS}?retry_failed=1`);
      const countData = await resCount.json();

      if (countData.total === 0) {
        setStatus({ type: "error", message: "No failed emails to retry" });
        setLoading(false);
        return;
      }

      // Start the retry process
      const resStart = await fetch(API_CONFIG.API_RETRY_FAILED, {
        method: "POST"
      });
      const startData = await resStart.json();

      if (startData.status !== "success") {
        throw new Error(startData.message || "Failed to start retry");
      }

      setStatus({
        type: "success",
        message: `Retry started for ${countData.total} emails`,
      });

      setShowProgress(true);
      const progressInterval = setInterval(async () => {
        const _progress = await checkRetryProgress();
        // Progress tracking disabled
        console.log("Retry progress:", _progress);

        fetchLists(); // Keep lists updated during retry

        if (_progress.stage === "complete" || _progress.stage === "error") {
          clearInterval(progressInterval);
          setTimeout(() => {
            setShowProgress(false);
            fetchLists();
            fetchRetryFailedCount();
          }, 2000);
        }
      }, 1500);
    } catch (error) {
      setStatus({ type: "error", message: error.message });
    } finally {
      setLoading(false);
    }
  };

  const handleRetryFailedByList = async (listId) => {
    setRetryingList((prev) => ({ ...prev, [listId]: true }));
    setStatus(null);

    try {
      // Fetch failed count for this list
      const resCount = await fetch(`${API_CONFIG.GET_RESULTS}?retry_failed=1&csv_list_id=${listId}`);
      const countData = await resCount.json();

      if (!countData.total || countData.total === 0) {
        setStatus({ type: "error", message: "No failed emails to retry for this list" });
        setRetryingList((prev) => ({ ...prev, [listId]: false }));
        return;
      }

      // Start retry for this list
      const resStart = await fetch(`${API_CONFIG.RETRY_SMTP}?csv_list_id=${listId}`, {
        method: "POST"
      });
      const startData = await resStart.json();

      if (startData.status !== "success") {
        throw new Error(startData.message || "Failed to start retry");
      }

      setStatus({
        type: "success",
        message: `Retry started for ${countData.total} emails in list ${listId}`,
      });

      fetchLists();
    } catch (error) {
      setStatus({ type: "error", message: error.message });
    } finally {
      setRetryingList((prev) => ({ ...prev, [listId]: false }));
    }
  };

  const statusBadgeColor = (status) => {
    switch (status) {
      case "completed":
        return "bg-emerald-100 text-emerald-800";
      case "running":
        return "bg-blue-100 text-blue-800";
      case "pending":
        return "bg-amber-100 text-amber-800";
      case "failed":
        return "bg-red-100 text-red-800";
      default:
        return "bg-gray-100 text-gray-800";
    }
  };

  // Unused variable for commented JSX
  const _failedCount = lists.filter((list) => list.domain_status === 2).length;

  return (
    <div className="container mx-auto px-4 py-8 max-w-7xl">
      <StatusMessage status={status} onClose={() => setStatus(null)} />

      {/* Upload Section */}
      <div className="bg-white rounded-xl shadow-sm border border-gray-200 p-6 mb-8 mt-12">
        <div className="flex items-center mb-6">
          <div className="bg-blue-100 p-2 rounded-lg mr-4">
            <svg
              className="w-6 h-6 text-blue-600"
              fill="none"
              stroke="currentColor"
              viewBox="0 0 24 24"
            >
              <path
                strokeLinecap="round"
                strokeLinejoin="round"
                strokeWidth="2"
                d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M9 19l3 3m0 0l3-3m-3 3V10"
              />
            </svg>
          </div>
          <h2 className="text-xl font-semibold text-gray-800">
            Upload Email List
          </h2>
        </div>

        <form onSubmit={handleSubmit} className="space-y-6">
          <div className="grid grid-cols-1 md:grid-cols-2 gap-6">
            <div>
              <label className="block text-sm font-medium text-gray-700 mb-2">
                List Name
                <span className="text-red-500 ml-1">*</span>
              </label>
              <input
                type="text"
                name="listName"
                value={formData.listName}
                onChange={handleInputChange}
                className="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-blue-500 focus:border-blue-500 transition-colors"
                placeholder="e.g. List_2025"
                required
              />
            </div>

            <div>
              <label className="block text-sm font-medium text-gray-700 mb-2">
                File Name
                <span className="text-red-500 ml-1">*</span>
              </label>
              <input
                type="text"
                name="fileName"
                value={formData.fileName}
                onChange={handleInputChange}
                className="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-blue-500 focus:border-blue-500 transition-colors"
                placeholder="e.g. File_2025.csv"
                required
              />
            </div>
          </div>

          <div>
            <label className="block text-sm font-medium text-gray-700 mb-2">
              CSV File
              <span className="text-red-500 ml-1">*</span>
            </label>
            <div className="mt-1 flex justify-center px-6 pt-5 pb-6 border-2 border-gray-300 border-dashed rounded-lg hover:border-blue-400 transition-colors">
              <div className="space-y-1 text-center">
                {formData.csvFile ? (
                  <div className="flex items-center justify-center">
                    <div className="bg-green-100 p-2 rounded-lg mr-4">
                      <svg
                        className="w-6 h-6 text-green-600"
                        fill="none"
                        stroke="currentColor"
                        viewBox="0 0 24 24"
                      >
                        <path
                          strokeLinecap="round"
                          strokeLinejoin="round"
                          strokeWidth="2"
                          d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"
                        />
                      </svg>
                    </div>
                    <div className="text-left">
                      <p className="text-sm font-medium text-gray-700">
                        {formData.csvFile.name}
                      </p>
                      <p className="text-xs text-gray-500">
                        {(formData.csvFile.size / 1024).toFixed(1)} KB
                      </p>
                    </div>
                  </div>
                ) : (
                  <>
                    <svg
                      className="mx-auto h-12 w-12 text-gray-400"
                      fill="none"
                      stroke="currentColor"
                      viewBox="0 0 24 24"
                    >
                      <path
                        strokeLinecap="round"
                        strokeLinejoin="round"
                        strokeWidth="2"
                        d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12"
                      />
                    </svg>
                    <div className="flex text-sm text-gray-600 justify-center">
                      <label className="relative cursor-pointer bg-white rounded-md font-medium text-blue-600 hover:text-blue-500 focus-within:outline-none">
                        <span>Upload a file</span>
                        <input
                          type="file"
                          name="csvFile"
                          className="sr-only"
                          accept=".csv"
                          onChange={handleFileChange}
                          required
                        />
                      </label>
                      <p className="pl-1">or drag and drop</p>
                    </div>
                    <p className="text-xs text-gray-500">Only 5MB CSV files</p>
                  </>
                )}
              </div>
            </div>
          </div>

          <div className="flex justify-center">
            <button
              type="submit"
              disabled={loading}
              className="px-6 py-3 bg-blue-600 text-white font-medium rounded-lg shadow-sm hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2 transition-colors flex items-center disabled:opacity-70"
            >
              {loading ? (
                <>
                  <svg
                    className="animate-spin -ml-1 mr-3 h-5 w-5 text-white"
                    xmlns="http://www.w3.org/2000/svg"
                    fill="none"
                    viewBox="0 0 24 24"
                  >
                    <circle
                      className="opacity-25"
                      cx="12"
                      cy="12"
                      r="10"
                      stroke="currentColor"
                      strokeWidth="4"
                    ></circle>
                    <path
                      className="opacity-75"
                      fill="currentColor"
                      d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"
                    ></path>
                  </svg>
                  Processing...
                </>
              ) : (
                <>
                  <svg
                    className="w-5 h-5 mr-2 -ml-1"
                    fill="none"
                    stroke="currentColor"
                    viewBox="0 0 24 24"
                  >
                    <path
                      strokeLinecap="round"
                      strokeLinejoin="round"
                      strokeWidth="2"
                      d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M9 19l3 3m0 0l3-3m-3 3V10"
                    />
                  </svg>
                  Upload & Verify
                </>
              )}
            </button>
          </div>
        </form>
      </div>

      {/* Lists Section */}
      <div className="bg-white rounded-xl shadow-sm border border-gray-200 p-6">
        <div className="flex flex-col sm:flex-row justify-between items-start sm:items-center mb-6 gap-4">
          <div className="flex items-center">
            <div className="bg-blue-100 p-2 rounded-lg mr-4">
              <svg
                className="w-6 h-6 text-blue-600"
                fill="none"
                stroke="currentColor"
                viewBox="0 0 24 24"
              >
                <path
                  strokeLinecap="round"
                  strokeLinejoin="round"
                  strokeWidth="2"
                  d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"
                />
              </svg>
            </div>
            <h2 className="text-xl font-semibold text-gray-800">Email Lists</h2>
          </div>
          <div className="flex flex-col sm:flex-row gap-3 w-full sm:w-auto">
            <div className="relative flex-grow max-w-md">
              <div className="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                <svg
                  className="h-5 w-5 text-gray-400"
                  fill="none"
                  stroke="currentColor"
                  viewBox="0 0 24 24"
                >
                  <path
                    strokeLinecap="round"
                    strokeLinejoin="round"
                    strokeWidth="2"
                    d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"
                  />
                </svg>
              </div>
              <input
                type="text"
                placeholder="Search lists..."
                className="pl-10 w-full border border-gray-300 rounded-lg py-2 px-4 focus:ring-blue-500 focus:border-blue-500 transition-colors"
                value={searchInput}
                onChange={handleSearchChange}
              />
            </div>
            <div className="flex gap-2">
              {/* <button
                onClick={() => exportEmails("valid")}
                className="px-4 py-2 bg-green-600 text-white rounded-lg hover:bg-green-700 transition-colors flex items-center"
              >
                <svg
                  className="w-4 h-4 mr-2"
                  fill="none"
                  stroke="currentColor"
                  viewBox="0 0 24 24"
                >
                  <path
                    strokeLinecap="round"
                    strokeLinejoin="round"
                    strokeWidth="2"
                    d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"
                  />
                </svg>
                Export Valid
              </button> */}
              {/* <button
                onClick={() => exportEmails("invalid")}
                className="px-4 py-2 bg-red-600 text-white rounded-lg hover:bg-red-700 transition-colors flex items-center"
              >
                <svg
                  className="w-4 h-4 mr-2"
                  fill="none"
                  stroke="currentColor"
                  viewBox="0 0 24 24"
                >
                  <path
                    strokeLinecap="round"
                    strokeLinejoin="round"
                    strokeWidth="2"
                    d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"
                  />
                </svg>
                Export Invalid
              </button> */}
              {/* <button
                onClick={handleRetryFailed}
                disabled={loading || retryFailedCount === 0}
                className="px-4 py-2 bg-yellow-600 text-white rounded-lg hover:bg-yellow-700 transition-colors flex items-center"
              >
                <svg
                  className={`w-4 h-4 mr-2 ${loading ? "animate-spin" : ""}`}
                  fill="none"
                  stroke="currentColor"
                  viewBox="0 0 24 24"
                >
                  <path
                    strokeLinecap="round"
                    strokeLinejoin="round"
                    strokeWidth="2"
                    d="M4 4v5h5M20 20v-5h-5M5.5 8.5a8 8 0 0113 0M18.5 15.5a8 8 0 01-13 0"
                  />
                </svg>
                {loading ? "Retrying..." : `Retry Failed (${retryFailedCount})`}
              </button> */}
           
            </div>
          </div>
        </div>

        {/* Lists Table */}
        <div className="overflow-x-auto rounded-lg border border-gray-200">
          <table className="min-w-full divide-y divide-gray-200">
            <thead className="bg-gray-50">
              <tr>
                <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                  ID
                </th>
                <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                  List Name
                </th>
                <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                  Status
                </th>
                <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                  Emails
                </th>
                <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                  Valid/Invalid
                </th>
                <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                  Actions
                </th>
              </tr>
            </thead>
            <tbody className="bg-white divide-y divide-gray-200">
              {listsLoading ? (
                // Skeleton loading rows
                Array.from({ length: listPagination.rowsPerPage }).map((_, idx) => (
                  <tr key={idx} className="animate-pulse">
                    <td className="px-6 py-4"><div className="h-4 bg-gray-200 rounded w-12"></div></td>
                    <td className="px-6 py-4"><div className="h-4 bg-gray-200 rounded w-32"></div></td>
                    <td className="px-6 py-4"><div className="h-6 bg-gray-200 rounded-full w-20"></div></td>
                    <td className="px-6 py-4"><div className="h-4 bg-gray-200 rounded w-20"></div></td>
                    <td className="px-6 py-4"><div className="h-4 bg-gray-200 rounded w-24"></div></td>
                    <td className="px-6 py-4 flex gap-2">
                      <div className="h-8 bg-gray-200 rounded w-16"></div>
                      <div className="h-8 bg-gray-200 rounded w-16"></div>
                      <div className="h-8 bg-gray-200 rounded w-16"></div>
                      <div className="h-8 bg-gray-200 rounded w-20"></div>
                    </td>
                  </tr>
                ))
              ) : lists.length === 0 ? (
                <tr>
                  <td
                    colSpan={6}
                    className="px-6 py-4 text-center text-gray-500 text-sm"
                  >
                    {listPagination.search
                      ? "No lists match your search criteria"
                      : "No lists found. Upload a CSV file to get started."}
                  </td>
                </tr>
              ) : (
                // Filter lists by list name (case-insensitive)
                lists
                  .filter((list) =>
                    list.list_name
                      .toLowerCase()
                      .includes(listPagination.search.toLowerCase())
                  )
                  .map((list) => (
                    <tr
                      key={list.id}
                      className="hover:bg-gray-50 transition-colors"
                    >
                      <td className="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">
                        {list.id}
                      </td>
                      <td className="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                        {list.list_name}
                      </td>
                      <td className="px-6 py-4 whitespace-nowrap">
                        <span
                          className={`px-2.5 py-0.5 inline-flex text-xs leading-5 font-semibold rounded-full ${statusBadgeColor(
                            list.status
                          )}`}
                        >
                          {list.status.charAt(0).toUpperCase() +
                            list.status.slice(1)}
                        </span>
                      </td>
                      <td className="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                        {list.total_emails} total
                      </td>
                      <td className="px-6 py-4 whitespace-nowrap text-sm">
                        <span className="text-emerald-600 font-medium">
                          {list.valid_count || 0} valid
                        </span>{" "}
                        /{" "}
                        <span className="text-red-600 font-medium">
                          {list.invalid_count || 0} invalid
                        </span>
                      </td>
                      <td className="px-6 py-4 whitespace-nowrap text-sm font-medium flex gap-2">
                        <button
                          onClick={() => setExpandedListId(list.id)}
                          className="text-blue-600 hover:text-blue-800 transition-colors flex items-center"
                        >
                          <svg
                            className="w-4 h-4 mr-1"
                            fill="none"
                            stroke="currentColor"
                            viewBox="0 0 24 24"
                          >
                            <path
                              strokeLinecap="round"
                              strokeLinejoin="round"
                              strokeWidth="2"
                              d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"
                            />
                            <path
                              strokeLinecap="round"
                              strokeLinejoin="round"
                              strokeWidth="2"
                              d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"
                            />
                          </svg>
                          View
                        </button>
                        <button
                          onClick={() => exportEmails("valid", list.id)}
                          className="text-green-600 hover:text-green-800 transition-colors flex items-center"
                        >
                          <svg
                            className="w-4 h-4 mr-1"
                            fill="none"
                            stroke="currentColor"
                            viewBox="0 0 24 24"
                          >
                            <path
                              strokeLinecap="round"
                              strokeLinejoin="round"
                              strokeWidth="2"
                              d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"
                            />
                          </svg>
                          Valid
                        </button>
                        <button
                          onClick={() => exportEmails("invalid", list.id)}
                          className="text-red-600 hover:text-red-800 transition-colors flex items-center"
                        >
                          <svg
                            className="w-4 h-4 mr-1"
                            fill="none"
                            stroke="currentColor"
                            viewBox="0 0 24 24"
                          >
                            <path
                              strokeLinecap="round"
                              strokeLinejoin="round"
                              strokeWidth="2"
                              d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"
                            />
                          </svg>
                          Invalid
                        </button>
                        <button
                          onClick={() => handleRetryFailedByList(list.id)}
                          disabled={retryingList[list.id] || !list.failed_count}
                          className="text-yellow-600 hover:text-yellow-800 transition-colors flex items-center border border-yellow-300 rounded px-2 py-1 disabled:opacity-60"
                          title="Retry failed emails for this list"
                        >
                          <svg
                            className={`w-4 h-4 mr-1 ${retryingList[list.id] ? "animate-spin" : ""}`}
                            fill="none"
                            stroke="currentColor"
                            viewBox="0 0 24 24"
                          >
                            <path
                              strokeLinecap="round"
                              strokeLinejoin="round"
                              strokeWidth="2"
                              d="M4 4v5h5M20 20v-5h-5M5.5 8.5a8 8 0 0113 0M18.5 15.5a8 8 0 01-13 0"
                            />
                          </svg>
                          {retryingList[list.id]
                            ? "Retrying..."
                            : `Retry (${list.failed_count || 0})`}
                        </button>
                      </td>
                    </tr>
                  ))
              )}
            </tbody>
          </table>
        </div>

        {/* Pagination */}
        {lists.length > 0 && (
          <div className="flex flex-col items-center justify-center mt-6 px-1 gap-2">
            <div className="text-sm text-gray-500 mb-2">
              Showing{" "}
              <span className="font-medium">
                {(listPagination.page - 1) * listPagination.rowsPerPage + 1}
              </span>{" "}
              to{" "}
              <span className="font-medium">
                {Math.min(
                  listPagination.page * listPagination.rowsPerPage,
                  listPagination.total
                )}
              </span>{" "}
              of <span className="font-medium">{listPagination.total}</span>{" "}
              lists
            </div>
            <div className="flex items-center gap-2">
              <button
                onClick={() =>
                  setListPagination((prev) => ({ ...prev, page: 1 }))
                }
                disabled={listPagination.page === 1}
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
                onClick={() =>
                  setListPagination((prev) => ({
                    ...prev,
                    page: prev.page - 1,
                  }))
                }
                disabled={listPagination.page === 1}
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
                Page {listPagination.page} of{" "}
                {Math.max(
                  1,
                  Math.ceil(listPagination.total / listPagination.rowsPerPage)
                )}
              </span>
              <button
                onClick={() =>
                  setListPagination((prev) => ({
                    ...prev,
                    page: Math.min(
                      Math.ceil(
                        listPagination.total / listPagination.rowsPerPage
                      ),
                      prev.page + 1
                    ),
                  }))
                }
                disabled={
                  listPagination.page >=
                  Math.ceil(listPagination.total / listPagination.rowsPerPage)
                }
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
                onClick={() =>
                  setListPagination((prev) => ({
                    ...prev,
                    page: Math.ceil(
                      listPagination.total / listPagination.rowsPerPage
                    ),
                  }))
                }
                disabled={
                  listPagination.page >=
                  Math.ceil(listPagination.total / listPagination.rowsPerPage)
                }
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
                value={listPagination.rowsPerPage}
                onChange={(e) =>
                  setListPagination((prev) => ({
                    ...prev,
                    rowsPerPage: Number(e.target.value),
                    page: 1,
                  }))
                }
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

      {/* Emails List Overlay */}
      {expandedListId && (
        <EmailsList
          listId={expandedListId}
          onClose={() => setExpandedListId(null)}
        />
      )}
    </div>
  );
};

export default EmailVerification; 