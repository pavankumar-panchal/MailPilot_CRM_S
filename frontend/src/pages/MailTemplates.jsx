import React, { useState, useEffect } from 'react';

import { API_CONFIG, getBaseUrl } from '../config';
import { authFetch } from '../utils/authFetch';

// Glassmorphism Status Message Popup (matching Campaigns page style)
const StatusMessage = ({ message, onClose }) =>
  message && (
    <div
      className={`
        fixed top-6 left-1/2 transform -translate-x-1/2
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
        zIndex: 99999,
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
        className="ml-2 hover:opacity-70 focus:outline-none transition-opacity"
        aria-label="Close"
      >
        <i className="fas fa-times"></i>
      </button>
    </div>
  );

const MailTemplates = () => {
  const [templates, setTemplates] = useState([]);
  const [loading, setLoading] = useState(false);
  const [showModal, setShowModal] = useState(false);
  const [editingTemplate, setEditingTemplate] = useState(null);
  const [showPreview, setShowPreview] = useState(false);
  const [message, setMessage] = useState({ type: '', text: '' });
  const [previewHtml, setPreviewHtml] = useState('');
  const [detectedImages, setDetectedImages] = useState([]);
  const [showImageUploadModal, setShowImageUploadModal] = useState(false);
  const [imageFiles, setImageFiles] = useState({});
  const [uploadingImages, setUploadingImages] = useState(false);

  const BASE_URL = getBaseUrl();
  const API_URL = `${BASE_URL}/backend/includes/mail_templates.php`;
  const UPLOAD_IMAGE_URL = `${BASE_URL}/backend/includes/upload_image.php`;

  const [formData, setFormData] = useState({
    template_name: '',
    template_description: '',
    template_html: '',
    merge_fields: [],
    is_active: 1
  });

  useEffect(() => {
    loadTemplates();
  }, []);

  // Auto-hide message after 5 seconds
  useEffect(() => {
    if (message.text) {
      const timer = setTimeout(() => {
        setMessage({ type: '', text: '' });
      }, 5000);
      return () => clearTimeout(timer);
    }
  }, [message]);

  const loadTemplates = async () => {
    setLoading(true);
    try {
      const response = await authFetch(`${API_URL}?action=list`);

      if (!response.ok) {
        const errorData = await response.json().catch(() => ({}));
        throw new Error(errorData.error || `HTTP error! status: ${response.status}`);
      }

      const data = await response.json();

      if (data.success) {
        setTemplates(data.templates);
        // Clear any previous errors
        if (message.type === 'error') {
          setMessage({ type: '', text: '' });
        }
      } else {
        console.error('API Error:', data.error);
        setMessage({ type: 'error', text: data.error || 'Failed to load templates' });
      }
    } catch (error) {
      console.error('Load templates error:', error);
      setTemplates([]);
      setMessage({ type: 'error', text: error.message || 'Failed to load templates.' });
    } finally {
      setLoading(false);
    }
  };

  const handleSubmit = async (e) => {
    e.preventDefault();
    setLoading(true);

    try {
      const url = editingTemplate
        ? `${API_URL}?action=update`
        : `${API_URL}?action=create`;

      const method = editingTemplate ? 'PUT' : 'POST';

      const response = await authFetch(url, {
        method,
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(formData)
      });

      const data = await response.json();

      if (data.success) {
        setMessage({ type: 'success', text: editingTemplate ? 'Template updated successfully' : 'Template created successfully' });
        loadTemplates();
        handleCloseModal();
      } else {
        setMessage({ type: 'error', text: data.error || 'Operation failed' });
      }
    } catch (error) {
      console.error('Save template error:', error);
      setMessage({ type: 'error', text: 'Failed to save template' });
    } finally {
      setLoading(false);
    }
  };

  const handleEdit = (template) => {
    setEditingTemplate(template);
    setFormData({
      template_id: template.template_id,
      template_name: template.template_name,
      template_description: template.template_description || '',
      template_html: '',  // Will load on demand
      merge_fields: template.merge_fields || [],
      is_active: template.is_active
    });

    // Load full HTML
    fetch(`${API_URL}?action=get&template_id=${template.template_id}`, {
      credentials: 'include'
    })
      .then(res => res.json())
      .then(data => {
        if (data.success) {
          setFormData(prev => ({ ...prev, template_html: data.template.template_html }));
        }
      });

    setShowModal(true);
  };

  const handleDelete = async (templateId) => {
    if (!confirm('Are you sure you want to delete this template?')) return;

    try {
      const response = await authFetch(
        `${API_URL}?action=delete&template_id=${templateId}`,
        {
          method: 'DELETE',
        }
      );

      const data = await response.json();

      if (data.success) {
        setMessage({ type: 'success', text: 'Template deleted successfully' });
        loadTemplates();
      } else {
        setMessage({ type: 'error', text: data.error || 'Failed to delete template' });
      }
    } catch (error) {
      console.error('Delete template error:', error);
      setMessage({ type: 'error', text: 'Failed to delete template' });
    }
  };

  const handlePreview = async () => {
    try {
      // First, try to get the latest import batch ID
      let import_batch_id = null;
      try {
        const batchResponse = await authFetch(`${API_CONFIG.API_IMPORT_DATA}?action=list`);
        const batchData = await batchResponse.json();
        if (batchData.success && batchData.batches && batchData.batches.length > 0) {
          import_batch_id = batchData.batches[0].import_batch_id;
        }
      } catch (err) {
        console.log('No import batches found, using sample data');
      }

      const response = await authFetch(`${API_URL}?action=merge_preview`, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
          template_html: formData.template_html,
          import_batch_id: import_batch_id, // Pass batch_id to fetch real data
          merge_data: {
            // Fallback sample data if no batch found
            Amount: '5000',
            Price: '4237',
            Tax: '763',
            NetPrice: '5000',
            Days: '30',
            BilledName: 'Sample Company Pvt Ltd',
            Company: 'Sample Company Pvt Ltd',
            CustomerID: 'CUST001',
            BillNumber: 'INV-2025-001',
            BillDate: 'January 15, 2025',
            ExecutiveName: 'John Doe',
            ExecutiveContact: '+91-9876543210',
            DealerName: 'Sample Dealer',
            DealerEmail: 'dealer@example.com',
            DealerCell: '+91-9876543210',
            Email: 'customer@example.com',
            Emails: 'customer@example.com',
            Phone: '+91-9876543210',
            Edition: 'Professional',
            UsageType: 'Multi-User',
            LastProduct: 'SaralTDS 2024',
            DISTRICT: 'Sample District'
          }
        })
      });

      const data = await response.json();

      if (data.success) {
        setPreviewHtml(data.merged_html);
        setShowPreview(true);
        if (data.merge_fields_found) {
          setFormData(prev => ({ ...prev, merge_fields: data.merge_fields_found }));
        }
        // Show which data source was used
        if (data.data_source === 'database') {
          setMessage({ type: 'success', text: 'Preview using real data from latest import' });
        }
      } else {
        setMessage({ type: 'error', text: data.error || 'Preview failed' });
      }
    } catch (error) {
      console.error('Preview error:', error);
      setMessage({ type: 'error', text: 'Failed to generate preview' });
    }
  };

  const handleCloseModal = () => {
    setShowModal(false);
    setEditingTemplate(null);
    setFormData({
      template_name: '',
      template_description: '',
      template_html: '',
      merge_fields: [],
      is_active: 1
    });
  };

  const detectMergeFields = () => {
    const html = formData.template_html;
    const regex = /\[\[([^\]]+)\]\]/g;
    const fields = [];
    let match;

    while ((match = regex.exec(html)) !== null) {
      if (!fields.includes(match[1])) {
        fields.push(match[1]);
      }
    }

    setFormData(prev => ({ ...prev, merge_fields: fields }));
    if (fields.length > 0) {
      setMessage({ type: 'success', text: `Found ${fields.length} merge fields: ${fields.join(', ')}` });
    } else {
      setMessage({ type: 'error', text: 'No merge fields found. Use [[FieldName]] syntax.' });
    }
  };

  // Detect images in HTML
  const detectImages = (html) => {
    const imgRegex = /<img[^>]+src=["']([^"']+)["']/gi;
    const images = [];
    let match;

    while ((match = imgRegex.exec(html)) !== null) {
      const src = match[1];
      // Check if it's a local/external path (not already uploaded to our server)
      if (!src.startsWith(BASE_URL) && !src.startsWith('data:')) {
        images.push(src);
      }
    }

    return [...new Set(images)]; // Remove duplicates
  };

  // Handle HTML file upload
  const handleHtmlFileUpload = (e) => {
    const file = e.target.files[0];
    if (!file) return;

    const reader = new FileReader();
    reader.onload = (event) => {
      const htmlContent = event.target.result;
      setFormData(prev => ({ ...prev, template_html: htmlContent }));

      // Auto-detect merge fields and images
      detectMergeFields();
      const images = detectImages(htmlContent);
      if (images.length > 0) {
        setDetectedImages(images);
        setMessage({
          type: 'error',
          text: `Found ${images.length} external/local images. Please upload them to ensure emails display correctly.`
        });
        setShowImageUploadModal(true);
      }
    };
    reader.readAsText(file);
  };

  // Handle image upload for detected images
  const handleImageFileSelect = (imageSrc, file) => {
    setImageFiles(prev => ({ ...prev, [imageSrc]: file }));
  };

  // Upload all images and replace URLs in HTML
  const uploadImagesAndReplaceUrls = async () => {
    setUploadingImages(true);
    try {
      let updatedHtml = formData.template_html;
      const urlMap = {};

      console.log('Starting image upload for', Object.keys(imageFiles).length, 'images');
      console.log('Upload URL:', UPLOAD_IMAGE_URL);

      for (const [originalSrc, file] of Object.entries(imageFiles)) {
        if (!file) continue;

        console.log(`Uploading: ${file.name} for source: ${originalSrc}`);

        const imageFormData = new FormData();
        imageFormData.append('image', file);

        try {
          // Use fetch with credentials for session-based auth
          const response = await fetch(UPLOAD_IMAGE_URL, {
            method: 'POST',
            credentials: 'include', // Include cookies for session
            body: imageFormData
          });

          console.log('Upload response status:', response.status);
          console.log('Upload response ok:', response.ok);

          // Get response text first to debug
          const responseText = await response.text();
          console.log('Upload response text:', responseText);

          let data;
          try {
            data = JSON.parse(responseText);
          } catch (parseError) {
            console.error('JSON parse error:', parseError);
            throw new Error(`Invalid JSON response for ${file.name}: ${responseText.substring(0, 100)}`);
          }

          console.log('Upload response data:', data);

          if (data.success) {
            // Use the server path directly
            const serverPath = `${BASE_URL}/backend/${data.path}`;
            urlMap[originalSrc] = serverPath;
            console.log(`✓ Uploaded ${file.name} -> ${serverPath}`);
          } else {
            throw new Error(data.message || `Failed to upload ${file.name}`);
          }
        } catch (uploadError) {
          console.error(`Failed to upload ${file.name}:`, uploadError);
          throw uploadError;
        }
      }

      console.log('All uploads complete. URL mapping:', urlMap);

      // Replace all image URLs in HTML
      for (const [oldSrc, newSrc] of Object.entries(urlMap)) {
        const escapedSrc = oldSrc.replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
        const regex = new RegExp(escapedSrc, 'g');
        updatedHtml = updatedHtml.replace(regex, newSrc);
        console.log(`Replaced: ${oldSrc} -> ${newSrc}`);
      }

      console.log('Final HTML length:', updatedHtml.length);

      setFormData(prev => ({ ...prev, template_html: updatedHtml }));
      setDetectedImages([]);
      setImageFiles({});
      setShowImageUploadModal(false);
      setMessage({ type: 'success', text: `Successfully uploaded and replaced ${Object.keys(urlMap).length} images` });
    } catch (error) {
      console.error('Image upload error:', error);
      setMessage({ type: 'error', text: error.message || 'Failed to upload images' });
    } finally {
      setUploadingImages(false);
    }
  };

  const detectImagesInCurrentHtml = () => {
    const images = detectImages(formData.template_html);
    if (images.length > 0) {
      setDetectedImages(images);
      setShowImageUploadModal(true);
      setMessage({
        type: 'error',
        text: `Found ${images.length} external/local images that need to be uploaded`
      });
    } else {
      setMessage({ type: 'success', text: 'All images are already hosted correctly!' });
    }
  };

  return (
    <div className="min-h-screen bg-gradient-to-br from-blue-50 via-white to-purple-50">
      <div className="container mx-auto px-2 sm:px-4 py-4 sm:py-6 lg:py-8 max-w-7xl">
        {/* Status Message */}
        {message.text && <StatusMessage message={message} onClose={() => setMessage({ type: '', text: '' })} />}

        {/* Mail Templates Section */}
        <div className="glass-effect rounded-xl shadow-xl border border-white/20 p-5 sm:p-6 lg:p-8 mb-5 sm:mb-6 hover:shadow-2xl transition-all duration-300">
          <div className="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4 mb-6">
            <div className="flex items-center gap-3">
              <div className="bg-gradient-to-br from-blue-500 to-indigo-600 p-3 rounded-xl shadow-lg">
                <svg className="w-6 h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M7 21h10a2 2 0 002-2V9.414a1 1 0 00-.293-.707l-5.414-5.414A1 1 0 0012.586 3H7a2 2 0 00-2 2v14a2 2 0 002 2z" />
                </svg>
              </div>
              <h2 className="text-lg sm:text-xl font-bold text-gray-800">Mail Templates</h2>
            </div>
            <button
              onClick={() => setShowModal(true)}
              className="px-6 py-3 bg-gradient-to-r from-blue-600 to-blue-700 text-white font-semibold rounded-xl shadow-xl hover:shadow-2xl hover:scale-105 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2 transition-all duration-300 flex items-center gap-2"
            >
              <svg className="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M12 4v16m8-8H4" />
              </svg>
              Add Template
            </button>
          </div>
          {loading && templates.length === 0 ? (
            <div className="glass-effect rounded-xl shadow-lg overflow-hidden p-8">
              <div className="flex flex-col items-center justify-center py-12">
                <div className="relative mb-4">
                  <div className="w-16 h-16 border-4 border-blue-200 border-t-blue-600 rounded-full animate-spin"></div>
                </div>
                <p className="text-gray-600 font-medium">Loading templates...</p>
              </div>
            </div>
          ) : templates.length === 0 ? (
            <div className="glass-effect rounded-xl shadow-lg p-12 text-center">
              <div className="flex flex-col items-center gap-4">
                <div className="w-16 h-16 bg-gradient-to-br from-blue-500 to-blue-600 rounded-full flex items-center justify-center shadow-md">
                  <i className="fas fa-file-code text-2xl text-white"></i>
                </div>
                <div>
                  <h3 className="text-lg font-semibold text-gray-800 mb-1">No Templates Yet</h3>
                  <p className="text-gray-600 text-sm">Create your first template to get started with personalized email campaigns.</p>
                </div>
                <button
                  className="mt-2 bg-gradient-to-r from-blue-500 to-blue-600 hover:shadow-xl hover:scale-105 text-white px-4 py-2.5 rounded-lg shadow-lg flex items-center gap-2 font-semibold transition-all"
                  onClick={() => setShowModal(true)}
                >
                  <i className="fas fa-plus"></i>
                  <span>Create First Template</span>
                </button>
              </div>
            </div>
          ) : (
            <div className="glass-effect rounded-xl shadow-lg overflow-hidden">
              <div className="overflow-x-auto">
                <table className="min-w-full divide-y divide-gray-200">
                  <thead className="bg-gradient-to-r from-gray-50 to-gray-100">
                    <tr>
                      <th className="px-4 py-3.5 text-left text-xs font-bold text-gray-700 uppercase tracking-wider">
                        ID
                      </th>
                      <th className="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                        Name
                      </th>
                      <th className="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                        Description
                      </th>
                      <th className="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                        Merge Fields
                      </th>
                      <th className="w-32 px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                        Status
                      </th>
                      <th className="w-32 px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">
                        Actions
                      </th>
                    </tr>
                  </thead>
                  <tbody className="bg-white divide-y divide-gray-200">
                    {templates.map((template, index) => (
                      <tr
                        key={template.template_id}
                        className="hover:bg-gray-50 transition-colors duration-150"
                      >
                        <td className="px-4 py-3 whitespace-nowrap text-sm font-medium text-gray-500">
                          {index + 1}
                        </td>
                        <td className="px-4 py-3">
                          <div className="text-sm font-medium text-gray-900">
                            {template.template_name}
                          </div>
                          <div className="text-xs text-gray-500">
                            {template.html_length ? `${(template.html_length / 1024).toFixed(1)} KB` : 'No content'}
                          </div>
                        </td>
                        <td className="px-4 py-3">
                          <div className="text-sm text-gray-600 max-w-xs truncate">
                            {template.template_description || 'No description'}
                          </div>
                        </td>
                        <td className="px-4 py-3">
                          <div className="flex flex-wrap gap-1 max-w-sm">
                            {template.merge_fields && template.merge_fields.length > 0 ? (
                              <>
                                {template.merge_fields.slice(0, 3).map(field => (
                                  <span
                                    key={field}
                                    className="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-blue-100 text-blue-800"
                                  >
                                    [[{field}]]
                                  </span>
                                ))}
                                {template.merge_fields.length > 3 && (
                                  <span className="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-gray-100 text-gray-600">
                                    +{template.merge_fields.length - 3} more
                                  </span>
                                )}
                              </>
                            ) : (
                              <span className="text-xs text-gray-400 italic">No fields</span>
                            )}
                          </div>
                        </td>
                        <td className="px-4 py-3 whitespace-nowrap">
                          <span className={`inline-flex px-2 py-1 text-xs font-semibold rounded-full ${template.is_active
                            ? 'bg-green-100 text-green-800'
                            : 'bg-gray-100 text-gray-600'
                            }`}>
                            {template.is_active ? 'Active' : 'Inactive'}
                          </span>
                        </td>
                        <td className="px-4 py-3 whitespace-nowrap text-right text-sm font-medium">
                          <div className="flex justify-end space-x-2">
                            <button
                              onClick={() => handleEdit(template)}
                              className="text-blue-600 hover:text-blue-800 p-1 rounded hover:bg-blue-50"
                              title="Edit"
                            >
                              <i className="fas fa-edit"></i>
                            </button>
                            <button
                              onClick={() => handleDelete(template.template_id)}
                              className="text-red-600 hover:text-red-800 p-1 rounded hover:bg-red-50"
                              title="Delete"
                            >
                              <i className="fas fa-trash"></i>
                            </button>
                          </div>
                        </td>
                      </tr>
                    ))}
                  </tbody>
                </table>
              </div>
            </div>
          )}
        </div>
      </div>

      {/* Create/Edit Modal */}
      {showModal && (
        <div className="fixed inset-0 z-[9999] bg-black/50 backdrop-blur-sm overflow-y-auto">
          <div className="min-h-screen px-4 flex items-center justify-center">
            <div className="bg-white rounded-2xl shadow-2xl w-full max-w-6xl max-h-[90vh] overflow-hidden flex flex-col my-8">
              {/* Modal Header */}
              <div className="flex justify-between items-center px-6 py-4 border-b border-gray-200 bg-gradient-to-r from-indigo-50 to-purple-50">
                <div className="flex items-center gap-3">
                  <div className="bg-white p-2.5 rounded-lg shadow-sm">
                    <i className={`fas ${editingTemplate ? 'fa-edit' : 'fa-plus-circle'} text-indigo-600 text-xl`}></i>
                  </div>
                  <div>
                    <h3 className="text-xl font-bold text-gray-900">{editingTemplate ? 'Edit Template' : 'Create New Template'}</h3>
                  </div>
                </div>
                <button
                  type="button"
                  className="text-gray-400 hover:text-gray-600 hover:bg-white/50 rounded-lg p-2 transition-colors"
                  onClick={handleCloseModal}
                >
                  <i className="fas fa-times text-xl"></i>
                </button>
              </div>

              {/* Modal Body */}
              <form onSubmit={handleSubmit} className="flex-1 overflow-y-auto">
                <div className="p-6 space-y-6">
                  {/* Name and Status Row */}
                  <div className="grid grid-cols-1 md:grid-cols-3 gap-4">
                    <div className="md:col-span-2">
                      <label className="block text-sm font-semibold text-gray-700 mb-2">
                        Template Name <span className="text-red-500">*</span>
                      </label>
                      <input
                        type="text"
                        className="w-full px-4 py-3 border border-gray-300 rounded-xl focus:ring-2 focus:ring-blue-500 focus:border-transparent transition-all"
                        value={formData.template_name}
                        onChange={e => setFormData({ ...formData, template_name: e.target.value })}
                        required
                        placeholder="e.g., Outstanding Payment Reminder"
                      />
                    </div>
                    <div>
                      <label className="block text-sm font-semibold text-gray-700 mb-2">Status</label>
                      <select
                        className="w-full px-4 py-3 border border-gray-300 rounded-xl focus:ring-2 focus:ring-blue-500 focus:border-transparent transition-all"
                        value={formData.is_active}
                        onChange={e => setFormData({ ...formData, is_active: parseInt(e.target.value) })}
                      >
                        <option value="1">Active</option>
                        <option value="0">Inactive</option>
                      </select>
                    </div>
                  </div>

                  {/* Description */}
                  <div>
                    <label className="block text-sm font-semibold text-gray-700 mb-2">Description</label>
                    <textarea
                      className="w-full px-4 py-3 border border-gray-300 rounded-xl focus:ring-2 focus:ring-blue-500 focus:border-transparent transition-all resize-none"
                      rows="2"
                      value={formData.template_description}
                      onChange={e => setFormData({ ...formData, template_description: e.target.value })}
                      placeholder="Brief description of this template's purpose"
                    ></textarea>
                  </div>

                  {/* HTML Template */}
                  <div>
                    <div className="flex justify-between items-center mb-3">
                      <label className="block text-sm font-semibold text-gray-700">
                        HTML Template <span className="text-red-500">*</span>
                      </label>
                      <div className="flex gap-2">
                        <label className="flex items-center gap-2 px-4 py-2 bg-gradient-to-r from-purple-50 to-purple-100 text-purple-700 rounded-lg hover:from-purple-100 hover:to-purple-200 transition-all text-sm font-medium border border-purple-200 cursor-pointer">
                          <i className="fas fa-file-upload"></i>
                          Upload HTML File
                          <input
                            type="file"
                            accept=".html,.htm"
                            onChange={handleHtmlFileUpload}
                            className="hidden"
                          />
                        </label>
                        <button
                          type="button"
                          className="flex items-center gap-2 px-4 py-2 bg-gradient-to-r from-orange-50 to-orange-100 text-orange-700 rounded-lg hover:from-orange-100 hover:to-orange-200 transition-all text-sm font-medium border border-orange-200"
                          onClick={detectImagesInCurrentHtml}
                          disabled={!formData.template_html}
                        >
                          <i className="fas fa-images"></i>
                          Check Images
                        </button>
                        <button
                          type="button"
                          className="flex items-center gap-2 px-4 py-2 bg-gradient-to-r from-cyan-50 to-cyan-100 text-cyan-700 rounded-lg hover:from-cyan-100 hover:to-cyan-200 transition-all text-sm font-medium border border-cyan-200"
                          onClick={detectMergeFields}
                        >
                          <i className="fas fa-search"></i>
                          Detect Fields
                        </button>
                        <button
                          type="button"
                          className="flex items-center gap-2 px-4 py-2 bg-gradient-to-r from-green-50 to-green-100 text-green-700 rounded-lg hover:from-green-100 hover:to-green-200 transition-all text-sm font-medium border border-green-200"
                          onClick={(e) => {
                            e.preventDefault();
                            e.stopPropagation();
                            handlePreview();
                          }}
                          disabled={!formData.template_html}
                        >
                          <i className="fas fa-eye"></i>
                          Preview
                        </button>
                      </div>
                    </div>
                    <textarea
                      className="w-full px-4 py-3 border border-gray-300 rounded-xl focus:ring-2 focus:ring-blue-500 focus:border-transparent transition-all font-mono text-sm resize-none"
                      rows="18"
                      value={formData.template_html}
                      onChange={e => setFormData({ ...formData, template_html: e.target.value })}
                      required
                      placeholder="Paste your HTML template here OR use 'Upload HTML File' button above. Use [[FieldName]] for merge fields."
                    ></textarea>
                    <p className="mt-2 text-sm text-gray-600 bg-blue-50 border border-blue-200 rounded-lg p-3">
                      <i className="fas fa-info-circle text-blue-600 mr-2"></i>
                      <strong>Tip:</strong> Use double brackets for merge fields: <code className="bg-white px-2 py-1 rounded text-blue-700">[[FieldName]]</code>
                      <br />
                      <span className="ml-5">Upload HTML file or paste directly. Click "Check Images" to upload any local/external images.</span>
                    </p>
                  </div>

                  {/* Detected Merge Fields */}
                  {formData.merge_fields.length > 0 && (
                    <div className="bg-gradient-to-r from-blue-50 to-purple-50 border border-blue-200 rounded-xl p-4">
                      <div className="flex items-center gap-2 mb-3">
                        <i className="fas fa-tags text-blue-600"></i>
                        <strong className="text-gray-800">Detected Merge Fields ({formData.merge_fields.length})</strong>
                      </div>
                      <div className="flex flex-wrap gap-2 mb-3">
                        {formData.merge_fields.map(field => (
                          <span
                            key={field}
                            className="px-3 py-1.5 bg-white text-blue-700 rounded-lg text-sm font-mono border border-blue-300 shadow-sm"
                          >
                            [[{field}]]
                          </span>
                        ))}
                      </div>
                      <p className="text-sm text-gray-700">
                        <i className="fas fa-lightbulb text-yellow-500 mr-2"></i>
                        These fields will be replaced with actual data from your CSV when sending campaigns.
                      </p>
                    </div>
                  )}
                    </div>
    
                    {/* Modal Footer */}
                    <div className="flex justify-end gap-3 px-6 py-4 border-t border-gray-200 bg-gray-50">
                      <button
                        type="button"
                        className="px-6 py-2.5 bg-white border border-gray-300 text-gray-700 rounded-xl hover:bg-gray-50 transition-all font-medium"
                        onClick={handleCloseModal}
                      >
                        Cancel
                      </button>
                      <button
                        type="submit"
                        className="flex items-center gap-2 px-6 py-2.5 bg-gradient-to-r from-blue-600 to-blue-700 text-white rounded-xl hover:from-blue-700 hover:to-blue-800 transition-all shadow-lg font-medium disabled:opacity-50 disabled:cursor-not-allowed"
                        disabled={loading}
                      >
                        {loading ? (
                          <>
                            <div className="w-4 h-4 border-2 border-white border-t-transparent rounded-full animate-spin"></div>
                            <span>Saving...</span>
                          </>
                        ) : (
                          <>
                            <i className="fas fa-save"></i>
                            <span>{editingTemplate ? 'Update Template' : 'Create Template'}</span>
                          </>
                        )}
                      </button>
                    </div>
                  </form>
                </div>
              </div>
            </div>
          )}
  
          {/* Preview Modal */}
          {showPreview && (
            <div className="fixed inset-0 z-[9999] bg-black/50 backdrop-blur-sm overflow-y-auto">
              <div className="min-h-screen px-4 text-center">
                <span className="inline-block h-screen align-middle" aria-hidden="true">&#8203;</span>
                <div className="inline-block w-11/12 max-w-6xl my-8 text-left align-middle transition-all transform bg-white shadow-2xl rounded-xl">
                  {/* Preview Header */}
                  <div className="sticky top-0 z-10 px-6 py-4 bg-gradient-to-r from-indigo-50 to-purple-50 border-b border-gray-200 rounded-t-xl">
                    <div className="flex justify-between items-center">
                      <div className="flex items-center space-x-3">
                        <div className="bg-white p-2.5 rounded-lg shadow-sm">
                          <i className="fas fa-eye text-purple-600 text-xl"></i>
                        </div>
                        <div>
                          <h3 className="text-xl font-bold text-gray-900">Template Preview</h3>
                          <p className="text-sm text-gray-600 mt-0.5">with sample data</p>
                        </div>
                      </div>
                      <button
                        type="button"
                        className="text-gray-400 hover:text-gray-600 hover:bg-white/50 rounded-lg p-2 transition-colors"
                        onClick={() => setShowPreview(false)}
                      >
                        <i className="fas fa-times text-xl"></i>
                      </button>
                    </div>
                  </div>

                  {/* Preview Body */}
                  <div className="px-6 py-4 overflow-y-auto" style={{ maxHeight: '70vh' }}>
                    <div className="bg-gray-50 rounded-lg p-4 border border-gray-200">
                      <iframe
                        srcDoc={previewHtml}
                        sandbox="allow-same-origin"
                        className="w-full border-0 rounded-lg bg-white"
                        style={{ minHeight: '500px', height: '60vh' }}
                        title="Template Preview"
                      />
                    </div>
                  </div>

                  {/* Preview Footer */}
                  <div className="px-6 py-4 bg-gray-50 border-t border-gray-200 rounded-b-xl">
                    <div className="flex justify-end">
                      <button
                        type="button"
                        className="px-6 py-2 bg-gray-200 hover:bg-gray-300 text-gray-800 rounded-lg transition-colors"
                        onClick={() => setShowPreview(false)}
                      >
                        Close
                      </button>
                    </div>
                  </div>
                </div>
              </div>
            </div>
          )}

          {/* Image Upload Modal */}
          {showImageUploadModal && (
            <div className="fixed inset-0 z-[9999] bg-black/50 backdrop-blur-sm overflow-y-auto">
              <div className="min-h-screen px-4 flex items-center justify-center">
                <div className="bg-white rounded-2xl shadow-2xl w-full max-w-4xl max-h-[90vh] overflow-hidden flex flex-col my-8">
                  {/* Header */}
                  <div className="px-6 py-4 border-b border-gray-200 bg-gradient-to-r from-indigo-50 to-purple-50 rounded-t-xl">
                    <div className="flex justify-between items-center">
                      <div className="flex items-center space-x-3">
                        <div className="bg-white p-2.5 rounded-lg shadow-sm">
                          <i className="fas fa-images text-orange-600 text-xl"></i>
                        </div>
                        <div>
                          <h3 className="text-xl font-bold text-gray-900">Upload External/Local Images</h3>
                          <p className="text-sm text-gray-600 mt-0.5">{detectedImages.length} images found</p>
                        </div>
                      </div>
                      <button
                        type="button"
                        className="text-gray-400 hover:text-gray-600 hover:bg-white/50 rounded-lg p-2 transition-colors"
                        onClick={() => setShowImageUploadModal(false)}
                      >
                        <i className="fas fa-times text-xl"></i>
                      </button>
                    </div>
                  </div>

                  {/* Body */}
                  <div className="flex-1 overflow-y-auto p-6">
                    <div className="mb-4 bg-orange-50 border border-orange-200 rounded-lg p-4">
                      <p className="text-sm text-gray-700">
                        <i className="fas fa-exclamation-triangle text-orange-600 mr-2"></i>
                        <strong>Important:</strong> Your HTML contains images with external or local paths. Upload them here so they display correctly in emails.
                      </p>
                    </div>

                    <div className="space-y-4">
                      {detectedImages.map((imgSrc, index) => (
                        <div key={index} className="border border-gray-200 rounded-lg p-4 bg-gray-50">
                          <div className="flex items-start gap-4">
                            <div className="flex-shrink-0 w-12 h-12 bg-orange-100 rounded-lg flex items-center justify-center">
                              <i className="fas fa-image text-orange-600 text-xl"></i>
                            </div>
                            <div className="flex-1 min-w-0">
                              <div className="text-sm font-medium text-gray-900 mb-1">Image {index + 1}</div>
                              <div className="text-xs text-gray-600 font-mono bg-white px-2 py-1 rounded border border-gray-200 break-all mb-2">
                                {imgSrc}
                              </div>
                              <label className="flex items-center gap-2 cursor-pointer">
                                <input
                                  type="file"
                                  accept="image/*"
                                  onChange={(e) => handleImageFileSelect(imgSrc, e.target.files[0])}
                                  className="hidden"
                                />
                                <span className="inline-flex items-center gap-2 px-3 py-1.5 bg-blue-600 text-white text-sm rounded-lg hover:bg-blue-700 transition-colors">
                                  <i className="fas fa-upload"></i>
                                  {imageFiles[imgSrc] ? 'Change File' : 'Select File'}
                                </span>
                                {imageFiles[imgSrc] && (
                                  <span className="text-sm text-green-600 font-medium">
                                    <i className="fas fa-check-circle mr-1"></i>
                                    {imageFiles[imgSrc].name}
                                  </span>
                                )}
                              </label>
                            </div>
                          </div>
                        </div>
                      ))}
                    </div>
                  </div>

                  {/* Footer */}
                  <div className="flex justify-end gap-3 px-6 py-4 border-t border-gray-200 bg-gray-50">
                    <button
                      type="button"
                      className="px-6 py-2.5 bg-gray-600 text-white rounded-xl hover:bg-gray-700 transition-all font-medium"
                      onClick={() => {
                        setShowImageUploadModal(false);
                        setDetectedImages([]);
                        setImageFiles({});
                      }}
                    >
                      Skip for Now
                    </button>
                    <button
                      type="button"
                      className="px-6 py-2.5 bg-gradient-to-r from-orange-600 to-red-600 text-white rounded-xl hover:from-orange-700 hover:to-red-700 transition-all font-medium disabled:opacity-50 disabled:cursor-not-allowed"
                      onClick={uploadImagesAndReplaceUrls}
                      disabled={uploadingImages || Object.keys(imageFiles).length === 0}
                    >
                      {uploadingImages ? (
                        <>
                          <i className="fas fa-spinner fa-spin mr-2"></i>
                          Uploading...
                        </>
                      ) : (
                        <>
                          <i className="fas fa-cloud-upload-alt mr-2"></i>
                          Upload & Replace URLs ({Object.keys(imageFiles).length}/{detectedImages.length})
                        </>
                      )}
                    </button>
                  </div>
                </div>
              </div>
            </div>
        )}
      </div>

  );
};

export default MailTemplates;
