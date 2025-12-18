import React, { useEffect, useState } from "react";

import { API_CONFIG } from "../../config";

const API_BASE = API_CONFIG.APP;

// Component 1: Server List (20% width)
const ServerList = ({ servers, selectedServer, onSelectServer }) => {
  return (
    <div className="w-1/5 bg-white border-r border-gray-200 flex flex-col">
      <div className="p-4 border-b border-gray-200">
        <h1 className="text-xl font-bold text-gray-800">Mail Accounts</h1>
      </div>
      <div className="flex-1 overflow-y-auto">
        {servers.map((srv) => (
          <button
            key={srv.id}
            onClick={() => onSelectServer(srv.id)}
            className={`w-full text-left px-4 py-3 flex items-center transition-colors ${
              selectedServer === srv.id
                ? "bg-blue-50 text-blue-600 border-r-2 border-blue-500"
                : "hover:bg-gray-100 text-gray-700"
            }`}
          >
            <div className="w-8 h-8 rounded-full bg-blue-100 flex items-center justify-center mr-3">
              <span className="text-sm font-medium text-blue-600">
                {srv.name
                  ? srv.name.charAt(0).toUpperCase()
                  : srv.email.charAt(0).toUpperCase()}
              </span>
            </div>
            <div>
              <div className="text-sm font-medium truncate">
                {srv.name || srv.email}
              </div>
              <div className="text-xs text-gray-500 truncate">{srv.email}</div>
            </div>
          </button>
        ))}
      </div>
    </div>
  );
};

// Component 2: Email List (20% width)
const EmailList = ({
  emails,
  loading,
  error,
  selectedEmail,
  onSelectEmail,
}) => {
  return (
    <div className="w-1/5 border-r border-gray-200 bg-white overflow-y-auto">
      {loading && (
        <div className="flex flex-col items-center justify-center h-64">
          <div className="w-8 h-8 border-4 border-blue-500 border-t-transparent rounded-full animate-spin"></div>
          <p className="mt-3 text-sm text-gray-500">Loading emails...</p>
        </div>
      )}
      {error && (
        <div className="p-4 bg-red-50 text-red-600 text-sm rounded m-4">
          <svg
            className="w-4 h-4 inline mr-2"
            fill="none"
            stroke="currentColor"
            viewBox="0 0 24 24"
          >
            <path
              strokeLinecap="round"
              strokeLinejoin="round"
              strokeWidth={2}
              d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0114 0z"
            />
          </svg>
          {error}
        </div>
      )}
      {!loading && !error && emails.length === 0 && (
        <div className="flex flex-col items-center justify-center h-64 text-gray-400">
          <svg
            className="w-12 h-12 mb-2"
            fill="none"
            stroke="currentColor"
            viewBox="0 0 24 24"
          >
            <path
              strokeLinecap="round"
              strokeLinejoin="round"
              strokeWidth={1}
              d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"
            />
          </svg>
          <p>No emails found</p>
        </div>
      )}
      <ul>
        {emails.map((email) => (
          <li 
            key={email.uid || email.id}
            onClick={() => onSelectEmail(email)}
            className={`border-b border-gray-100 px-4 py-3 cursor-pointer transition-colors ${
              selectedEmail?.uid === email.uid
                ? "bg-blue-50"
                : "hover:bg-gray-50"
            }`}
          >
            <div className="flex justify-between items-start mb-1">
              <span className="font-medium text-sm text-gray-900 truncate">
                {email.from_name || email.from_email}
              </span>
              <span className="text-xs text-gray-500 whitespace-nowrap ml-2">
                {new Date(email.date_received).toLocaleTimeString([], {
                  hour: "2-digit",
                  minute: "2-digit",
                })}
              </span>
            </div>
            <div className="text-sm font-semibold text-gray-800 truncate mb-1">
              {email.subject || "(No Subject)"}
            </div>
            <div className="text-xs text-gray-500 line-clamp-2">
              {email.body}
            </div>
          </li>
        ))}
      </ul>
    </div>
  );
};

// Component 3: Email Preview (60% width)
const EmailPreview = ({ email, onReply, replyStatus }) => {
  const [replyBody, setReplyBody] = useState("");

  const handleReply = () => {
    onReply(replyBody);
    setReplyBody("");
  };

  if (!email) {
    return (
      <div className="w-3/5 flex items-center justify-center bg-gray-50">
        <div className="text-center">
          <svg
            className="w-16 h-16 mx-auto text-gray-300"
            fill="none"
            stroke="currentColor"
            viewBox="0 0 24 24"
          >
            <path
              strokeLinecap="round"
              strokeLinejoin="round"
              strokeWidth={1}
              d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"
            />
          </svg>
          <h3 className="mt-2 text-sm font-medium text-gray-900">
            No email selected
          </h3>
          <p className="mt-1 text-sm text-gray-500">
            Select an email from the list to view its contents
          </p>
        </div>
      </div>
    );
  }

  return (
    <div className="w-3/5 bg-white overflow-y-auto flex flex-col">
      {/* Email Header */}
      <div className="border-b border-gray-200 px-6 py-4">
        <h1 className="text-xl font-bold text-gray-900 mb-2">
          {email.subject}
        </h1>
        <div className="flex items-start">
          <div className="w-10 h-10 rounded-full bg-blue-100 flex items-center justify-center mr-3">
            <span className="text-sm font-medium text-blue-600">
              {email.from_name
                ? email.from_name.charAt(0).toUpperCase()
                : email.from_email.charAt(0).toUpperCase()}
            </span>
          </div>
          <div>
            <div className="text-sm font-medium text-gray-900">
              {email.from_name || email.from_email}
            </div>
            <div className="text-xs text-gray-500">
              to me
              <span className="mx-2">•</span>
              {new Date(email.date_received).toLocaleString()}
            </div>
          </div>
        </div>
      </div>

      {/* Email Body */}
      <div className="flex-1 px-6 py-4">
        <div className="prose max-w-none text-gray-800 whitespace-pre-wrap">
          {email.body}
        </div>
      </div>

      {/* Reply Section */}
      <div className="border-t border-gray-200 px-6 py-4 bg-gray-50">
        <div className="mb-4">
          <label className="block text-sm font-medium text-gray-700 mb-2">
            Reply
          </label>
          <textarea
            rows={6}
            className="w-full px-3 py-2 text-sm border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
            placeholder="Write your reply here..."
            value={replyBody}
            onChange={(e) => setReplyBody(e.target.value)}
          />
        </div>
        <div className="flex items-center justify-between">
          <div className="flex items-center space-x-3">
            <button
              onClick={handleReply}
              disabled={!replyBody}
              className="px-4 py-2 bg-blue-600 text-white text-sm font-medium rounded-md hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 disabled:opacity-50"
            >
              Send
            </button>
            {replyStatus && (
              <span
                className={`text-sm ${
                  replyStatus.includes("Failed")
                    ? "text-red-600"
                    : "text-green-600"
                }`}
              >
                {replyStatus}
              </span>
            )}
          </div>
          <button
            onClick={() => setReplyBody("")}
            className="text-sm text-gray-500 hover:text-gray-700"
          >
            Clear
          </button>
        </div>
      </div>
    </div>
  );
};

// Main Component
const ReceivedResponse = () => {
  const [servers, setServers] = useState([]);
  const [selectedServer, setSelectedServer] = useState(null);
  const [emails, setEmails] = useState([]);
  const [selectedEmail, setSelectedEmail] = useState(null);
  const [loading, setLoading] = useState(false);
  const [error, setError] = useState("");
  const [replyStatus, setReplyStatus] = useState("");

  // Fetch SMTP servers
  useEffect(() => {
    fetch(`${API_BASE}/servers.php`)
      .then((res) => res.json())
      .then((data) => {
        setServers(data.servers || []);
        if (data.servers && data.servers.length > 0) {
          setSelectedServer(data.servers[0].id);
        }
      })
      .catch(() => setError("Failed to load servers"));
  }, []);

  // Fetch emails for selected server
  useEffect(() => {
    if (!selectedServer) return;
    setLoading(true);
    setError("");
    setSelectedEmail(null);
    setEmails([]); // <-- Clear emails before fetching new ones
    fetch(`${API_BASE}/received_response.php?account_id=${selectedServer}`)
      .then((res) => res.json())
      .then((data) => {
        if (!data.success) {
          setError(data.message || "Failed to fetch emails");
          setEmails([]);
        } else {
          setEmails(data.emails || []);
        }
        setLoading(false);
      })
      .catch(() => {
        setError("Failed to fetch emails");
        setLoading(false);
      });
  }, [selectedServer]);

  // Handle reply
  // const handleReply = (replyBody) => {
  //   setReplyStatus("Sending...");
  //   fetch(`${API_BASE}/reply.php`, {
  //     method: "POST",
  //     headers: { "Content-Type": "application/json" },
  //     body: JSON.stringify({
  //       account_id: selectedServer,
  //       to: selectedEmail.from_email,
  //       subject: "Re: " + selectedEmail.subject,
  //       body: replyBody,
  //     }),
  //   })
  //     .then((res) => res.json())
  //     .then((data) => {
  //       setReplyStatus(data.success ? "Reply sent!" : "Failed to send reply");
  //     })
  //     .catch(() => setReplyStatus("Failed to send reply"));
  // };

  const handleReply = (replyBody) => {
    const payload = {
      account_id: selectedServer,
      to: selectedEmail.from_email,
      subject: "Re: " + selectedEmail.subject,
      body: replyBody,
    };

    console.log("Sending reply payload:", payload); // 🔍 Debug log

    setReplyStatus("Sending...");
    fetch(`${API_BASE}/reply.php`, {
      method: "POST",
      headers: { "Content-Type": "application/json" },
      body: JSON.stringify(payload),
    })
      .then((res) => res.json())
      .then((data) => {
        setReplyStatus(data.success ? "Reply sent!" : "Failed to send reply");
      })
      .catch(() => setReplyStatus("Failed to send reply"));
  };

  return (
    <div className="flex h-screen bg-gray-50 font-sans pt-16">
      {/* Server List (20%) */}
      <ServerList
        servers={servers}
        selectedServer={selectedServer}
        onSelectServer={setSelectedServer}
      />

      {/* Email List (20%) - Always visible */}
      <EmailList
        emails={emails}
        loading={loading}
        error={error}
        selectedEmail={selectedEmail}
        onSelectEmail={setSelectedEmail}
      />

      {/* Email Preview (60%) - Always visible */}
      <EmailPreview
        email={selectedEmail}
        onReply={handleReply}
        replyStatus={replyStatus}
      />
    </div>
  );
};

export default ReceivedResponse;
