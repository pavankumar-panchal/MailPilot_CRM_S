import { useState, useEffect, Suspense, lazy } from "react";
import { Routes, Route, useLocation, Navigate, useNavigate } from "react-router-dom";
import { API_CONFIG } from './config';
import { useApp } from './contexts/AppContext';
import { useWebVitals } from './hooks/usePerformanceMonitor';
import ErrorBoundary from './components/ErrorBoundary';

import TopProgressBar from "./components/TopProgressBar";
import PageLoader from "./components/PageLoader";
import Login from "./components/Login";
import Register from "./components/Register";
import Home from "./Home";
import Navbar from "./components/Navbar";

// Lazy load pages for better performance with prefetching
const EmailVerification = lazy(() => import("./pages/EmailVerification.jsx"));
const Smtp = lazy(() => import("./pages/Smtp.jsx"));
const Campaigns = lazy(() => import("./pages/Campaigns.jsx"));
const Master = lazy(() => import("./pages/Master.jsx"));
const EmailSent = lazy(() => import("./pages/monitor/EmailSent.jsx"));
const ReceivedResponse = lazy(() => import("./pages/monitor/ReceivedResponse.jsx"));
const Workers = lazy(() => import("./pages/Workers.jsx"));
const MailTemplates = lazy(() => import("./pages/MailTemplates.jsx"));

const App = () => {
  // Use global app context instead of local state
  const { user, isAuthenticated, setUser, setAuth, networkStatus } = useApp();
  const [authView, setAuthView] = useState('login');
  const [loading, setLoading] = useState(true);

  // Monitor web vitals for performance tracking
  useWebVitals();

  const location = useLocation();
  const navigate = useNavigate();
  const hideNavbarRoutes = ["/table-data/2"]; // Add all routes where navbar should be hidden
  const hideNavbar = hideNavbarRoutes.includes(location.pathname);

  // Network status monitoring
  useEffect(() => {
    const handleOnline = () => {
      console.log('Network: ONLINE');
      // You could show a toast notification here
    };
    
    const handleOffline = () => {
      console.log('Network: OFFLINE');
      // You could show a toast notification here
    };

    window.addEventListener('online', handleOnline);
    window.addEventListener('offline', handleOffline);

    return () => {
      window.removeEventListener('online', handleOnline);
      window.removeEventListener('offline', handleOffline);
    };
  }, []);

  // Check authentication and token expiry on mount
  useEffect(() => {
    const savedUser = localStorage.getItem('mailpilot_user');
    const savedToken = localStorage.getItem('mailpilot_token');
    const savedExpiry = localStorage.getItem('mailpilot_token_expiry');
    
    if (savedUser && savedToken && savedExpiry) {
      try {
        const userData = JSON.parse(savedUser);
        const expiryDate = new Date(savedExpiry);
        const now = new Date();
        
        // Check if token is still valid (within 24 hours)
        if (expiryDate > now) {
          setUser(userData);
          setAuth(true);
        } else {
          // Token expired, clear localStorage
          localStorage.removeItem('mailpilot_user');
          localStorage.removeItem('mailpilot_token');
          localStorage.removeItem('mailpilot_token_expiry');
          alert('Your session has expired. Please login again.');
        }
      } catch (e) {
        localStorage.removeItem('mailpilot_user');
        localStorage.removeItem('mailpilot_token');
        localStorage.removeItem('mailpilot_token_expiry');
      }
    }
    setLoading(false);
  }, []);

  const handleLogin = (userData, token) => {
    setUser(userData);
    setAuth(true);
  };

  const handleRegister = () => {
    setAuthView('login');
  };

  const handleLogout = async () => {
    // Call logout API
    try {
      await fetch(API_CONFIG.API_LOGOUT, {
        method: 'POST',
        credentials: 'include',
      });
    } catch (error) {
      console.error('Logout error:', error);
    }
    
    // Clear local storage and state
    localStorage.removeItem('mailpilot_user');
    localStorage.removeItem('mailpilot_token');
    localStorage.removeItem('mailpilot_token_expiry');
    setUser(null);
    setAuth(false);
    setAuthView('login');
  };

  // Show loading state
  if (loading) {
    return (
      <div className="min-h-screen flex items-center justify-center bg-gradient-to-br from-purple-600 to-blue-600">
        <div className="text-white text-center">
          <svg className="animate-spin h-12 w-12 mx-auto mb-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
            <circle className="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" strokeWidth="4"></circle>
            <path className="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
          </svg>
          <p className="text-lg font-semibold">Loading Relyon CRM...</p>
        </div>
      </div>
    );
  }

  return (
    <ErrorBoundary>
      {/* Network status indicator */}
      {networkStatus === 'offline' && (
        <div className="fixed top-0 left-0 right-0 bg-yellow-500 text-white text-center py-2 px-4 z-50">
          <i className="fas fa-wifi-slash mr-2"></i>
          You are currently offline. Some features may not be available.
        </div>
      )}
      
      {isAuthenticated && !hideNavbar && <Navbar user={user} onLogout={handleLogout} />}
      {isAuthenticated && <TopProgressBar />}
      <main 
        id="main-content" 
        role="main" 
        aria-label="Main application content"
        className={isAuthenticated && !hideNavbar ? "pt-16" : ""}
      >
        <Suspense fallback={<PageLoader />}>
          <Routes>
            {/* Public routes */}
            <Route
              path="/login"
              element={
                isAuthenticated
                  ? <Navigate to="/" replace />
                  : <Login onLogin={(u, t) => { handleLogin(u, t); navigate('/'); }} onSwitchToRegister={() => navigate('/register')} />
              }
            />
            <Route
              path="/register"
              element={
                isAuthenticated
                  ? <Navigate to="/" replace />
                  : <Register onRegister={() => navigate('/login')} onSwitchToLogin={() => navigate('/login')} />
              }
            />

            {/* Protected routes */}
            <Route
              path="/"
              element={
                isAuthenticated ? <Home user={user} /> : <Navigate to="/login" replace />
              }
            />
            <Route
              path="/email-verification"
              element={
                isAuthenticated ? <EmailVerification user={user} /> : <Navigate to="/login" replace />
              }
            />
            <Route
              path="/smtp"
              element={
                isAuthenticated ? <Smtp user={user} /> : <Navigate to="/login" replace />
              }
            />
            <Route
              path="/campaigns"
              element={
                isAuthenticated ? <Campaigns user={user} /> : <Navigate to="/login" replace />
              }
            />
            <Route
              path="/master"
              element={
                isAuthenticated ? <Master user={user} /> : <Navigate to="/login" replace />
              }
            />
            <Route
              path="/mail-templates"
              element={
                isAuthenticated ? <MailTemplates user={user} /> : <Navigate to="/login" replace />
              }
            />
            <Route
              path="/monitor/email-sent"
              element={
                isAuthenticated ? <EmailSent user={user} /> : <Navigate to="/login" replace />
              }
            />
            <Route
              path="/monitor/received-response"
              element={
                isAuthenticated ? <ReceivedResponse user={user} /> : <Navigate to="/login" replace />
              }
            />
            <Route
              path="/workers"
              element={
                isAuthenticated ? <Workers user={user} /> : <Navigate to="/login" replace />
              }
            />

            {/* Fallback */}
            <Route path="*" element={<Navigate to={isAuthenticated ? "/" : "/login"} replace />} />
          </Routes>
        </Suspense>
      </main>
    </ErrorBoundary>
  );
};

export default App;
