import React, { useState, useEffect } from "react";

const EmailsList = ({ listId, onClose }) => {
  const [listEmails, setListEmails] = useState([]);
  const [status, setStatus] = useState(null);
  const [loading, setLoading] = useState(false);
  const [pagination, setPagination] = useState({
    page: 1,
    rowsPerPage: 10, // Increased default for large datasets
    total: 0,
  });
  const [filter, setFilter] = useState("all"); // 'all', 'valid', 'invalid', 'timeout'

  // Fetch email details for the given listId
  const fetchListEmails = async () => {
    try {
      setLoading(true);
      const res = await fetch(
        `http://localhost/Verify_email/backend/routes/api.php/api/results?csv_list_id=${listId}&limit=1000000`
      );
      const data = await res.json();
      setListEmails(Array.isArray(data.data) ? data.data : []);
      setPagination((prev) => ({
        ...prev,
        total: Array.isArray(data.data) ? data.data.length : 0,
      }));
    } catch (error) {
      setListEmails([]);
      setStatus({ type: "error", message: "Failed to load list emails" });
    } finally {
      setLoading(false);
    }
  };

  // Utility function for timeout detection
  const isTimeout = (e) =>
    (e.validation_response || "").toLowerCase().includes("timeout") ||
    (e.validation_response || "")
      .toLowerCase()
      .includes("connection refused") ||
    (e.validation_response || "").toLowerCase().includes("failed to connect");

  // Filter emails based on current filter
  const filteredEmails = listEmails.filter((email) => {
    if (filter === "all") return true;
    if (filter === "valid") return email.domain_status === 1;
    if (filter === "invalid")
      return email.domain_status === 0 && !isTimeout(email);
    if (filter === "timeout") return isTimeout(email);
    return true;
  });

  // Calculate paginated emails
  const paginatedEmails = filteredEmails.slice(
    (pagination.page - 1) * pagination.rowsPerPage,
    pagination.page * pagination.rowsPerPage
  );

  // Fetch emails on mount and when listId changes
  useEffect(() => {
    fetchListEmails();
  }, [listId]);

  // Reset pagination when filter or rowsPerPage changes
  useEffect(() => {
    setPagination((prev) => ({
      ...prev,
      page: 1,
      total: filteredEmails.length,
    }));
  }, [filter, pagination.rowsPerPage]);

  // Status message component
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

  // Auto-hide status message
  useEffect(() => {
    if (status) {
      const timer = setTimeout(() => setStatus(null), 4000);
      return () => clearTimeout(timer);
    }
  }, [status]);

  // Export emails to CSV
  const exportToCSV = (type) => {
    let dataToExport = [];

    if (type === "valid") {
      dataToExport = listEmails.filter((e) => e.domain_status === 1);
    } else if (type === "invalid") {
      dataToExport = listEmails.filter(
        (e) => e.domain_status === 0 && !isTimeout(e)
      );
    } else if (type === "timeout") {
      dataToExport = listEmails.filter(isTimeout);
    } else {
      dataToExport = listEmails;
    }

    if (dataToExport.length === 0) {
      setStatus({ type: "error", message: "No emails found for export." });
      return;
    }

    // Export only emails in a single column for all types
    const csvRows = [
      "EMAILS", // header
      ...dataToExport.map((row) => `"${row.email.replace(/"/g, '""')}"`),
    ];

    const csvContent = csvRows.join("\n");

    // Download CSV
    const blob = new Blob([csvContent], { type: "text/csv" });
    const url = URL.createObjectURL(blob);
    const a = document.createElement("a");
    a.href = url;
    a.download = `${type}_emails.csv`;
    document.body.appendChild(a);
    a.click();
    document.body.removeChild(a);
    URL.revokeObjectURL(url);

    setStatus({ type: "success", message: "Exported successfully." });
  };

  // Count emails by status
  const countEmails = (type) => {
    if (type === "valid")
      return listEmails.filter((e) => e.domain_status === 1).length;
    if (type === "invalid")
      return listEmails.filter((e) => e.domain_status === 0 && !isTimeout(e))
        .length;
    if (type === "timeout") {
      return listEmails.filter(isTimeout).length;
    }
    return listEmails.length;
  };

  return (
    <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/30 backdrop-blur-md backdrop-saturate-150 p-4">
      <div
        className="bg-white rounded-xl shadow-lg w-full max-w-6xl max-h-[90vh] flex flex-col"
        onClick={(e) => e.stopPropagation()}
      >
        <div className="p-6 pb-0 flex justify-between items-start">
          <div>
            <h3 className="text-2xl font-bold text-gray-800 mb-2">
              Email List Details
            </h3>
            <p className="text-gray-600">List ID: {listId}</p>
          </div>
          <button
            className="text-gray-500 hover:text-gray-700 p-2 rounded-full hover:bg-gray-100"
            onClick={onClose}
            aria-label="Close"
          >
            <i className="fas fa-times text-xl"></i>
          </button>
        </div>

        <StatusMessage status={status} onClose={() => setStatus(null)} />

        {/* Filters and Stats */}
        <div className="p-6 pt-4 border-b border-gray-200">
          <div className="flex flex-wrap items-center justify-between gap-4 mb-4">
            <div className="flex flex-wrap gap-2">
              <button
                onClick={() => setFilter("all")}
                className={`px-4 py-2 rounded-lg font-medium text-sm ${filter === "all"
                  ? "bg-blue-600 text-white"
                  : "bg-gray-100 text-gray-700 hover:bg-gray-200"
                  }`}
              >
                All ({countEmails("all")})
              </button>
              <button
                onClick={() => setFilter("valid")}
                className={`px-4 py-2 rounded-lg font-medium text-sm ${filter === "valid"
                  ? "bg-green-600 text-white"
                  : "bg-gray-100 text-gray-700 hover:bg-gray-200"
                  }`}
              >
                Valid ({countEmails("valid")})
              </button>
              <button
                onClick={() => setFilter("invalid")}
                className={`px-4 py-2 rounded-lg font-medium text-sm ${filter === "invalid"
                  ? "bg-red-600 text-white"
                  : "bg-gray-100 text-gray-700 hover:bg-gray-200"
                  }`}
              >
                Invalid ({countEmails("invalid")})
              </button>
              <button
                onClick={() => setFilter("timeout")}
                className={`px-4 py-2 rounded-lg font-medium text-sm ${filter === "timeout"
                  ? "bg-yellow-600 text-white"
                  : "bg-gray-100 text-gray-700 hover:bg-gray-200"
                  }`}
              >
                Timeout ({countEmails("timeout")})
              </button>
            </div>

            <div className="flex flex-wrap gap-2">
              <button
                onClick={() => exportToCSV("valid")}
                className="px-4 py-2 bg-green-600 text-white rounded-lg font-medium text-sm hover:bg-green-700 flex items-center gap-2"
              >
                <i className="fas fa-file-export"></i>
                Export Valid
              </button>
              <button
                onClick={() => exportToCSV("invalid")}
                className="px-4 py-2 bg-red-600 text-white rounded-lg font-medium text-sm hover:bg-red-700 flex items-center gap-2"
              >
                <i className="fas fa-file-export"></i>
                Export Invalid
              </button>
              <button
                onClick={() => exportToCSV("timeout")}
                className="px-4 py-2 bg-yellow-600 text-white rounded-lg font-medium text-sm hover:bg-yellow-700 flex items-center gap-2"
              >
                <i className="fas fa-file-export"></i>
                Export Timeout
              </button>
            </div>
          </div>
        </div>

        {/* Email Table */}
        <div className="overflow-auto flex-1">
          <table className="min-w-full divide-y divide-gray-200">
            <thead className="bg-gray-50 sticky top-0">
              <tr>
                <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                  ID
                </th>
                <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                  Email
                </th>
                <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                  Account
                </th>
                <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                  Domain
                </th>
                <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                  Verified
                </th>
                <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                  Status
                </th>
                <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                  Response
                </th>
              </tr>
            </thead>
            <tbody className="bg-white divide-y divide-gray-200">
              {loading ? (
                <tr>
                  <td colSpan={7} className="px-6 py-8 text-center">
                    <div className="flex justify-center">
                      <div className="animate-spin rounded-full h-8 w-8 border-t-2 border-b-2 border-blue-500"></div>
                    </div>
                  </td>
                </tr>
              ) : paginatedEmails.length === 0 ? (
                <tr>
                  <td
                    colSpan={7}
                    className="px-6 py-8 text-center text-gray-500"
                  >
                    No emails found matching the current filter
                  </td>
                </tr>
              ) : (
                paginatedEmails.map((email) => (
                  <tr key={email.id} className="hover:bg-gray-50">
                    <td className="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                      {email.id}
                    </td>
                    <td className="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">
                      {email.raw_emailid || email.email || "N/A"}
                    </td>
                    <td className="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                      {email.sp_account}
                    </td>
                    <td className="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                      {email.sp_domain}
                    </td>
                    <td className="px-6 py-4 whitespace-nowrap">
                      <span
                        className={`px-2 inline-flex text-xs leading-5 font-semibold rounded-full ${email.domain_verified == 1
                          ? "bg-green-100 text-green-800"
                          : "bg-red-100 text-red-800"
                          }`}
                      >
                        {email.domain_verified == 1 ? "Verified" : "Invalid"}
                      </span>
                    </td>
                    <td className="px-6 py-4 whitespace-nowrap">
                      <span
                        className={`px-2 inline-flex text-xs leading-5 font-semibold rounded-full ${isTimeout(email)
                            ? "bg-yellow-100 text-yellow-800"
                            : email.domain_status == 1
                              ? "bg-blue-100 text-blue-800"
                              : "bg-orange-100 text-red-800"
                          }`}
                      >
                        {isTimeout(email)
                          ? "Timeout"
                          : email.domain_status == 1
                            ? "Valid"
                            : "Invalid"}
                      </span>
                    </td>
                    <td className="px-6 py-4 text-sm text-gray-500 max-w-xs truncate">
                      {email.validation_response || "N/A"}
                    </td>
                  </tr>
                ))
              )}
            </tbody>
          </table>
        </div>

        {/* Pagination */}
        <div className="px-6 py-4 border-t border-gray-200 flex items-center justify-between">
          <div className="flex-1 flex justify-between sm:hidden">
            <button
              onClick={() =>
                setPagination((prev) => ({ ...prev, page: prev.page - 1 }))
              }
              disabled={pagination.page === 1}
              className="relative inline-flex items-center px-4 py-2 border border-gray-300 text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50 disabled:opacity-50"
            >
              Previous
            </button>
            <button
              onClick={() =>
                setPagination((prev) => ({ ...prev, page: prev.page + 1 }))
              }
              disabled={
                pagination.page >=
                Math.ceil(filteredEmails.length / pagination.rowsPerPage)
              }
              className="ml-3 relative inline-flex items-center px-4 py-2 border border-gray-300 text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50 disabled:opacity-50"
            >
              Next
            </button>
          </div>
          <div className="hidden sm:flex-1 sm:flex sm:items-center sm:justify-between">
            <div>
              <p className="text-sm text-gray-700">
                Showing{" "}
                <span className="font-medium">
                  {(pagination.page - 1) * pagination.rowsPerPage + 1}
                </span>{" "}
                to{" "}
                <span className="font-medium">
                  {Math.min(
                    pagination.page * pagination.rowsPerPage,
                    filteredEmails.length
                  )}
                </span>{" "}
                of <span className="font-medium">{filteredEmails.length}</span>{" "}
                results
              </p>
            </div>
            <div className="flex items-center gap-2">
              <div className="flex items-center">
                <label
                  htmlFor="rows-per-page"
                  className="mr-2 text-sm text-gray-700"
                >
                  Rows per page:
                </label>
                <select
                  id="rows-per-page"
                  value={pagination.rowsPerPage}
                  onChange={(e) => {
                    setPagination((prev) => ({
                      ...prev,
                      page: 1,
                      rowsPerPage: Number(e.target.value),
                      total: filteredEmails.length,
                    }));
                  }}
                  className="border border-gray-300 rounded-md shadow-sm py-1 pl-2 pr-8 text-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500"
                >
                  {[10, 25, 50, 100, 200].map((size) => (
                    <option key={size} value={size}>
                      {size}
                    </option>
                  ))}
                </select>
              </div>
              <nav
                className="relative z-0 inline-flex rounded-md shadow-sm -space-x-px"
                aria-label="Pagination"
              >
                <button
                  onClick={() =>
                    setPagination((prev) => ({ ...prev, page: 1 }))
                  }
                  disabled={pagination.page === 1}
                  className="relative inline-flex items-center px-2 py-2 rounded-l-md border border-gray-300 bg-white text-sm font-medium text-gray-500 hover:bg-gray-50 disabled:opacity-50"
                >
                  <span className="sr-only">First</span>
                  <i className="fas fa-angle-double-left"></i>
                </button>
                <button
                  onClick={() =>
                    setPagination((prev) => ({ ...prev, page: prev.page - 1 }))
                  }
                  disabled={pagination.page === 1}
                  className="relative inline-flex items-center px-2 py-2 border border-gray-300 bg-white text-sm font-medium text-gray-500 hover:bg-gray-50 disabled:opacity-50"
                >
                  <span className="sr-only">Previous</span>
                  <i className="fas fa-angle-left"></i>
                </button>
                <span className="relative inline-flex items-center px-4 py-2 border border-gray-300 bg-white text-sm font-medium text-gray-700">
                  Page {pagination.page} of{" "}
                  {Math.ceil(filteredEmails.length / pagination.rowsPerPage)}
                </span>
                <button
                  onClick={() =>
                    setPagination((prev) => ({ ...prev, page: prev.page + 1 }))
                  }
                  disabled={
                    pagination.page >=
                    Math.ceil(filteredEmails.length / pagination.rowsPerPage)
                  }
                  className="relative inline-flex items-center px-2 py-2 border border-gray-300 bg-white text-sm font-medium text-gray-500 hover:bg-gray-50 disabled:opacity-50"
                >
                  <span className="sr-only">Next</span>
                  <i className="fas fa-angle-right"></i>
                </button>
                <button
                  onClick={() =>
                    setPagination((prev) => ({
                      ...prev,
                      page: Math.ceil(
                        filteredEmails.length / pagination.rowsPerPage
                      ),
                    }))
                  }
                  disabled={
                    pagination.page >=
                    Math.ceil(filteredEmails.length / pagination.rowsPerPage)
                  }
                  className="relative inline-flex items-center px-2 py-2 rounded-r-md border border-gray-300 bg-white text-sm font-medium text-gray-500 hover:bg-gray-50 disabled:opacity-50"
                >
                  <span className="sr-only">Last</span>
                  <i className="fas fa-angle-double-right"></i>
                </button>
              </nav>
            </div>
          </div>
        </div>
      </div>
    </div>
  );
};

export default EmailsList;
