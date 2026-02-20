import { defineConfig } from "vite";
import react from "@vitejs/plugin-react";
import tailwindcss from '@tailwindcss/vite';
import compression from 'vite-plugin-compression';

// https://vite.dev/config/
export default defineConfig({
  plugins: [
    react({
      // Enable Fast Refresh for better DX
      fastRefresh: true,
    }),
    tailwindcss(),
    // Gzip compression for production builds
    compression({
      algorithm: 'gzip',
      ext: '.gz',
      threshold: 1024, // Only compress files > 1kb
      deleteOriginFile: false
    }),
    // Brotli compression for even better compression
    compression({
      algorithm: 'brotliCompress',
      ext: '.br',
      threshold: 1024,
      deleteOriginFile: false
    })
  ],
  base: './', // Use relative paths for production
  
  build: {
    // Target modern browsers for smaller bundles
    target: 'es2020',
    
    // Optimize chunk splitting for better TBT/LCP
    rollupOptions: {
      output: {
        manualChunks(id) {
          // Split vendor chunks more aggressively
          if (id.includes('node_modules')) {
            // React core - most frequently used
            if (id.includes('react/') && !id.includes('react-router')) {
              return 'react-core';
            }
            if (id.includes('react-dom/')) {
              return 'react-dom';
            }
            // React Router - separate for route-based loading
            if (id.includes('react-router')) {
              return 'react-router';
            }
            // Quill editor - only loaded on Campaigns/Master pages
            if (id.includes('quill')) {
              return 'editor';
            }
            // Axios - separate for API calls
            if (id.includes('axios')) {
              return 'axios';
            }
            // Utilities
            if (id.includes('lodash') || id.includes('uuid')) {
              return 'utils';
            }
            // All other node_modules in separate vendor chunk
            return 'vendor';
          }
        },
        // Optimize chunk file names
        chunkFileNames: 'assets/[name]-[hash].js',
        entryFileNames: 'assets/[name]-[hash].js',
        assetFileNames: 'assets/[name]-[hash].[ext]'
      }
    },
    
    // Reduce chunk size warnings threshold
    chunkSizeWarningLimit: 300,
    
    // Disable source maps for smaller production bundles
    sourcemap: false,
    
    // Minification settings - optimize for production
    minify: 'terser',
    terserOptions: {
      compress: {
        drop_console: true, // Remove console.logs in production
        drop_debugger: true,
        pure_funcs: ['console.log', 'console.info', 'console.debug', 'console.warn'], // Remove specific console methods
        passes: 3, // Multiple compression passes for better optimization
        dead_code: true,
        conditionals: true,
        evaluate: true,
        booleans: true,
        loops: true,
        unused: true,
        toplevel: true,
        if_return: true,
        inline: true,
        join_vars: true,
        reduce_vars: true,
        side_effects: true,
      },
      mangle: {
        safari10: true, // Safari 10+ compatibility
        toplevel: true,
      },
      format: {
        comments: false, // Remove all comments
      },
    },
    
    // CSS code splitting
    cssCodeSplit: true,
    
    // Report compressed size
    reportCompressedSize: true,
    
    // Increase chunk size limit slightly
    assetsInlineLimit: 4096, // Inline assets < 4kb as base64
  },
  
  // Optimize dependencies
  optimizeDeps: {
    include: ['react', 'react-dom', 'react-router-dom', 'axios', 'quill']
  },
  
  // Performance optimizations for dev server
  server: {
    port: 5174, // Fixed port
    hmr: {
      overlay: true,
    },
    // Proxy backend requests to the local Apache server so the dev server
    // doesn't try to serve backend PHP files itself. This ensures calls to
    // /verify_emails/* reach the proper PHP backend on port 80.
    proxy: {
      '/api': {
        target: 'http://localhost',
        changeOrigin: true,
        rewrite: (path) => path.replace(/^\/api/, '/verify_emails/MailPilot_CRM_S/backend/app')
      },
      // Proxy any requests that begin with the project path to Apache
      '/verify_emails': {
        target: 'http://localhost',
        changeOrigin: true,
        secure: false,
        // Do not rewrite the path; keep the same absolute path so API
        // routing in backend/routes/api.php continues to work.
      },
    },
  },
});

