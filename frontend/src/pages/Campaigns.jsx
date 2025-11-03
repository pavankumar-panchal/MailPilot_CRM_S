import React, { useEffect, useState, useRef } from "react";
import Quill from 'quill';
import 'quill/dist/quill.snow.css';
import "../quill.css";

const emptyCampaign = {
  description: "",
  mail_subject: "",
  mail_body: "",
  attachment: null,
  existing_attachment: null, // Track existing attachment path
  images: [], // Track uploaded image paths
};

// Quill configuration (toolbar + formats)
const quillModules = {
  toolbar: [
    [{ header: [1, 2, 3, false] }],
    ['bold', 'italic', 'underline', 'strike'],
    [{ list: 'ordered' }, { list: 'bullet' }],
    [{ color: [] }, { background: [] }],
    ['link', 'image'],
    ['clean']
  ]
};

const quillFormats = [
  'header', 'bold', 'italic', 'underline', 'strike',
  'list', 'bullet', 'link', 'color', 'background', 'image'
];

// Direct Quill editor wrapper compatible with React 19
function QuillEditor({ value, onChange, modules = quillModules, formats = quillFormats, placeholder, onImageUpload, uploadImageUrl }) {
  const containerRef = useRef(null);
  const quillRef = useRef(null);

  useEffect(() => {
    if (!containerRef.current) return;
    if (!quillRef.current) {
      quillRef.current = new Quill(containerRef.current, {
        theme: 'snow',
        modules,
        formats,
        placeholder: placeholder || ''
      });

      // Custom image handler
      const toolbar = quillRef.current.getModule('toolbar');
      toolbar.addHandler('image', () => {
        const input = document.createElement('input');
        input.setAttribute('type', 'file');
        input.setAttribute('accept', 'image/*');
        input.click();

        input.onchange = async () => {
          const file = input.files[0];
          if (file) {
            // Show loading state
            const range = quillRef.current.getSelection(true);
            quillRef.current.insertText(range.index, 'Uploading image...');
            
            const formData = new FormData();
            formData.append('image', file);

            try {
              const response = await fetch(uploadImageUrl || 'http://localhost/verify_emails/MailPilot_CRM/backend/includes/upload_image.php', {
                method: 'POST',
                body: formData
              });

              let result;
              try {
                result = await response.json();
              } catch {
                const text = await response.text();
                console.error('Server response:', text);
                throw new Error('Invalid JSON response from server: ' + text.substring(0, 100));
              }
              
              // Remove loading text
              quillRef.current.deleteText(range.index, 'Uploading image...'.length);
              
              if (result.success) {
                // Insert image at cursor position
                quillRef.current.insertEmbed(range.index, 'image', result.url);
                quillRef.current.setSelection(range.index + 1);
                
                // Notify parent component about the uploaded image path
                if (onImageUpload) {
                  onImageUpload(result.path);
                }
              } else {
                alert('Image upload failed: ' + result.message);
                console.error('Upload failed:', result);
              }
            } catch (error) {
              quillRef.current.deleteText(range.index, 'Uploading image...'.length);
              alert('Image upload failed: ' + error.message);
              console.error('Upload error:', error);
            }
          }
        };
      });

      quillRef.current.on('text-change', () => {
        const html = quillRef.current.root.innerHTML;
        onChange && onChange(html === '<p><br></p>' ? '' : html);
      });
    }

    return () => {
      // optional cleanup: clear container
      // if (containerRef.current) containerRef.current.innerHTML = '';
      // keep instance as Quill doesn't expose a destroy API we rely on garbage collection
    };
  }, [modules, formats, onChange, placeholder, onImageUpload, uploadImageUrl]);

  // Sync external value into editor
  useEffect(() => {
    const q = quillRef.current;
    if (!q) return;
    const current = q.root.innerHTML;
    if ((value || '') !== current) {
      q.clipboard.dangerouslyPasteHTML(value || '');
    }
  }, [value]);

  return <div className="quill" style={{ background: 'white' }}>
    <div ref={containerRef} />
  </div>;
}

// Glassmorphism Status Message Popup
const StatusMessage = ({ message, onClose }) =>
  message && (
    <div
      className={`
        fixed top-6 left-1/2 transform -translate-x-1/2 z-50
        px-6 py-3 rounded-xl shadow text-base font-semibold
        flex items-center gap-3
        transition-all duration-300
        backdrop-blur-md
        ${message.type === "error"
          ? "bg-red-200/60 border border-red-400 text-red-800"
          : "bg-green-200/60 border border-green-400 text-green-800"
        }
      `}
      style={{
        minWidth: 250,
        maxWidth: 400,
        boxShadow: "0 8px 32px 0 rgba(0, 0, 0, 0.23)",
        background:
          message.type === "error"
            ? "rgba(255, 0, 0, 0.29)"
            : "rgba(0, 200, 83, 0.29)",
        borderRadius: "16px",
        backdropFilter: "blur(8px)",
        WebkitBackdropFilter: "blur(8px)",
      }}
      role="alert"
    >
      <i
        className={`fas text-lg ${message.type === "error"
          ? "fa-exclamation-circle text-red-500"
          : "fa-check-circle text-green-500"
          }`}
      ></i>
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

const Campaigns = () => {
  const [campaigns, setCampaigns] = useState([]);
  const [loading, setLoading] = useState(true);
  const [modalOpen, setModalOpen] = useState(false);
  const [editModalOpen, setEditModalOpen] = useState(false);
  const [form, setForm] = useState(emptyCampaign);
  const [attachmentFile, setAttachmentFile] = useState(null);
  const [uploadedImages, setUploadedImages] = useState([]); // Track images uploaded via Quill
  const [editId, setEditId] = useState(null);
  const [message, setMessage] = useState(null);

  // Pagination state
  const [pagination, setPagination] = useState({
    page: 1,
    rowsPerPage: 10,
    total: 0,
  });

  // Configuration - Update these for production
  const BASE_URL = "http://localhost/verify_emails/MailPilot_CRM";
  const API_URL = `${BASE_URL}/backend/routes/api.php/api/master/campaigns`;
  const UPLOAD_IMAGE_URL = `${BASE_URL}/backend/includes/upload_image.php`;

  // Fetch campaigns
  const fetchCampaigns = React.useCallback(async () => {
    setLoading(true);
    try {
      const res = await fetch(API_URL);
      const data = await res.json();
      if (Array.isArray(data)) {
        setCampaigns(data);
        setPagination((prev) => ({
          ...prev,
          total: data.length,
        }));
      } else {
        setCampaigns([]);
        setPagination((prev) => ({
          ...prev,
          total: 0,
        }));
      }
    } catch {
      setMessage({ type: "error", text: "Failed to load campaigns." });
      setCampaigns([]);
      setPagination((prev) => ({
        ...prev,
        total: 0,
      }));
    }
    setLoading(false);
  }, [API_URL]);

  useEffect(() => {
    fetchCampaigns();
  }, [fetchCampaigns]);

  // Handle form input
  const handleChange = (e) => {
    const { name, value, files } = e.target;
    if (name === "attachment") {
      setAttachmentFile(files[0]);
    } else {
      setForm((f) => ({
        ...f,
        [name]: value,
      }));
    }
  };

  // Callback when image is uploaded via Quill
  const handleImageUpload = (imagePath) => {
    setUploadedImages(prev => [...prev, imagePath]);
  };

  // Add campaign
  const handleAdd = async (e) => {
    e.preventDefault();
    const formData = new FormData();
    formData.append("description", form.description);
    formData.append("mail_subject", form.mail_subject);
    formData.append("mail_body", form.mail_body);
    formData.append("send_as_html", "1"); // Always send as HTML for rich content
    
    if (attachmentFile) {
      formData.append("attachment", attachmentFile);
    }

    // ALWAYS send uploaded images as JSON array (even if empty)
    formData.append("images_json", JSON.stringify(uploadedImages));

    try {
      const res = await fetch(API_URL, {
        method: "POST",
        body: formData,
      });
      const data = await res.json();
      if (data.success) {
        setMessage({ type: "success", text: "Campaign added successfully!" });
        setModalOpen(false);
        setForm(emptyCampaign);
        setAttachmentFile(null);
        setUploadedImages([]); // Clear uploaded images
        fetchCampaigns();
      } else {
        setMessage({
          type: "error",
          text: data.message || "Failed to add campaign.",
        });
      }
    } catch {
      setMessage({ type: "error", text: "Failed to add campaign." });
    }
  };

  // Edit campaign
  const handleEdit = (campaign) => {
    setEditId(campaign.campaign_id);
    setForm({
      description: campaign.description,
      mail_subject: campaign.mail_subject,
      mail_body: campaign.mail_body,
      attachment: null,
      existing_attachment: campaign.attachment_path || null, // Track existing attachment
      images: campaign.images_paths ? JSON.parse(campaign.images_paths) : [],
    });
    setAttachmentFile(null);
    setUploadedImages([]); // Reset for edit mode
    setEditModalOpen(true);
  };

  // Update campaign
  const handleUpdate = async (e) => {
    e.preventDefault();
    const formData = new FormData();
    formData.append("description", form.description);
    formData.append("mail_subject", form.mail_subject);
    formData.append("mail_body", form.mail_body);
    formData.append("send_as_html", "1"); // Always send as HTML for rich content
    
    if (attachmentFile) {
      formData.append("attachment", attachmentFile);
    }

    // ALWAYS send uploaded images as JSON array (combine existing + newly uploaded)
    const allImages = [...(form.images || []), ...uploadedImages];
    formData.append("images_json", JSON.stringify(allImages));
    
    // Tell backend this is an update
    formData.append("_method", "PUT");

    try {
      const res = await fetch(`${API_URL}?id=${editId}`, {
        method: "POST", // Still POST for file upload, backend checks _method
        body: formData,
      });
      const data = await res.json();
      if (data.success) {
        setMessage({ type: "success", text: "Campaign updated successfully!" });
        setEditModalOpen(false);
        setForm(emptyCampaign);
        setAttachmentFile(null);
        setUploadedImages([]); // Clear uploaded images
        fetchCampaigns();
      } else {
        setMessage({
          type: "error",
          text: data.message || "Failed to update campaign.",
        });
      }
    } catch {
      setMessage({ type: "error", text: "Failed to update campaign." });
    }
  };

  // Delete campaign (optimistic UI update)
  const handleDelete = async (id) => {
    // Optimistically remove from UI
    setCampaigns((prev) => prev.filter((c) => c.campaign_id !== id));
    setPagination((prev) => ({
      ...prev,
      total: prev.total - 1,
    }));

    try {
      const res = await fetch(`${API_URL}?id=${id}`, { method: "DELETE" });
      const data = await res.json();

      if (data.success) {
        setMessage({ type: "success", text: "Campaign deleted successfully!" });
        // No need to reload, already removed
      } else {
        setMessage({
          type: "error",
          text: data.message || "Failed to delete campaign.",
        });
        // Restore if failed
        fetchCampaigns();
      }
    } catch {
      setMessage({ type: "error", text: "Failed to delete campaign." });
      fetchCampaigns();
    }
  };


  // Reuse campaign
  const handleReuse = async (id) => {
    try {
      const res = await fetch(`${API_URL}?id=${id}`);
      const data = await res.json();
      setForm({
        description: data.description,
        mail_subject: data.mail_subject,
        mail_body: data.mail_body,
        attachment: null,
        images: [], // Don't copy images, user will re-upload if needed
      });
      setEditId(null); // <-- Clear editId so handleAdd will be used
      setAttachmentFile(null); // <-- Clear any previous file
      setUploadedImages([]); // Clear uploaded images
      setModalOpen(true);
    } catch {
      setMessage({ type: "error", text: "Failed to load campaign for reuse." });
    }
  };

  // Auto-hide message after 3 seconds
  useEffect(() => {
    if (message) {
      const timer = setTimeout(() => setMessage(null), 3000);
      return () => clearTimeout(timer);
    }
  }, [message]);

  // Preview first 30 words (strip HTML tags for clean display)
  const preview = (body) => {
    if (!body) return "";
    
    // Create a temporary div to parse HTML and extract text
    const tempDiv = document.createElement('div');
    tempDiv.innerHTML = body;
    
    // Get text content (strips all HTML tags)
    const textContent = tempDiv.textContent || tempDiv.innerText || "";
    
    // Split into words and take first 30
    const words = textContent.trim().split(/\s+/).filter(word => word.length > 0);
    const preview = words.slice(0, 30).join(" ");
    
    return preview + (words.length > 30 ? "..." : "");
  };

  // Pagination logic
  const totalPages = Math.max(1, Math.ceil(pagination.total / pagination.rowsPerPage));
  const paginatedCampaigns = campaigns.slice(
    (pagination.page - 1) * pagination.rowsPerPage,
    pagination.page * pagination.rowsPerPage
  );

  return (
    <div className="container mx-auto mt-12 px-2 sm:px-4 py-8 max-w-7xl">
      {/* Status Message */}
      <StatusMessage message={message} onClose={() => setMessage(null)} />

      <div className="flex justify-between items-center mb-6">
        <h1 className="text-2xl font-bold text-gray-800">
          <i className="fas fa-bullhorn mr-2 text-blue-600"></i>
          Email Campaigns
        </h1>
        <button
          onClick={() => {
            setForm(emptyCampaign);
            setModalOpen(true);
          }}
          className="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-md flex items-center"
        >
          <i className="fas fa-plus mr-2"></i> Add Campaign
        </button>
      </div>

      {/* Responsive Campaigns List */}
      <div className="block sm:hidden">
        {paginatedCampaigns.map((c) => (
          <div
            key={c.campaign_id}
            className="bg-white rounded-2xl shadow p-4 mb-4 flex flex-col"
          >
            <div className="flex justify-between items-start">
              <div>
                <div className="text-lg font-bold text-gray-900 mb-1">
                  ID: {c.campaign_id}
                </div>
                <div className="font-semibold text-gray-900">Description:</div>
                <div className="text-gray-600 text-base mb-2 break-words">
                  {c.description}
                </div>
              </div>
              <div className="flex flex-col gap-2 items-end ml-2">
                <button
                  onClick={() => handleEdit(c)}
                  className="text-blue-600 hover:text-blue-800 p-1 rounded"
                  title="Edit"
                >
                  <i className="fas fa-edit text-xl"></i>
                </button>
                <button
                  onClick={() => handleReuse(c.campaign_id)}
                  className="text-green-600 hover:text-green-800 p-1 rounded"
                  title="Reuse"
                >
                  <i className="fas fa-copy text-xl"></i>
                </button>
                <button
                  onClick={() => {
                    if (window.confirm("Are you sure you want to delete this campaign?")) {
                      handleDelete(c.campaign_id);
                    }
                  }}
                  className="text-red-600 hover:text-red-800 p-1 rounded"
                  title="Delete"
                >
                  <i className="fas fa-trash text-xl"></i>
                </button>
              </div>
            </div>
            <div className="mt-2">
              <div className="font-semibold text-gray-900 text-xs uppercase tracking-wide mb-1">Subject:</div>
              <div className="text-gray-700 text-sm break-words mb-3">{c.mail_subject}</div>
              <div className="font-semibold text-gray-900 text-xs uppercase tracking-wide mb-1">Email Preview:</div>
              <div className="text-gray-600 text-sm leading-relaxed" style={{
                display: '-webkit-box',
                WebkitLineClamp: 3,
                WebkitBoxOrient: 'vertical',
                overflow: 'hidden'
              }}>{preview(c.mail_body)}</div>
              {c.attachment_path && (
                <div className="mt-2 flex items-center gap-2">
                  <span className="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium bg-blue-100 text-blue-800">
                    <i className="fas fa-paperclip mr-1"></i>
                    Has Attachment
                  </span>
                </div>
              )}
            </div>
          </div>
        ))}
      </div>

      {/* Desktop Table */}
      <div className="hidden sm:block bg-white rounded-lg shadow overflow-hidden">
        <div className="overflow-x-auto">
          <table className="min-w-full divide-y divide-gray-200">
            <thead className="bg-gray-50">
              <tr>
                <th className="w-16 px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                  ID
                </th>
                <th className="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                  Description
                </th>
                <th className="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                  Subject
                </th>
                <th className="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                  Email Preview
                </th>
                <th className="w-40 px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">
                  Actions
                </th>
              </tr>
            </thead>
            <tbody className="bg-white divide-y divide-gray-200">
              {loading ? (
                <tr>
                  <td
                    colSpan={5}
                    className="px-6 py-4 text-center text-sm text-gray-500"
                  >
                    Loading...
                  </td>
                </tr>
              ) : campaigns.length === 0 ? (
                <tr>
                  <td
                    colSpan={5}
                    className="px-6 py-4 text-center text-sm text-gray-500"
                  >
                    No campaigns found. Add one to get started.
                  </td>
                </tr>
              ) : (
                paginatedCampaigns.map((c) => (
                  <tr
                    key={c.campaign_id}
                    className="hover:bg-gray-50 transition-colors duration-150"
                  >
                    <td className="px-4 py-3 whitespace-nowrap text-sm font-medium text-gray-500">
                      {c.campaign_id}
                    </td>
                    <td className="px-4 py-3">
                      <div className="text-sm font-medium text-gray-900 truncate max-w-xs">
                        {c.description}
                      </div>
                    </td>
                    <td className="px-4 py-3">
                      <div className="text-sm text-gray-900 truncate max-w-xs">
                        {c.mail_subject}
                      </div>
                    </td>
                    <td className="px-4 py-3">
                      <div
                        className="text-sm text-gray-600 max-w-xs line-clamp-2"
                        title={preview(c.mail_body)}
                        style={{
                          display: '-webkit-box',
                          WebkitLineClamp: 2,
                          WebkitBoxOrient: 'vertical',
                          overflow: 'hidden',
                          textOverflow: 'ellipsis'
                        }}
                      >
                        {preview(c.mail_body)}
                      </div>
                      {c.attachment_path && (
                        <span className="inline-flex items-center px-2 py-0.5 mt-1 rounded text-xs font-medium bg-blue-100 text-blue-800">
                          <i className="fas fa-paperclip mr-1"></i>
                          Attachment
                        </span>
                      )}
                    </td>
                    <td className="px-4 py-3 whitespace-nowrap text-right text-sm font-medium">
                      <div className="flex justify-end space-x-2">
                        <button
                          onClick={() => handleEdit(c)}
                          className="text-blue-600 hover:text-blue-800 p-1 rounded hover:bg-blue-50"
                          title="Edit"
                        >
                          <i className="fas fa-edit"></i>
                        </button>
                        <button
                          onClick={() => handleReuse(c.campaign_id)}
                          className="text-green-600 hover:text-green-800 p-1 rounded hover:bg-green-50"
                          title="Reuse"
                        >
                          <i className="fas fa-copy"></i>
                        </button>
                        <button
                          onClick={() => {
                            if (window.confirm("Are you sure you want to delete this campaign?")) {
                              handleDelete(c.campaign_id);
                            }
                          }}
                          className="text-red-600 hover:text-red-800 p-1 rounded hover:bg-red-50"
                          title="Delete"
                        >
                          <i className="fas fa-trash"></i>
                        </button>
                      </div>
                    </td>
                  </tr>
                ))
              )}
            </tbody>
          </table>
        </div>
      </div>

      {/* Pagination Controls */}
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
          <div className="flex flex-wrap items-center gap-2 pb-5">
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

      {/* Add Campaign Modal */}
      {modalOpen && (
        <div className="fixed inset-0 bg-black/40 backdrop-blur-sm overflow-y-auto h-full w-full z-50">
          <div className="min-h-screen px-4 text-center">
            {/* This element is to trick the browser into centering the modal contents. */}
            <span className="inline-block h-screen align-middle" aria-hidden="true">&#8203;</span>
            <div className="inline-block w-11/12 md:w-3/4 lg:w-2/3 max-w-5xl my-8 text-left align-middle transition-all transform bg-white shadow-2xl rounded-xl">
              <div className="sticky top-0 z-10 bg-white px-6 py-4 border-b border-gray-200 rounded-t-xl">
                <div className="flex justify-between items-center">
                  <h3 className="text-xl font-semibold text-gray-900 flex items-center gap-2">
                    <i className="fas fa-plus-circle text-blue-600"></i>
                    Add New Campaign
                  </h3>
                  <button
                    onClick={() => setModalOpen(false)}
                    className="text-gray-400 hover:text-gray-500 p-2 rounded-lg hover:bg-gray-100 transition-colors"
                    aria-label="Close"
                  >
                    <i className="fas fa-times text-lg"></i>
                  </button>
                </div>
              </div>
            <form onSubmit={handleAdd} encType="multipart/form-data" className="px-6 py-4">
              <div className="space-y-6">
                <div className="grid grid-cols-1 gap-6">
                  <div>
                    <label className="block text-sm font-medium text-gray-900 mb-2">
                      Description
                    </label>
                    <input
                      type="text"
                      name="description"
                      required
                      className="block w-full px-4 py-3 bg-gray-50 border border-gray-300 rounded-lg shadow-sm focus:outline-none focus:ring-2 focus:ring-blue-500/50 focus:border-blue-500 transition-colors sm:text-sm"
                      placeholder="Enter campaign description"
                      value={form.description}
                      onChange={handleChange}
                    />
                  </div>
                  <div>
                    <label className="block text-sm font-medium text-gray-900 mb-2">
                      Email Subject
                    </label>
                    <input
                      type="text"
                      name="mail_subject"
                      required
                      className="block w-full px-4 py-3 bg-gray-50 border border-gray-300 rounded-lg shadow-sm focus:outline-none focus:ring-2 focus:ring-blue-500/50 focus:border-blue-500 transition-colors sm:text-sm"
                      placeholder="Enter email subject line"
                      value={form.mail_subject}
                      onChange={handleChange}
                    />
                  </div>
                  <div>
                    <label className="block text-sm font-medium text-gray-900 mb-2">
                      Email Body
                    </label>
                    <div className="bg-white border border-gray-300 rounded-lg overflow-hidden shadow-sm" style={{ zIndex: 1001 }}>
                      <QuillEditor
                        value={form.mail_body}
                        onChange={(html) => setForm(prev => ({ ...prev, mail_body: html }))}
                        onImageUpload={handleImageUpload}
                        uploadImageUrl={UPLOAD_IMAGE_URL}
                        modules={quillModules}
                        formats={quillFormats}
                        placeholder="Compose your email content here..."
                      />
                    </div>
                  </div>
                  <div>
                    <label className="block text-sm font-medium text-gray-900 mb-2">
                      Attachment (Optional)
                    </label>
                    <div className="flex items-center space-x-2">
                      <label className="flex-1">
                        <span className="sr-only">Choose file</span>
                        <input
                          type="file"
                          name="attachment"
                          onChange={handleChange}
                          accept=".pdf,.jpg,.jpeg,.png,.doc,.docx,.xls,.xlsx,.csv,.txt"
                          id="attachment-input"
                          className="block w-full text-sm text-gray-500 file:mr-4 file:py-2 file:px-4 file:rounded-md file:border-0 file:text-sm file:font-semibold file:bg-blue-50 file:text-blue-700 hover:file:bg-blue-100 focus:outline-none"
                        />
                      </label>
                      {attachmentFile && (
                        <button
                          type="button"
                          onClick={() => setAttachmentFile(null)}
                          className="p-2 text-gray-400 hover:text-gray-500"
                          title="Remove attachment"
                        >
                          <i className="fas fa-times"></i>
                        </button>
                      )}
                    </div>
                    {attachmentFile && (
                      <div className="mt-2 text-sm text-gray-600">
                        <i className="fas fa-paperclip mr-2"></i>
                        {attachmentFile.name}
                      </div>
                    )}
                  </div>
                </div>
              </div>
              <div className="mt-6 border-t border-gray-200 pt-6 flex items-center justify-end gap-3">
                <button
                  type="button"
                  onClick={() => setModalOpen(false)}
                  className="px-4 py-2 border border-gray-300 rounded-lg text-sm font-medium text-gray-700 hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 transition-colors"
                >
                  Cancel
                </button>
                <button
                  type="submit"
                  className="px-4 py-2 bg-blue-600 text-white text-sm font-medium rounded-lg hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 transition-colors inline-flex items-center"
                >
                  <i className="fas fa-save mr-2"></i>
                  Save Campaign
                </button>
              </div>
            </form>
            </div>
          </div>
        </div>
      )}

      {/* Edit Campaign Modal */}
      {editModalOpen && (
        <div className="fixed inset-0 bg-black/40 backdrop-blur-sm overflow-y-auto h-full w-full z-50">
          <div className="min-h-screen px-4 text-center">
            <span className="inline-block h-screen align-middle" aria-hidden="true">&#8203;</span>
            <div className="inline-block w-11/12 md:w-3/4 lg:w-2/3 max-w-5xl my-8 text-left align-middle transition-all transform bg-white shadow-2xl rounded-xl">
              <div className="sticky top-0 z-10 bg-white px-6 py-4 border-b border-gray-200 rounded-t-xl">
                <div className="flex justify-between items-center">
                  <h3 className="text-xl font-semibold text-gray-900 flex items-center gap-2">
                    <i className="fas fa-edit text-blue-600"></i>
                    Edit Campaign
                  </h3>
                  <button
                    onClick={() => setEditModalOpen(false)}
                    className="text-gray-400 hover:text-gray-500 p-2 rounded-lg hover:bg-gray-100 transition-colors"
                    aria-label="Close"
                  >
                    <i className="fas fa-times text-lg"></i>
                  </button>
                </div>
              </div>
              
              <form onSubmit={handleUpdate} encType="multipart/form-data" className="px-6 py-4">
                <div className="space-y-6">
                  <div className="grid grid-cols-1 gap-6">
                    <div>
                      <label className="block text-sm font-medium text-gray-900 mb-2">
                        Description
                      </label>
                      <input
                        type="text"
                        name="description"
                        required
                        className="block w-full px-4 py-3 bg-gray-50 border border-gray-300 rounded-lg shadow-sm focus:outline-none focus:ring-2 focus:ring-blue-500/50 focus:border-blue-500 transition-colors sm:text-sm"
                        value={form.description}
                        onChange={handleChange}
                      />
                    </div>
                    <div>
                      <label className="block text-sm font-medium text-gray-900 mb-2">
                        Email Subject
                      </label>
                      <input
                        type="text"
                        name="mail_subject"
                        required
                        className="block w-full px-4 py-3 bg-gray-50 border border-gray-300 rounded-lg shadow-sm focus:outline-none focus:ring-2 focus:ring-blue-500/50 focus:border-blue-500 transition-colors sm:text-sm"
                        value={form.mail_subject}
                        onChange={handleChange}
                      />
                    </div>
                    <div>
                      <label className="block text-sm font-medium text-gray-900 mb-2">
                        Email Body (Use image button in toolbar to add images)
                      </label>
                      <div className="bg-white border border-gray-300 rounded-lg overflow-hidden shadow-sm" style={{ zIndex: 1001 }}>
                        <QuillEditor
                          value={form.mail_body}
                          onChange={(html) => setForm(prev => ({ ...prev, mail_body: html }))}
                          onImageUpload={handleImageUpload}
                          uploadImageUrl={UPLOAD_IMAGE_URL}
                          modules={quillModules}
                          formats={quillFormats}
                        />
                      </div>
                    </div>
                    <div>
                      <label className="block text-sm font-medium text-gray-900 mb-2">
                        Attachment (Optional)
                      </label>
                      <div className="flex items-center space-x-2">
                        <label className="flex-1">
                          <span className="sr-only">Choose file</span>
                          <input
                            type="file"
                            name="attachment"
                            onChange={handleChange}
                            accept=".pdf,.jpg,.jpeg,.png,.doc,.docx,.xls,.xlsx,.csv,.txt"
                            id="edit-attachment-input"
                            className="block w-full text-sm text-gray-500 file:mr-4 file:py-2 file:px-4 file:rounded-md file:border-0 file:text-sm file:font-semibold file:bg-blue-50 file:text-blue-700 hover:file:bg-blue-100 focus:outline-none"
                          />
                        </label>
                        {attachmentFile && (
                          <button
                            type="button"
                            onClick={() => setAttachmentFile(null)}
                            className="p-2 text-gray-400 hover:text-gray-500"
                            title="Remove attachment"
                          >
                            <i className="fas fa-times"></i>
                          </button>
                        )}
                      </div>
                      {attachmentFile && (
                        <div className="mt-2 text-sm text-gray-600">
                          <i className="fas fa-paperclip mr-2"></i>
                          {attachmentFile.name}
                        </div>
                      )}
                      {!attachmentFile && form.existing_attachment && (
                        <div className="mt-2 text-sm text-gray-600 border-t pt-2">
                          <p className="font-medium text-gray-700 mb-1">Existing attachment:</p>
                          <div className="flex items-center justify-between bg-gray-50 rounded px-3 py-2">
                            <div className="flex items-center">
                              <i className="fas fa-file mr-2 text-blue-600"></i>
                              <span className="text-xs">{form.existing_attachment.split('/').pop()}</span>
                            </div>
                            <a
                              href={`${BASE_URL}/backend/${form.existing_attachment}`}
                              target="_blank"
                              rel="noopener noreferrer"
                              className="text-blue-600 hover:text-blue-700 text-xs"
                            >
                              <i className="fas fa-download"></i> Download
                            </a>
                          </div>
                          <p className="text-xs text-gray-500 mt-1">
                            <i className="fas fa-info-circle mr-1"></i>
                            Upload a new file to replace this attachment
                          </p>
                        </div>
                      )}
                    </div>
                  </div>
                </div>
                <div className="mt-6 border-t border-gray-200 pt-6 flex items-center justify-end gap-3">
                  <button
                    type="button"
                    onClick={() => setEditModalOpen(false)}
                    className="px-4 py-2 border border-gray-300 rounded-lg text-sm font-medium text-gray-700 hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 transition-colors"
                  >
                    Cancel
                  </button>
                  <button
                    type="submit"
                    className="px-4 py-2 bg-blue-600 text-white text-sm font-medium rounded-lg hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 transition-colors inline-flex items-center"
                  >
                    <i className="fas fa-save mr-2"></i>
                    Update Campaign
                  </button>
                </div>
              </form>
            </div>
          </div>
        </div>
      )}
    </div>
  );
};

export default Campaigns;
