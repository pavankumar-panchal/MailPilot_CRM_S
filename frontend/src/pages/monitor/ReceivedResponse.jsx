import React, { useEffect, useState } from "react";

const ReceivedResponse = () => {
  const [emails, setEmails] = useState([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState("");
  const [backendMsg, setBackendMsg] = useState("");

  useEffect(() => {
    setLoading(true);
    setError("");
    fetch("http://localhost/Verify_email/backend/app/received_response.php?account_id=1&type=regular&page=1")
      .then((res) => {
        if (!res.ok) throw new Error("Failed to fetch emails");
        return res.json();
      })
      .then((data) => {
        setBackendMsg(data.message || "");
        if (data.success === false && data.error) {
          setError(data.error);
          setEmails([]);
        } else {
          setEmails(data.emails || []);
        }
        setLoading(false);
      })
      .catch((err) => {
        setError(err.message || "Unknown error");
        setLoading(false);
      });
  }, []);

  if (loading) return <div className="text-xl">Loading...</div>;
  if (error) return (
    <div>
      <div className="text-red-500">Error: {error}</div>
      <div className="mb-2 text-blue-700">{backendMsg}</div>
    </div>
  );

  return (
    <div className="max-w-3xl mx-auto p-4">
      <h1 className="text-2xl font-bold mb-4">Received Responses</h1>
      <div className="mb-2 text-green-700 font-semibold">
        {`Fetched ${emails.length} email${emails.length !== 1 ? "s" : ""} from backend.`}
      </div>
      <div className="mb-2 text-blue-700">{backendMsg}</div>
      {emails.length === 0 ? (
        <div className="text-gray-700">No emails found.</div>
      ) : (
        <ul className="space-y-4">
          {emails.map((email) => (
            <li key={email.uid || email.id || Math.random()} className="p-4 bg-white rounded shadow">
              <div className="font-semibold">{email.subject || "(No Subject)"}</div>
              <div className="text-sm text-gray-600">
                From: {email.from_name || email.from_email || "Unknown"}
              </div>
              <div className="text-xs text-gray-500">{email.date_received || ""}</div>
              <div className="mt-2 text-gray-700">
                {email.body ? email.body.slice(0, 200) : ""}
                {email.body && email.body.length > 200 ? "..." : ""}
              </div>
            </li>
          ))}
        </ul>
      )}
    </div>
  );
};

export default ReceivedResponse;