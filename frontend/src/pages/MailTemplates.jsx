import React, { useState, useEffect } from 'react';

import { API_CONFIG, getBaseUrl } from '../config';

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
  
  const BASE_URL = getBaseUrl();
  const API_URL = `${BASE_URL}/backend/includes/mail_templates.php`;
  
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
      const response = await fetch(`${API_URL}?action=list`);
      const data = await response.json();
      
      if (data.success) {
        setTemplates(data.templates);
      } else {
        console.error('API Error:', data.error);
        setMessage({ type: 'error', text: data.error || 'Failed to load templates' });
      }
    } catch (error) {
      console.error('Load templates error:', error);
      setMessage({ type: 'error', text: 'Failed to connect to server. Please check if the backend is running.' });
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
      
      const response = await fetch(url, {
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
    fetch(`${API_URL}?action=get&template_id=${template.template_id}`)
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
      const response = await fetch(
        `${API_URL}?action=delete&template_id=${templateId}`,
        { method: 'DELETE' }
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
        const batchResponse = await fetch(`${BASE_URL}/backend/includes/import_data.php?action=list`);
        const batchData = await batchResponse.json();
        if (batchData.success && batchData.batches && batchData.batches.length > 0) {
          import_batch_id = batchData.batches[0].import_batch_id;
        }
      } catch (err) {
        console.log('No import batches found, using sample data');
      }

      const response = await fetch(`${API_URL}?action=merge_preview`, {
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

  return (
    <div className="container mx-auto mt-12 px-2 sm:px-4 py-8 max-w-7xl">
      {/* Status Message */}
      {message.text && <StatusMessage message={message} onClose={() => setMessage({ type: '', text: '' })} />}

      {/* Header */}
      <div className="flex justify-between items-center mb-6">
        <h1 className="text-2xl font-bold text-gray-800">
          <i className="fas fa-file-code mr-2 text-blue-600"></i>
          Mail Templates
        </h1>
        <button
          onClick={() => setShowModal(true)}
          className="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-md flex items-center"
        >
          <i className="fas fa-plus mr-2"></i> Add Template
        </button>
      </div>

      {/* Content */}
      {loading && templates.length === 0 ? (
        <div className="bg-white rounded-lg shadow overflow-hidden p-8">
          <div className="flex flex-col items-center justify-center py-12">
            <div className="relative mb-4">
              <div className="w-16 h-16 border-4 border-blue-200 border-t-blue-600 rounded-full animate-spin"></div>
            </div>
            <p className="text-gray-600 font-medium">Loading templates...</p>
          </div>
        </div>
      ) : templates.length === 0 ? (
        <div className="bg-white rounded-lg shadow p-12 text-center">
          <div className="flex flex-col items-center gap-4">
            <div className="w-16 h-16 bg-blue-100 rounded-full flex items-center justify-center">
              <i className="fas fa-file-code text-2xl text-blue-600"></i>
            </div>
            <div>
              <h3 className="text-lg font-semibold text-gray-800 mb-1">No Templates Yet</h3>
              <p className="text-gray-600 text-sm">Create your first template to get started with personalized email campaigns.</p>
            </div>
            <button
              className="mt-2 bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-md flex items-center gap-2"
              onClick={() => setShowModal(true)}
            >
              <i className="fas fa-plus"></i>
              <span>Create First Template</span>
            </button>
          </div>
        </div>
      ) : (
        <div className="bg-white rounded-lg shadow overflow-hidden">
          <div className="overflow-x-auto">
            <table className="min-w-full divide-y divide-gray-200">
              <thead className="bg-gray-50">
                <tr>
                  <th className="w-16 px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
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
                {templates.map(template => (
                  <tr
                    key={template.template_id}
                    className="hover:bg-gray-50 transition-colors duration-150"
                  >
                    <td className="px-4 py-3 whitespace-nowrap text-sm font-medium text-gray-500">
                      {template.template_id}
                    </td>
                    <td className="px-4 py-3">
                      <div className="text-sm font-medium text-gray-900">
                        {template.template_name}
                      </div>
                      <div className="text-xs text-gray-500">
                        {(template.html_length / 1024).toFixed(1)} KB • {new Date(template.created_at).toLocaleDateString()}
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
                      <span className={`inline-flex px-2 py-1 text-xs font-semibold rounded-full ${
                        template.is_active 
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

      {/* Create/Edit Modal */}
      {showModal && (
        <div className="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/50 backdrop-blur-sm animate-fade-in">
          <div className="bg-white rounded-2xl shadow-2xl w-full max-w-6xl max-h-[90vh] overflow-hidden flex flex-col">
            {/* Modal Header */}
            <div className="flex justify-between items-center px-6 py-4 border-b border-gray-200 bg-gradient-to-r from-blue-50 to-purple-50">
              <h2 className="text-2xl font-bold text-gray-800 flex items-center gap-3">
                <i className={`fas ${editingTemplate ? 'fa-edit' : 'fa-plus-circle'} text-blue-600`}></i>
                {editingTemplate ? 'Edit Template' : 'Create New Template'}
              </h2>
              <button
                type="button"
                className="text-gray-400 hover:text-gray-600 transition-colors p-2"
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
                        onClick={handlePreview}
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
                    placeholder="Paste your HTML template here. Use [[FieldName]] for merge fields (e.g., [[Amount]], [[Days]], [[BilledName]])"
                  ></textarea>
                  <p className="mt-2 text-sm text-gray-600 bg-blue-50 border border-blue-200 rounded-lg p-3">
                    <i className="fas fa-info-circle text-blue-600 mr-2"></i>
                    <strong>Tip:</strong> Use double brackets for merge fields: <code className="bg-white px-2 py-1 rounded text-blue-700">[[FieldName]]</code>
                    <br />
                    <span className="ml-5">Examples: [[Amount]], [[BilledName]], [[BillDate]], [[ExecutiveName]]</span>
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
      )}

      {/* Preview Modal */}
      {showPreview && (
        <div className="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/50 backdrop-blur-sm animate-fade-in">
          <div className="bg-white rounded-2xl shadow-2xl w-full max-w-6xl max-h-[90vh] overflow-hidden flex flex-col">
            {/* Preview Header */}
            <div className="flex justify-between items-center px-6 py-4 border-b border-gray-200 bg-gradient-to-r from-green-50 to-teal-50">
              <h2 className="text-2xl font-bold text-gray-800 flex items-center gap-3">
                <i className="fas fa-eye text-green-600"></i>
                Template Preview
                <span className="text-sm font-normal text-gray-600">(with sample data)</span>
              </h2>
              <button
                type="button"
                className="text-gray-400 hover:text-gray-600 transition-colors p-2"
                onClick={() => setShowPreview(false)}
              >
                <i className="fas fa-times text-xl"></i>
              </button>
            </div>
            
            {/* Preview Body */}
            <div className="flex-1 overflow-y-auto bg-gray-100">
              <div className="p-8">
                <iframe
                  srcDoc={previewHtml}
                  style={{
                    width: '100%',
                    minHeight: '600px',
                    border: 'none',
                    background: 'white',
                    boxShadow: '0 2px 8px rgba(0,0,0,0.1)'
                  }}
                  title="Template Preview"
                />
              </div>
            </div>
            
            {/* Preview Footer */}
            <div className="flex justify-end px-6 py-4 border-t border-gray-200 bg-gray-50">
              <button
                type="button"
                className="px-6 py-2.5 bg-gradient-to-r from-gray-600 to-gray-700 text-white rounded-xl hover:from-gray-700 hover:to-gray-800 transition-all font-medium"
                onClick={() => setShowPreview(false)}
              >
                Close Preview
              </button>
            </div>
          </div>
        </div>
      )}
    </div>
  );
};

export default MailTemplates;
