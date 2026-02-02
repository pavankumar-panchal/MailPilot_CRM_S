import { useState, useEffect, Suspense, lazy } from "react";
import { Routes, Route, useLocation, Navigate, useNavigate } from "react-router-dom";
import { API_CONFIG } from './config';

import TopProgressBar from "./components/TopProgressBar";
import PageLoader from "./components/PageLoader";
import Login from "./components/Login";
import Register from "./components/Register";
import Home from "./Home";
import Navbar from "./components/Navbar";

// Lazy load pages for better performance
const EmailVerification = lazy(() => import("./pages/EmailVerification.jsx"));
const Smtp = lazy(() => import("./pages/Smtp.jsx"));
const Campaigns = lazy(() => import("./pages/Campaigns.jsx"));
const Master = lazy(() => import("./pages/Master.jsx"));
const EmailSent = lazy(() => import("./pages/monitor/EmailSent.jsx"));
const ReceivedResponse = lazy(() => import("./pages/monitor/ReceivedResponse.jsx"));
const Workers = lazy(() => import("./pages/Workers.jsx"));
const MailTemplates = lazy(() => import("./pages/MailTemplates.jsx"));

const App = () => {
  const [isAuthenticated, setIsAuthenticated] = useState(false);
  const [authView, setAuthView] = useState('login'); // 'login' or 'register'
  const [user, setUser] = useState(null);
  const [loading, setLoading] = useState(true);

  const location = useLocation();
  const navigate = useNavigate();
  const hideNavbarRoutes = ["/table-data/2"]; // Add all routes where navbar should be hidden
  const hideNavbar = hideNavbarRoutes.includes(location.pathname);

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
          setIsAuthenticated(true);
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
    setIsAuthenticated(true);
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
    setIsAuthenticated(false);
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
    <>
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
    </>
  );
};

export default App;
