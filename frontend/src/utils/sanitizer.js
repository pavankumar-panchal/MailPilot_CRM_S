/**
 * HTML Sanitization Utility
 * Sanitizes HTML to prevent XSS attacks
 */

/**
 * Basic HTML sanitizer - removes script tags and dangerous attributes
 * For production, consider using DOMPurify library for comprehensive sanitization
 * @param {string} html - HTML string to sanitize
 * @returns {string} - Sanitized HTML
 */
export const sanitizeHtml = (html) => {
  if (!html || typeof html !== 'string') return '';
  
  // Create a temporary div to parse HTML
  const temp = document.createElement('div');
  temp.innerHTML = html;
  
  // Remove all script tags
  const scripts = temp.querySelectorAll('script');
  scripts.forEach(script => script.remove());
  
  // Remove dangerous event handlers
  const dangerousAttributes = ['onclick', 'onload', 'onerror', 'onmouseover', 'onfocus', 'onblur'];
  const allElements = temp.querySelectorAll('*');
  allElements.forEach(element => {
    dangerousAttributes.forEach(attr => {
      if (element.hasAttribute(attr)) {
        element.removeAttribute(attr);
      }
    });
    
    // Remove javascript: protocol from href and src
    ['href', 'src'].forEach(attr => {
      const value = element.getAttribute(attr);
      if (value && value.toLowerCase().startsWith('javascript:')) {
        element.removeAttribute(attr);
      }
    });
  });
  
  return temp.innerHTML;
};

/**
 * Sanitize and extract text content from HTML
 * @param {string} html - HTML string
 * @returns {string} - Plain text content
 */
export const htmlToText = (html) => {
  if (!html || typeof html !== 'string') return '';
  
  const temp = document.createElement('div');
  temp.innerHTML = html;
  return temp.textContent || temp.innerText || '';
};

/**
 * Check if HTML contains potentially dangerous content
 * @param {string} html - HTML string to check
 * @returns {boolean} - True if dangerous content detected
 */
export const containsDangerousContent = (html) => {
  if (!html || typeof html !== 'string') return false;
  
  const dangerousPatterns = [
    /<script[\s\S]*?>[\s\S]*?<\/script>/gi,
    /javascript:/gi,
    /on\w+\s*=/gi, // Event handlers like onclick=
    /<iframe/gi,
    /<embed/gi,
    /<object/gi,
  ];
  
  return dangerousPatterns.some(pattern => pattern.test(html));
};

export default { sanitizeHtml, htmlToText, containsDangerousContent };
