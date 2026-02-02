import React, { useEffect, useState } from "react";

import RichTextEditor from "../components/RichTextEditor";
import { API_CONFIG } from "../config";

const emptyCampaign = {
  description: "",
  mail_subject: "",
  mail_body: "",
  attachment: null,
  existing_attachment: null, // Track existing attachment path
  images: [], // Track uploaded image paths
  template_id: null, // Template selection
  import_batch_id: null, // Imported recipients batch ID
};

// Glassmorphism Status Message Popup
const StatusMessage = ({ message, onClose }) =>
  message && (
    <div
      className={`
        fixed top-6 left-1/2 transform -translate-x-1/2
        px-6 py-3 rounded-xl shadow-lg text-base font-bold
        flex items-center gap-3
        transition-all duration-300
        backdrop-blur-md
        ${message.type === "error"
          ? "bg-red-50 border-2 border-red-500 text-red-700"
          : "bg-green-50 border-2 border-green-500 text-green-700"
        }
      `}
      style={{
        minWidth: 250,
        maxWidth: 600,
        zIndex: 99999,
        boxShadow: message.type === "error" 
          ? "0 8px 32px 0 rgba(220, 38, 38, 0.4)"
          : "0 8px 32px 0 rgba(34, 197, 94, 0.4)",
        background:
          message.type === "error"
            ? "rgba(254, 226, 226, 0.95)"
            : "rgba(220, 252, 231, 0.95)",
        borderRadius: "16px",
        backdropFilter: "blur(8px)",
        WebkitBackdropFilter: "blur(8px)",
      }}
      role="alert"
    >
      <i
        className={`fas text-xl ${message.type === "error"
          ? "fa-exclamation-circle text-red-600"
          : "fa-check-circle text-green-600"
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
  const [_uploadedImages, setUploadedImages] = useState([]); // Track images uploaded via Quill (unused directly)
  const [editId, setEditId] = useState(null);
  const [message, setMessage] = useState(null);
  const [templates, setTemplates] = useState([]); // Mail templates
  const [importBatches, setImportBatches] = useState([]); // Imported data batches
  const [importModalOpen, setImportModalOpen] = useState(false);
  const [uploadFile, setUploadFile] = useState(null);
  const [importing, setImporting] = useState(false);
  // Preview modal state
  const [previewModalOpen, setPreviewModalOpen] = useState(false);
  const [previewHtml, setPreviewHtml] = useState('');
  const [previewLoading, setPreviewLoading] = useState(false);
  // Status tracking removed; only Master page shows run state

  // Pagination state
  const [pagination, setPagination] = useState({
    page: 1,
    rowsPerPage: 10,
    total: 0,
  });

  // Use centralized configuration
  const BASE_URL = API_CONFIG.BASE_URL;
  const API_URL_CRUD = API_CONFIG.API_CAMPAIGNS; // For create, read, update, delete, and listing with previews
  const _API_URL_OPERATIONS = API_CONFIG.API_MASTER_CAMPAIGNS; // For start, pause, list status (not used on this page)
  const UPLOAD_IMAGE_URL = API_CONFIG.UPLOAD_IMAGE;

  // Fetch mail templates
  const fetchTemplates = React.useCallback(async () => {
    try {
      const res = await fetch(`${BASE_URL}/backend/includes/mail_templates.php?action=list`, {
        credentials: 'include'
      });
      const data = await res.json();
      if (data.success) {
        setTemplates(data.templates.filter(t => t.is_active == 1 || t.is_active === '1'));
      }
    } catch (error) {
      console.error('Failed to load templates:', error);
    }
  }, [BASE_URL]);

  // Fetch import batches
  const fetchImportBatches = React.useCallback(async () => {
    try {
      const res = await fetch(`${API_CONFIG.API_IMPORT_DATA}?action=list&source=campaign`, {
        credentials: 'include'
      });
      const data = await res.json();
      if (data.success) {
        setImportBatches(data.batches || []);
      }
    } catch (error) {
      console.error('Failed to load import batches:', error);
    }
  }, [BASE_URL]);

  // Handle file import - imports to imported_recipients table
  const handleImportFile = async () => {
    if (!uploadFile) {
      setMessage({ type: 'error', text: 'Please select a CSV or Excel file to import' });
      return;
    }

    const ext = uploadFile.name.split('.').pop().toLowerCase();
    if (!['csv', 'xlsx', 'xls'].includes(ext)) {
      setMessage({ type: 'error', text: 'Please select a CSV or Excel (.xlsx, .xls) file' });
      return;
    }

    setImporting(true);

    const formData = new FormData();
    formData.append('csv_file', uploadFile);

    try {
      // Use import_recipients endpoint which saves to imported_recipients table
      const res = await fetch(`${BASE_URL}/backend/api/import_recipients.php`, {
        method: 'POST',
        body: formData,
        credentials: 'include'
      });

      const data = await res.json();

      if (data.success && data.status === 'success') {
        setMessage({ 
          type: 'success', 
          text: `✓ Imported ${data.data.imported} records! Batch ID: ${data.data.import_batch_id}` 
        });
        setImportModalOpen(false);
        setUploadFile(null);
        fetchImportBatches(); // Reload batches
      } else {
        setMessage({ type: 'error', text: data.error || data.message || 'Import failed' });
      }
    } catch (error) {
      console.error('Import error:', error);
      setMessage({ type: 'error', text: 'Failed to import file: ' + error.message });
    } finally {
      setImporting(false);
    }
  };

  useEffect(() => {
    fetchTemplates();
  }, [fetchTemplates]);

  // Fetch campaigns list (from CRUD endpoint to include mail_body and mail_body_preview)
  const fetchCampaigns = React.useCallback(async () => {
    setLoading(true);
    try {
      const res = await fetch(API_URL_CRUD, { 
        method: 'GET',
        credentials: 'include'
      });
      
      if (!res.ok) {
        throw new Error(`HTTP error! status: ${res.status}`);
      }
      
      const data = await res.json();
      // This endpoint returns an array of campaigns directly
      const campaignsList = Array.isArray(data) ? data : [];
      setCampaigns(campaignsList);
      setPagination((prev) => ({
        ...prev,
        total: campaignsList.length,
      }));
      
      // Clear any previous errors on successful load
      if (message?.type === 'error' && message?.text?.includes('Failed to load')) {
        setMessage(null);
      }
    } catch (error) {
      console.error('Failed to load campaigns:', error);
      setCampaigns([]);
      setPagination((prev) => ({
        ...prev,
        total: 0,
      }));
      // Only show error for actual network/server errors
      setMessage({ type: "error", text: "Failed to load data: Network Error. Please check your connection." });
    } finally {
      setLoading(false);
    }
  }, [API_URL_CRUD]);

  useEffect(() => {
    fetchCampaigns();
    fetchTemplates();
    fetchImportBatches();
  }, [fetchCampaigns, fetchTemplates, fetchImportBatches]);

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
    // Normalize path: strip any localhost or BASE_URL prefix to get clean relative path
    const normalizedPath = normalizeImagePath(imagePath);
    setUploadedImages(prev => [...prev, normalizedPath]);
  };

  // Normalize image path to standard relative format: storage/images/filename.jpg
  const normalizeImagePath = (path) => {
    if (!path) return path;
    
    // Remove any protocol and domain
    let normalized = path.replace(/^https?:\/\/[^/]+/i, '');
    
    // Remove common prefixes - handle both MailPilot_CRM and MailPilot_CRM_S
    normalized = normalized.replace(/^\/verify_emails\/MailPilot_CRM(_S)?\/backend\//i, '');
    normalized = normalized.replace(/^\/backend\//i, '');
    normalized = normalized.replace(/^backend\//i, '');
    
    // Ensure it starts with storage/ if it contains storage/
    if (normalized.includes('storage/') && !normalized.startsWith('storage/')) {
      normalized = normalized.substring(normalized.indexOf('storage/'));
    }
    
    // Clean up any leading slashes
    normalized = normalized.replace(/^\/+/, '');
    
    return normalized;
  };

  // Helper function to replace localhost URLs with relative paths in HTML body
  const replaceLocalhostWithRelativePaths = (htmlBody) => {
    if (!htmlBody) return htmlBody;
    
    // Replace all absolute URLs pointing to our backend storage with relative paths
    let processed = htmlBody;
    
    // Pattern 1: Full localhost URLs - handle both MailPilot_CRM and MailPilot_CRM_S
    processed = processed.replace(
      /http:\/\/localhost\/verify_emails\/MailPilot_CRM(_S)?\/backend\/(storage\/[^"'\s>]+)/gi,
      '$2'
    );
    
    // Pattern 2: Protocol-relative or absolute paths
    processed = processed.replace(
      /\/verify_emails\/MailPilot_CRM(_S)?\/backend\/(storage\/[^"'\s>]+)/gi,
      '$2'
    );
    
    // Pattern 3: Just /backend/ prefix
    processed = processed.replace(
      /\/backend\/(storage\/[^"'\s>]+)/gi,
      '$1'
    );
    
    return processed;
  };

  // CRITICAL: Extract all image paths from HTML content
  const extractImagesFromHtml = (htmlBody) => {
    if (!htmlBody) return [];
    
    const images = [];
    const tempDiv = document.createElement('div');
    tempDiv.innerHTML = htmlBody;
    
    // Find all <img> tags
    const imgTags = tempDiv.querySelectorAll('img');
    imgTags.forEach(img => {
      let src = img.getAttribute('src');
      if (src && src.includes('storage/images/')) {
        // Normalize the path
        const normalized = normalizeImagePath(src);
        if (normalized && !images.includes(normalized)) {
          images.push(normalized);
        }
      }
    });
    
    console.log('Extracted images from HTML:', images);
    return images;
  };

  const ensureAbsoluteBackendImagePaths = (htmlBody) => {
    if (!htmlBody) return htmlBody;
    
    // Replace relative paths with absolute URLs
    let processed = htmlBody.replace(
      /src=(["'])(storage\/images\/[^"']+)\1/gi,
      (_, quote, relativePath) => `src=${quote}${BASE_URL}/backend/${relativePath}${quote}`
    );
    
    // Also handle paths that might already have /backend/ but missing the base URL
    processed = processed.replace(
      /src=(["'])\/backend\/(storage\/images\/[^"']+)\1/gi,
      (_, quote, path) => `src=${quote}${BASE_URL}/backend/${path}${quote}`
    );
    
    return processed;
  };

  // Add campaign
  const handleAdd = async (e) => {
    e.preventDefault();
    
    // Validate: Either template_id or mail_body must be provided
    if (!form.template_id && (!form.mail_body || form.mail_body.trim() === '')) {
      setMessage({ type: "error", text: "Please select a template or compose an email body." });
      return;
    }
    
    // Replace localhost URLs with relative paths before sending
    const processedBody = replaceLocalhostWithRelativePaths(form.mail_body);
    
    // CRITICAL FIX: Extract ALL images from HTML body
    // This ensures images_paths is populated even if uploadedImages tracking failed
    const extractedImages = extractImagesFromHtml(processedBody);
    
    const formData = new FormData();
    formData.append("description", form.description);
    formData.append("mail_subject", form.mail_subject);
    formData.append("mail_body", processedBody || '');
    formData.append("send_as_html", "1"); // Always send as HTML for rich content
    
    // Add template_id if selected
    if (form.template_id) {
      formData.append("template_id", form.template_id);
    }
    
    // Add import_batch_id if selected
    if (form.import_batch_id) {
      formData.append("import_batch_id", form.import_batch_id);
    }
    
    if (attachmentFile) {
      formData.append("attachment", attachmentFile);
    }

    // Send extracted images from HTML (most reliable method)
    formData.append("images_json", JSON.stringify(extractedImages));

    try {
      const res = await fetch(API_URL_CRUD, {
        method: "POST",
        body: formData,
        credentials: 'include'
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
  const handleEdit = async (campaign) => {
    try {
      // Fetch full campaign data including complete mail_body
      const fetchUrl = `${API_URL_CRUD}&id=${campaign.campaign_id}`;
      const res = await fetch(fetchUrl, {
        credentials: 'include'
      });
      const data = await res.json();
      
      if (!data || data.error) {
        setMessage({ type: "error", text: "Failed to load campaign data." });
        return;
      }
      
      setEditId(campaign.campaign_id);
      const images = data.images_paths ? JSON.parse(data.images_paths) : [];
      let mail_body = ensureAbsoluteBackendImagePaths(data.mail_body || '');
      
      // Insert images into mail_body if not present
      images.forEach(imgPath => {
        const fullUrl = `${BASE_URL}/backend/${imgPath}`;
        if (mail_body.indexOf(fullUrl) === -1 && mail_body.indexOf(imgPath) === -1) {
          mail_body += `<p><img src='${fullUrl}' style='max-width:300px;'/></p>`;
        }
      });
      
      setForm({
        description: data.description,
        mail_subject: data.mail_subject,
        mail_body,
        attachment: null,
        existing_attachment: data.attachment_path || null,
        images,
        template_id: data.template_id || null,
        import_batch_id: data.import_batch_id || null,
      });
      setAttachmentFile(null);
      setUploadedImages([]);
      setEditModalOpen(true);
    } catch (error) {
      console.error('Failed to load campaign for edit:', error);
      setMessage({ type: "error", text: "Failed to load campaign data." });
    }
  };

  // Update campaign
  const handleUpdate = async (e) => {
    e.preventDefault();
    
    // Replace localhost URLs with relative paths before sending
    const processedBody = replaceLocalhostWithRelativePaths(form.mail_body);
    
    const formData = new FormData();
    formData.append("description", form.description);
    formData.append("mail_subject", form.mail_subject);
    formData.append("mail_body", processedBody || '');
    formData.append("send_as_html", "1"); // Always send as HTML for rich content
    
    if (form.template_id) {
      formData.append("template_id", form.template_id);
    }
    
    if (form.import_batch_id) {
      formData.append("import_batch_id", form.import_batch_id);
    }
    
    if (attachmentFile) {
      formData.append("attachment", attachmentFile);
    }

    // CRITICAL FIX: Extract ALL images from HTML body (most reliable)
    const extractedImages = extractImagesFromHtml(processedBody);
    formData.append("images_json", JSON.stringify(extractedImages));
    
    // Tell backend this is an update
    formData.append("_method", "PUT");

    try {
      const updateUrl = `${API_URL_CRUD}&id=${editId}`;
      const res = await fetch(updateUrl, {
        method: "POST", // Still POST for file upload, backend checks _method
        body: formData,
        credentials: 'include'
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
      // Properly append id parameter (use & since API_URL_CRUD already has ?)
      const deleteUrl = `${API_URL_CRUD}&id=${id}`;
      const res = await fetch(deleteUrl, { 
        method: "DELETE",
        credentials: 'include'
      });
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
      const fetchUrl = `${API_URL_CRUD}&id=${id}`;
      const res = await fetch(fetchUrl, {
        credentials: 'include'
      });
      const data = await res.json();
      const images = data.images_paths ? JSON.parse(data.images_paths) : [];
      let mail_body = ensureAbsoluteBackendImagePaths(data.mail_body || '');
      images.forEach(imgPath => {
        const fullUrl = `${BASE_URL}/backend/${imgPath}`;
        if (mail_body.indexOf(fullUrl) === -1 && mail_body.indexOf(imgPath) === -1) {
          mail_body += `<p><img src='${fullUrl}' style='max-width:300px;'/></p>`;
        }
      });
      setForm({
        description: data.description,
        mail_subject: data.mail_subject,
        mail_body,
        attachment: null,
        existing_attachment: data.attachment_path || null,
        images,
      });
      setEditId(null); // Clear editId so handleAdd will be used
      setAttachmentFile(null); // Clear any previous file
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

  // Preview first 30 words (prefer server-provided preview when available)
  const preview = (campaign) => {
    // Use precomputed preview if available
    if (campaign?.mail_body_preview) return campaign.mail_body_preview;

    const body = campaign?.mail_body || "";
    if (!body) return "";

    // Create a temporary div to parse HTML and extract text
    const tempDiv = document.createElement('div');
    tempDiv.innerHTML = body;

    // Get text content (strips all HTML tags)
    const textContent = tempDiv.textContent || tempDiv.innerText || "";

    // Split into words and take first 30
    const words = textContent.trim().split(/\s+/).filter(word => word.length > 0);
    const snippet = words.slice(0, 30).join(" ");

    return snippet + (words.length > 30 ? "..." : "");
  };

  // Get merged preview with real data for a specific email
  const getMergedPreview = async (campaign, email = null) => {
    try {
      // If campaign uses template, merge with real data
      if (campaign.template_id && (campaign.import_batch_id || campaign.csv_list_id)) {
        const response = await fetch(`${BASE_URL}/backend/includes/mail_templates.php?action=get&template_id=${campaign.template_id}`, {
          credentials: 'include'
        });
        const templateData = await response.json();
        
        if (templateData.success) {
          // Get sample data from the import/CSV
          let sampleEmail = email;
          
          if (!sampleEmail) {
            // Fetch first email from the batch or CSV list
            if (campaign.import_batch_id) {
              const recipientsRes = await fetch(`${API_CONFIG.API_IMPORT_DATA}?action=get_batch&batch_id=${campaign.import_batch_id}`, {
                credentials: 'include'
              });
              const recipientsData = await recipientsRes.json();
              if (recipientsData.success && recipientsData.recipients && recipientsData.recipients.length > 0) {
                sampleEmail = recipientsData.recipients[0].Emails;
              }
            } else if (campaign.csv_list_id) {
              const emailsRes = await fetch(`${BASE_URL}/backend/includes/get_csv_list.php?list_id=${campaign.csv_list_id}&limit=1`, {
                credentials: 'include'
              });
              const emailsData = await emailsRes.json();
              if (emailsData.emails && emailsData.emails.length > 0) {
                sampleEmail = emailsData.emails[0].raw_emailid;
              }
            }
          }
          
          // Merge template with data for preview
          const previewRes = await fetch(`${BASE_URL}/backend/includes/mail_templates.php?action=merge_preview`, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            credentials: 'include',
            body: JSON.stringify({
              template_html: templateData.template.template_html,
              import_batch_id: campaign.import_batch_id,
              csv_list_id: campaign.csv_list_id
            })
          });
          
          const previewData = await previewRes.json();
          if (previewData.success) {
            return previewData.merged_html;
          }
        }
      }
      
      // Fallback to regular mail_body
      return campaign.mail_body || '';
    } catch (error) {
      console.error('Error getting merged preview:', error);
      return campaign.mail_body || '';
    }
  };

  const handleViewPreview = async (campaign) => {
    setPreviewLoading(true);
    setPreviewModalOpen(true);
    setPreviewHtml(''); // Clear previous content
    try {
      const html = await getMergedPreview(campaign);
      setPreviewHtml(html);
    } catch (error) {
      console.error('Preview error:', error);
      setPreviewHtml('<p>Error loading preview</p>');
    } finally {
      setPreviewLoading(false);
    }
  };

  // Pagination logic
  const totalPages = Math.max(1, Math.ceil(pagination.total / pagination.rowsPerPage));
  const paginatedCampaigns = campaigns.slice(
    (pagination.page - 1) * pagination.rowsPerPage,
    pagination.page * pagination.rowsPerPage
  );

  return (
    <div className="min-h-screen bg-gradient-to-br from-blue-50 via-white to-purple-50">
      <div className="container mx-auto px-2 sm:px-4 py-4 sm:py-6 lg:py-8 max-w-7xl">
        {/* Status Message */}
        <StatusMessage message={message} onClose={() => setMessage(null)} />

        {/* Campaigns Section */}
        <div className="glass-effect rounded-xl shadow-xl border border-white/20 p-5 sm:p-6 lg:p-8 mb-5 sm:mb-6 hover:shadow-2xl transition-all duration-300">
          <div className="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4 mb-6">
            <div className="flex items-center gap-3">
              <div className="bg-gradient-to-br from-blue-500 to-indigo-600 p-3 rounded-xl shadow-lg">
                <svg className="w-6 h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M11 5.882V19.24a1.76 1.76 0 01-3.417.592l-2.147-6.15M18 13a3 3 0 100-6M5.436 13.683A4.001 4.001 0 017 6h1.832c4.1 0 7.625-1.234 9.168-3v14c-1.543-1.766-5.067-3-9.168-3H7a3.988 3.988 0 01-1.564-.317z" />
                </svg>
              </div>
              <h2 className="text-lg sm:text-xl font-bold text-gray-800">Email Campaigns</h2>
            </div>
            <button
              onClick={() => {
                setForm(emptyCampaign);
                setModalOpen(true);
              }}
              className="px-6 py-3 bg-gradient-to-r from-blue-600 to-blue-700 text-white font-semibold rounded-xl shadow-xl hover:shadow-2xl hover:scale-105 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2 transition-all duration-300 flex items-center gap-2"
            >
              <svg className="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M12 4v16m8-8H4" />
              </svg>
              Add Campaign
            </button>
          </div>

      {/* Responsive Campaigns List */}
      <div className="block sm:hidden">
        {paginatedCampaigns.map((c) => (
          <div
            key={c.campaign_id}
            className="glass-effect rounded-xl shadow-lg p-4 mb-4 flex flex-col"
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
                  onClick={() => handleViewPreview(c)}
                  className="text-purple-600 hover:text-purple-800 p-1 rounded"
                  title="View Email Preview"
                >
                  <i className="fas fa-eye text-xl"></i>
                </button>
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
              }}>{preview(c)}</div>
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
      <div className="hidden sm:block glass-effect rounded-xl shadow-lg overflow-hidden">
        <div className="overflow-x-auto">
          <table className="min-w-full divide-y divide-gray-200">
            <thead className="bg-gradient-to-r from-gray-50 to-gray-100">
              <tr>
                <th className="w-16 px-4 py-3.5 text-left text-xs font-bold text-gray-700 uppercase tracking-wider">
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
                // Skeleton loading rows
                Array.from({ length: pagination.rowsPerPage }).map((_, idx) => (
                  <tr key={idx} className="animate-pulse">
                    <td className="px-4 py-3"><div className="h-4 bg-gray-200 rounded w-8"></div></td>
                    <td className="px-4 py-3"><div className="h-4 bg-gray-200 rounded w-3/4"></div></td>
                    <td className="px-4 py-3"><div className="h-4 bg-gray-200 rounded w-2/3"></div></td>
                    <td className="px-4 py-3">
                      <div className="h-4 bg-gray-200 rounded w-full mb-1"></div>
                      <div className="h-4 bg-gray-200 rounded w-5/6"></div>
                    </td>
                    <td className="px-4 py-3">
                      <div className="flex justify-end space-x-2">
                        <div className="h-8 w-8 bg-gray-200 rounded"></div>
                        <div className="h-8 w-8 bg-gray-200 rounded"></div>
                        <div className="h-8 w-8 bg-gray-200 rounded"></div>
                      </div>
                    </td>
                  </tr>
                ))
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
                paginatedCampaigns.map((c, index) => (
                  <tr
                    key={c.campaign_id}
                    className="hover:bg-gray-50 transition-colors duration-150"
                  >
                    <td className="px-2 sm:px-4 py-2 sm:py-3 whitespace-nowrap text-xs sm:text-sm font-medium text-gray-500">
                      {(pagination.page - 1) * pagination.rowsPerPage + index + 1}
                    </td>
                    <td className="px-2 sm:px-4 py-2 sm:py-3">
                      <div className="text-xs sm:text-sm font-medium text-gray-900 truncate max-w-[120px] sm:max-w-xs">
                        {c.description}
                      </div>
                    </td>
                    <td className="px-2 sm:px-4 py-2 sm:py-3">
                      <div className="text-xs sm:text-sm text-gray-900 truncate max-w-[120px] sm:max-w-xs">
                        {c.mail_subject}
                      </div>
                    </td>
                    <td className="px-2 sm:px-4 py-2 sm:py-3 hidden md:table-cell">
                      <div
                        className="text-sm text-gray-600 max-w-xs line-clamp-2"
                        title={preview(c)}
                        style={{
                          display: '-webkit-box',
                          WebkitLineClamp: 2,
                          WebkitBoxOrient: 'vertical',
                          overflow: 'hidden',
                          textOverflow: 'ellipsis'
                        }}
                      >
                        {preview(c)}
                      </div>
                      {c.attachment_path && (
                        <span className="inline-flex items-center px-2 py-0.5 mt-1 rounded text-xs font-medium bg-blue-100 text-blue-800">
                          <i className="fas fa-paperclip mr-1"></i>
                          Attachment
                        </span>
                      )}
                    </td>
                    <td className="px-2 sm:px-4 py-2 sm:py-3 whitespace-nowrap text-right text-sm font-medium">
                      <div className="flex justify-end space-x-1 sm:space-x-2">
                        {/* Send button removed - sending managed in Master page */}
                        <button
                          onClick={() => handleViewPreview(c)}
                          className="text-purple-600 hover:text-purple-800 p-1 rounded hover:bg-purple-50"
                          title="View Email Preview"
                        >
                          <i className="fas fa-eye"></i>
                        </button>
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
        <div className="flex flex-col items-center justify-center mt-6 px-1 gap-2 pb-4">
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

      {/* End Campaigns Section */}
      </div>

      {/* Add Campaign Modal */}
      {modalOpen && (
        <div className="fixed inset-0 z-[9999] bg-black/50 backdrop-blur-sm overflow-y-auto">
          <div className="min-h-screen px-4 text-center">
            <span className="inline-block h-screen align-middle" aria-hidden="true">&#8203;</span>
            <div className="inline-block w-11/12 md:w-3/4 lg:w-2/3 max-w-5xl my-8 text-left align-middle transition-all transform bg-white shadow-2xl rounded-xl">
              <div className="sticky top-0 z-10 px-6 py-4 bg-gradient-to-r from-indigo-50 to-purple-50 border-b border-gray-200 rounded-t-xl">
                <div className="flex justify-between items-center">
                  <div className="flex items-center space-x-3">
                    <div className="bg-white p-2.5 rounded-lg shadow-sm">
                      <i className="fas fa-plus-circle text-indigo-600 text-xl"></i>
                    </div>
                    <div>
                      <h3 className="text-xl font-bold text-gray-900">Add New Campaign</h3>
                      <p className="text-sm text-gray-600 mt-0.5">Create a new email campaign</p>
                    </div>
                  </div>
                  <button
                    onClick={() => setModalOpen(false)}
                    className="text-gray-400 hover:text-gray-600 hover:bg-white/50 rounded-lg p-2 transition-colors"
                  >
                    <i className="fas fa-times text-xl"></i>
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
                      <i className="fas fa-file-code mr-2 text-blue-600"></i>
                      Mail Template (Optional)
                    </label>
                    <select
                      name="template_id"
                      className="block w-full px-4 py-3 bg-gray-50 border border-gray-300 rounded-lg shadow-sm focus:outline-none focus:ring-2 focus:ring-blue-500/50 focus:border-blue-500 transition-colors sm:text-sm"
                      value={form.template_id || ''}
                      onChange={(e) => {
                        const templateId = e.target.value ? parseInt(e.target.value) : null;
                        setForm(prev => ({ ...prev, template_id: templateId }));
                      }}
                    >
                      <option value="">No Template (Use Email Body below)</option>
                      {templates.map(template => (
                        <option key={template.template_id} value={template.template_id}>
                          {template.template_name} {template.merge_fields && template.merge_fields.length > 0 ? `(${template.merge_fields.length} fields)` : ''}
                        </option>
                      ))}
                    </select>
                    {form.template_id && (
                      <div className="mt-2 p-3 bg-green-50 border border-green-200 rounded-lg">
                        <p className="text-sm text-green-800">
                          <i className="fas fa-check-circle mr-2"></i>
                          <strong>Template Selected:</strong> This template will be used as the email body.
                          It will automatically merge with CSV data fields like [[Amount]], [[Days]], [[BilledName]], etc.
                        </p>
                        <p className="text-xs text-green-700 mt-1">
                          <i className="fas fa-database mr-1"></i>
                          Make sure your CSV file has columns matching the template merge fields.
                        </p>
                      </div>
                    )}
                    {!form.template_id && (
                      <p className="mt-2 text-sm text-gray-600">
                        <i className="fas fa-info-circle mr-1"></i>
                        Select a template above to use pre-designed HTML with CSV data merging, or compose email body below.
                      </p>
                    )}
                  </div>
                  <div>
                    <label className="block text-sm font-medium text-gray-900 mb-2">
                      <i className="fas fa-database mr-2 text-purple-600"></i>
                      Import Recipients Data (Optional)
                    </label>
                    <div className="flex gap-2">
                      <select
                        name="import_batch_id"
                        className="flex-1 px-4 py-3 bg-gray-50 border border-gray-300 rounded-lg shadow-sm focus:outline-none focus:ring-2 focus:ring-blue-500/50 focus:border-blue-500 transition-colors sm:text-sm"
                        value={form.import_batch_id || ''}
                        onChange={(e) => {
                          setForm(prev => ({ ...prev, import_batch_id: e.target.value || null }));
                        }}
                      >
                        <option value="">No Imported Data (Use CSV list)</option>
                        {importBatches.map(batch => (
                          <option key={batch.import_batch_id} value={batch.import_batch_id}>
                            {batch.import_filename} ({batch.record_count} records)
                          </option>
                        ))}
                      </select>
                      <button
                        type="button"
                        onClick={() => setImportModalOpen(true)}
                        className="px-4 py-2 bg-purple-600 hover:bg-purple-700 text-white rounded-lg transition-colors"
                        title="Import new Excel/CSV file"
                      >
                        <i className="fas fa-upload mr-2"></i>
                        Import
                      </button>
                    </div>
                    {form.import_batch_id && (
                      <div className="mt-2 p-3 bg-purple-50 border border-purple-200 rounded-lg">
                        <p className="text-sm text-purple-800">
                          <i className="fas fa-check-circle mr-2"></i>
                          <strong>Imported Data Selected:</strong> Template merge fields will use data from this import.
                        </p>
                        <p className="text-xs text-purple-700 mt-1">
                          <i className="fas fa-info-circle mr-1"></i>
                          Emails will be sent to all recipients in this import batch.
                        </p>
                      </div>
                    )}
                    {!form.import_batch_id && (
                      <p className="mt-2 text-sm text-gray-600">
                        <i className="fas fa-info-circle mr-1"></i>
                        Import Excel data or use existing CSV lists for recipients.
                      </p>
                    )}
                  </div>
                  {!form.template_id && (
                  <div>
                    <label className="block text-sm font-medium text-gray-900 mb-2">
                      Email Body <span className="text-red-500">*</span>
                    </label>
                    <div className="bg-white border border-gray-300 rounded-lg overflow-hidden shadow-sm" style={{ zIndex: 1001 }}>
                      <RichTextEditor
                        value={form.mail_body}
                        onChange={(html) => setForm((prev) => ({ ...prev, mail_body: html }))}
                        onImageUpload={handleImageUpload}
                        uploadImageUrl={UPLOAD_IMAGE_URL}
                        placeholder="Compose your email content here..."
                      />
                    </div>
                  </div>
                  )}
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
        <div className="fixed inset-0 z-[9999] bg-black/50 backdrop-blur-sm overflow-y-auto">
          <div className="min-h-screen px-4 text-center">
            <span className="inline-block h-screen align-middle" aria-hidden="true">&#8203;</span>
            <div className="inline-block w-11/12 md:w-3/4 lg:w-2/3 max-w-5xl my-8 text-left align-middle transition-all transform bg-white shadow-2xl rounded-xl">
              <div className="sticky top-0 z-10 px-6 py-4 bg-gradient-to-r from-indigo-50 to-purple-50 border-b border-gray-200 rounded-t-xl">
                <div className="flex justify-between items-center">
                  <div className="flex items-center space-x-3">
                    <div className="bg-white p-2.5 rounded-lg shadow-sm">
                      <i className="fas fa-edit text-indigo-600 text-xl"></i>
                    </div>
                    <div>
                      <h3 className="text-xl font-bold text-gray-900">Edit Campaign</h3>
                      <p className="text-sm text-gray-600 mt-0.5">Update campaign details</p>
                    </div>
                  </div>
                  <button
                    onClick={() => setEditModalOpen(false)}
                    className="text-gray-400 hover:text-gray-600 hover:bg-white/50 rounded-lg p-2 transition-colors"
                  >
                    <i className="fas fa-times text-xl"></i>
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
                        <i className="fas fa-file-code mr-2 text-blue-600"></i>
                        Mail Template (Optional)
                      </label>
                      <select
                        name="template_id"
                        className="block w-full px-4 py-3 bg-gray-50 border border-gray-300 rounded-lg shadow-sm focus:outline-none focus:ring-2 focus:ring-blue-500/50 focus:border-blue-500 transition-colors sm:text-sm"
                        value={form.template_id || ''}
                        onChange={(e) => {
                          const templateId = e.target.value ? parseInt(e.target.value) : null;
                          setForm(prev => ({ ...prev, template_id: templateId }));
                        }}
                      >
                        <option value="">No Template (Use Email Body below)</option>
                        {templates.map(template => (
                          <option key={template.template_id} value={template.template_id}>
                            {template.template_name} {template.merge_fields && template.merge_fields.length > 0 ? `(${template.merge_fields.length} fields)` : ''}
                          </option>
                        ))}
                      </select>
                      {form.template_id && !form.import_batch_id && (
                        <div className="mt-2 p-3 bg-yellow-50 border border-yellow-300 rounded-lg">
                          <p className="text-sm text-yellow-800">
                            <i className="fas fa-exclamation-triangle mr-2"></i>
                            <strong>Warning:</strong> Template selected but no Excel data imported!
                          </p>
                          <p className="text-xs text-yellow-700 mt-1">
                            <i className="fas fa-arrow-down mr-1"></i>
                            Please select an imported data batch below or import a new Excel file.
                          </p>
                        </div>
                      )}
                      {form.template_id && form.import_batch_id && (
                        <div className="mt-2 p-3 bg-green-50 border border-green-200 rounded-lg">
                          <p className="text-sm text-green-800">
                            <i className="fas fa-check-circle mr-2"></i>
                            <strong>Template + Excel Data:</strong> Ready to merge!
                          </p>
                        </div>
                      )}
                      {!form.template_id && (
                        <p className="mt-2 text-sm text-gray-600">
                          <i className="fas fa-info-circle mr-1"></i>
                          If template is selected, it will merge with Excel data fields like [[Amount]], [[Days]], etc.
                        </p>
                      )}
                    </div>
                    <div>
                      <label className="block text-sm font-medium text-gray-900 mb-2">
                        <i className="fas fa-database mr-2 text-purple-600"></i>
                        Import Recipients Data {form.template_id && <span className="text-red-500">* (Required for Template)</span>}
                      </label>
                      <div className="flex gap-2">
                        <select
                          name="import_batch_id"
                          className="flex-1 px-4 py-3 bg-gray-50 border border-gray-300 rounded-lg shadow-sm focus:outline-none focus:ring-2 focus:ring-blue-500/50 focus:border-blue-500 transition-colors sm:text-sm"
                          value={form.import_batch_id || ''}
                          onChange={(e) => {
                            setForm(prev => ({ ...prev, import_batch_id: e.target.value || null }));
                          }}
                        >
                          <option value="">No Imported Data (Use CSV list)</option>
                          {importBatches.map(batch => (
                            <option key={batch.import_batch_id} value={batch.import_batch_id}>
                              {batch.import_filename} ({batch.record_count} records)
                            </option>
                          ))}
                        </select>
                        <button
                          type="button"
                          onClick={() => setImportModalOpen(true)}
                          className="px-4 py-2 bg-purple-600 hover:bg-purple-700 text-white rounded-lg transition-colors"
                          title="Import new Excel/CSV file"
                        >
                          <i className="fas fa-upload mr-2"></i>
                          Import
                        </button>
                      </div>
                      {form.import_batch_id && (
                        <div className="mt-2 p-3 bg-purple-50 border border-purple-200 rounded-lg">
                          <p className="text-sm text-purple-800">
                            <i className="fas fa-check-circle mr-2"></i>
                            <strong>Imported Data Selected:</strong> Template merge fields will use data from this import.
                          </p>
                          <p className="text-xs text-purple-700 mt-1">
                            <i className="fas fa-info-circle mr-1"></i>
                            Emails will be sent to all recipients in this import batch.
                          </p>
                        </div>
                      )}
                      {!form.import_batch_id && !form.template_id && (
                        <p className="mt-2 text-sm text-gray-600">
                          <i className="fas fa-info-circle mr-1"></i>
                          Import Excel data or use existing CSV lists for recipients.
                        </p>
                      )}
                    </div>
                    <div>
                      <label className="block text-sm font-medium text-gray-900 mb-2">
                        Email Body (Use image button in toolbar to add images)
                      </label>
                      <div className="bg-white border border-gray-300 rounded-lg overflow-hidden shadow-sm" style={{ zIndex: 1001 }}>
                        <RichTextEditor
                          value={form.mail_body}
                          onChange={(html) => setForm((prev) => ({ ...prev, mail_body: html }))}
                          onImageUpload={handleImageUpload}
                          uploadImageUrl={UPLOAD_IMAGE_URL}
                          placeholder="Update your email content here..."
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

      {/* Import Modal */}
      {importModalOpen && (
        <div className="fixed inset-0 z-[9999] bg-black/50 backdrop-blur-sm overflow-y-auto">
          <div className="min-h-screen px-4 text-center">
            <span className="inline-block h-screen align-middle" aria-hidden="true">&#8203;</span>
            <div className="inline-block w-full max-w-2xl my-8 text-left align-middle transition-all transform bg-white shadow-2xl rounded-xl">
              <div className="sticky top-0 z-10 px-6 py-4 bg-gradient-to-r from-indigo-50 to-purple-50 border-b border-gray-200 rounded-t-xl">
                <div className="flex justify-between items-center">
                  <div className="flex items-center space-x-3">
                    <div className="bg-white p-2.5 rounded-lg shadow-sm">
                      <i className="fas fa-upload text-purple-600 text-xl"></i>
                    </div>
                    <div>
                      <h3 className="text-xl font-bold text-gray-900">Import Recipients Data</h3>
                      <p className="text-sm text-gray-600 mt-0.5">Upload CSV file with recipient information</p>
                    </div>
                  </div>
                  <button
                    onClick={() => {
                      setImportModalOpen(false);
                      setUploadFile(null);
                    }}
                    className="text-gray-400 hover:text-gray-600 hover:bg-white/50 rounded-lg p-2 transition-colors"
                  >
                    <i className="fas fa-times text-xl"></i>
                  </button>
                </div>
              </div>
            
            <div className="px-6 py-4">
              <div className="mb-4 p-4 bg-blue-50 border border-blue-200 rounded-lg">
                <h3 className="font-semibold text-blue-900 mb-2">
                  <i className="fas fa-info-circle mr-2"></i>
                  Instructions:
                </h3>
                <ul className="text-sm text-blue-800 space-y-1 ml-4">
                  <li>• Upload Excel (.xlsx, .xls) or CSV file directly</li>
                  <li>• File must have an <strong>"email"</strong> column</li>
                  <li>• Supported columns: name, company, phone, amount, days, bill_number, bill_date, executive_name, executive_contact</li>
                  <li>• Any additional columns will be stored and available for templates</li>
                </ul>
              </div>
              
              <div className="mb-4">
                <label className="block text-sm font-medium text-gray-900 mb-2">
                  <i className="fas fa-file-excel mr-2 text-green-600"></i>
                  Select Excel or CSV File
                </label>
                <div className="border-2 border-dashed border-gray-300 rounded-lg p-8 text-center hover:border-purple-400 transition-colors">
                  <input
                    type="file"
                    accept=".csv,.xlsx,.xls"
                    onChange={(e) => setUploadFile(e.target.files[0])}
                    className="hidden"
                    id="csvFileInput"
                  />
                  <label htmlFor="csvFileInput" className="cursor-pointer">
                    <div className="text-gray-400 mb-2">
                      <i className="fas fa-cloud-upload-alt text-5xl"></i>
                    </div>
                    <p className="text-gray-600 font-medium">
                      Click to select Excel or CSV file
                    </p>
                    <p className="text-sm text-gray-500 mt-1">
                      Supports .xlsx, .xls, .csv
                    </p>
                  </label>
                </div>
                {uploadFile && (
                  <div className="mt-3 p-3 bg-green-50 border border-green-200 rounded-lg flex items-center justify-between">
                    <div className="flex items-center">
                      <i className={`fas ${uploadFile.name.endsWith('.csv') ? 'fa-file-csv' : 'fa-file-excel'} text-green-600 text-2xl mr-3`}></i>
                      <div>
                        <p className="font-medium text-gray-900">{uploadFile.name}</p>
                        <p className="text-sm text-gray-600">{(uploadFile.size / 1024).toFixed(2)} KB</p>
                      </div>
                    </div>
                    <button
                      onClick={() => setUploadFile(null)}
                      className="text-red-500 hover:text-red-700"
                    >
                      <i className="fas fa-times"></i>
                    </button>
                  </div>
                )}
              </div>
              
              <div className="flex justify-end gap-3">
                <button
                  type="button"
                  onClick={() => {
                    setImportModalOpen(false);
                    setUploadFile(null);
                  }}
                  className="px-4 py-2 border border-gray-300 rounded-lg hover:bg-gray-50 transition-colors"
                >
                  Cancel
                </button>
                <button
                  type="button"
                  onClick={handleImportFile}
                  disabled={!uploadFile || importing}
                  className="px-6 py-2 bg-purple-600 hover:bg-purple-700 text-white rounded-lg transition-colors disabled:opacity-50 disabled:cursor-not-allowed flex items-center"
                >
                  {importing ? (
                    <>
                      <i className="fas fa-spinner fa-spin mr-2"></i>
                      Importing...
                    </>
                  ) : (
                    <>
                      <i className="fas fa-upload mr-2"></i>
                      Import Data
                    </>
                  )}
                </button>
              </div>
            </div>
          </div>
        </div>
      </div>
      )}

      {/* Email Preview Modal */}
      {previewModalOpen && (
        <div className="fixed inset-0 z-[9999] bg-black/50 backdrop-blur-sm overflow-y-auto">
          <div className="min-h-screen px-4 text-center">
            <span className="inline-block h-screen align-middle" aria-hidden="true">&#8203;</span>
            <div className="inline-block w-11/12 md:w-4/5 lg:w-3/4 max-w-6xl my-8 text-left align-middle transition-all transform bg-white shadow-2xl rounded-xl">
              <div className="sticky top-0 z-10 px-6 py-4 bg-gradient-to-r from-indigo-50 to-purple-50 border-b border-gray-200 rounded-t-xl">
                <div className="flex justify-between items-center">
                  <div className="flex items-center space-x-3">
                    <div className="bg-white p-2.5 rounded-lg shadow-sm">
                      <i className="fas fa-eye text-purple-600 text-xl"></i>
                    </div>
                    <div>
                      <h3 className="text-xl font-bold text-gray-900">Email Preview</h3>
                      <p className="text-sm text-gray-600 mt-0.5">Preview with merged data</p>
                    </div>
                  </div>
                  <button
                    onClick={() => setPreviewModalOpen(false)}
                    className="text-gray-400 hover:text-gray-600 hover:bg-white/50 rounded-lg p-2 transition-colors"
                  >
                    <i className="fas fa-times text-xl"></i>
                  </button>
                </div>
              </div>
              
              <div className="px-6 py-4">
                {previewLoading ? (
                  <div className="flex items-center justify-center py-12">
                    <i className="fas fa-spinner fa-spin text-4xl text-purple-600"></i>
                    <span className="ml-3 text-gray-600">Loading preview...</span>
                  </div>
                ) : (
                  <div className="bg-gray-50 rounded-lg p-4 border border-gray-200">
                    <iframe
                      srcDoc={previewHtml}
                      className="w-full border-0 rounded-lg bg-white"
                      style={{ minHeight: '500px', height: '70vh' }}
                      title="Email Preview"
                      sandbox="allow-same-origin"
                    />
                  </div>
                )}
              </div>

              <div className="px-6 py-4 bg-gray-50 border-t border-gray-200 rounded-b-xl">
                <div className="flex justify-end">
                  <button
                    onClick={() => setPreviewModalOpen(false)}
                    className="px-6 py-2 bg-gray-200 hover:bg-gray-300 text-gray-800 rounded-lg transition-colors"
                  >
                    Close
                  </button>
                </div>
              </div>
            </div>
          </div>
        </div>
      )}
    </div>
  </div>
);
};

  export default Campaigns;
