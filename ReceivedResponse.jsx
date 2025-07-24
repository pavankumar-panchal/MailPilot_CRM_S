import React, { useEffect, useState } from "react";

const API_BASE = "http://localhost/Verify_email/backend/app";

const ReceivedResponse = () => {
  const [servers, setServers] = useState([]);
  const [selectedServer, setSelectedServer] = useState(null);
  const [emails, setEmails] = useState([]);
  const [selectedEmail, setSelectedEmail] = useState(null);
  const [loading, setLoading] = useState(false);
  const [error, setError] = useState("");
  const [replyBody, setReplyBody] = useState("");
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
  const handleReply = () => {
    setReplyStatus("Sending...");
    fetch(`${API_BASE}/reply.php`, {
      method: "POST",
      headers: { "Content-Type": "application/json" },
      body: JSON.stringify({
        account_id: selectedServer,
        to: selectedEmail.from_email,
        subject: "Re: " + selectedEmail.subject,
        body: replyBody,
      }),
    })
      .then((res) => res.json())
      .then((data) => {
        setReplyStatus(data.success ? "Reply sent!" : "Failed to send reply");
        setReplyBody("");
      })
      .catch(() => setReplyStatus("Failed to send reply"));
  };

  return (
    <div className="flex max-w-5xl mx-auto p-4 bg-gray-50 min-h-screen">
      {/* Sidebar: SMTP Servers */}
      <div className="w-64 pr-4 border-r">
        <h2 className="font-bold mb-2">Accounts</h2>
        <ul>
          {servers.map((srv) => (
            <li
              key={srv.id}
              className={`cursor-pointer p-2 rounded ${
                selectedServer === srv.id ? "bg-blue-100 font-bold" : ""
              }`}
              onClick={() => setSelectedServer(srv.id)}
            >
              {srv.name || srv.email}
            </li>
          ))}
        </ul>
      </div>

      {/* Main: Email List & Details */}
      <div className="flex-1 pl-4">
        <h1 className="text-2xl font-bold mb-4">Inbox</h1>
        {loading && <div>Loading...</div>}
        {error && <div className="text-red-500 mb-2">{error}</div>}

        {!selectedEmail ? (
          <ul>
            {emails.length === 0 && !loading && <div>No emails found.</div>}
            {emails.map((email) => (
              <li
                key={email.uid || email.id}
                className="border-b py-3 cursor-pointer hover:bg-gray-100"
                onClick={() => setSelectedEmail(email)}
              >
                <div className="flex justify-between">
                  <span className="font-semibold">
                    {email.subject || "(No Subject)"}
                  </span>
                  <span className="text-xs text-gray-500">
                    {email.date_received}
                  </span>
                </div>
                <div className="text-sm text-gray-600">
                  {email.from_name || email.from_email || "Unknown"}
                </div>
                <div className="text-xs text-gray-500">
                  {email.body ? email.body.slice(0, 80) : ""}
                  {email.body && email.body.length > 80 ? "..." : ""}
                </div>
              </li>
            ))}
          </ul>
        ) : (
          <div className="bg-white p-4 rounded shadow">
            <button
              className="mb-2 text-blue-600 underline"
              onClick={() => setSelectedEmail(null)}
            >
              &larr; Back to Inbox
            </button>
            <div className="font-bold text-lg">{selectedEmail.subject}</div>
            <div className="text-sm text-gray-600 mb-2">
              From: {selectedEmail.from_name || selectedEmail.from_email}
            </div>
            <div className="text-xs text-gray-500 mb-2">
              {selectedEmail.date_received}
            </div>
            <div className="mb-4 whitespace-pre-wrap">{selectedEmail.body}</div>
            <div className="mt-4">
              <textarea
                className="w-full border p-2 rounded"
                rows={4}
                placeholder="Type your reply..."
                value={replyBody}
                onChange={(e) => setReplyBody(e.target.value)}
              />
              <button
                className="mt-2 px-4 py-2 bg-blue-600 text-white rounded"
                onClick={handleReply}
                disabled={!replyBody}
              >
                Send Reply
              </button>
              {replyStatus && (
                <div className="mt-2 text-green-600">{replyStatus}</div>
              )}
            </div>
          </div>
        )}
      </div>
    </div>
  );
};

export default ReceivedResponse;
