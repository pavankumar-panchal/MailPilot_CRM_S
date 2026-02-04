/**
 * Logging Utility for Production
 * Only logs in development mode when enabled
 */

const isDevelopment = import.meta.env.DEV;
const enableLogs = import.meta.env.VITE_ENABLE_CONSOLE_LOGS === 'true';

export const logger = {
  log: (...args) => {
    if (isDevelopment && enableLogs) {
      console.log(...args);
    }
  },
  
  info: (...args) => {
    if (isDevelopment && enableLogs) {
      console.info(...args);
    }
  },
  
  warn: (...args) => {
    if (isDevelopment && enableLogs) {
      console.warn(...args);
    }
  },
  
  error: (...args) => {
    // Always log errors, even in production
    console.error(...args);
  },
  
  debug: (...args) => {
    if (isDevelopment && enableLogs) {
      console.debug(...args);
    }
  },
};

export default logger;
