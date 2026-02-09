import React, { useState, useEffect } from 'react';
import { Link } from 'react-router-dom';
import axios from './api/axios';
import { API_CONFIG } from './config';

const Home = ({ user }) => {
  const [stats, setStats] = useState({
    totalCampaigns: 0,
    emailsSent: 0,
    activeSmtp: 0,
    successRate: 0,
  });
  const [loading, setLoading] = useState(true);

  // Fetch dashboard stats
  useEffect(() => {
    const fetchStats = async () => {
      try {
        // Fetch campaigns
        const campaignsRes = await axios.post(API_CONFIG.API_MASTER_CAMPAIGNS, { action: 'list' });
        const campaigns = campaignsRes.data?.data?.campaigns || [];
        
        // Fetch SMTP servers
        const smtpRes = await axios.get(API_CONFIG.API_MASTER_SMTPS);
        const smtpServers = smtpRes.data?.data || [];
        
        // Calculate stats
        const totalCampaigns = campaigns.length;
        const emailsSent = campaigns.reduce((sum, c) => sum + (parseInt(c.sent_emails) || 0), 0);
        const totalEmails = campaigns.reduce((sum, c) => sum + (parseInt(c.sent_emails) || 0) + (parseInt(c.failed_emails) || 0), 0);
        const successRate = totalEmails > 0 ? Math.round((emailsSent / totalEmails) * 100) : 0;
        
        // Count active SMTP accounts
        const activeSmtp = smtpServers.reduce((sum, server) => {
          return sum + (server.accounts?.filter(acc => acc.is_active)?.length || 0);
        }, 0);
        
        setStats({
          totalCampaigns,
          emailsSent,
          activeSmtp,
          successRate,
        });
      } catch (error) {
        console.error('Failed to fetch stats:', error);
      } finally {
        setLoading(false);
      }
    };
    
    fetchStats();
  }, []);

  const quickActions = [
    {
      title: 'Email Verification',
      description: 'Upload and verify email lists',
      path: '/email-verification',
      icon: (
        <svg className="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
        </svg>
      ),
      gradient: 'from-emerald-500 to-teal-600',
    },
    {
      title: 'SMTP Servers',
      description: 'Manage email servers',
      path: '/smtp',
      icon: (
        <svg className="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M5 12h14M5 12a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v4a2 2 0 01-2 2M5 12a2 2 0 00-2 2v4a2 2 0 002 2h14a2 2 0 002-2v-4a2 2 0 00-2-2m-2-4h.01M17 16h.01" />
        </svg>
      ),
      gradient: 'from-blue-500 to-indigo-600',
    },
    {
      title: 'Campaigns',
      description: 'Create email campaigns',
      path: '/campaigns',
      icon: (
        <svg className="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z" />
        </svg>
      ),
      gradient: 'from-violet-500 to-purple-600',
    },
    {
      title: 'Master Control',
      description: 'Advanced campaign management',
      path: '/master',
      icon: (
        <svg className="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2" />
        </svg>
      ),
      gradient: 'from-orange-500 to-amber-600',
    },
    {
      title: 'Templates',
      description: 'Email template designer',
      path: '/mail-templates',
      icon: (
        <svg className="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M7 21h10a2 2 0 002-2V9.414a1 1 0 00-.293-.707l-5.414-5.414A1 1 0 0012.586 3H7a2 2 0 00-2 2v14a2 2 0 002 2z" />
        </svg>
      ),
      gradient: 'from-pink-500 to-rose-600',
    },
    {
      title: 'Workers',
      description: 'Monitor email workers',
      path: '/workers',
      icon: (
        <svg className="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M13 10V3L4 14h7v7l9-11h-7z" />
        </svg>
      ),
      gradient: 'from-cyan-500 to-blue-600',
      adminOnly: true,
    },
  ];

  // Filter actions based on user role
  const filteredActions = quickActions.filter(action => {
    if (action.adminOnly && user?.role !== 'admin') {
      return false;
    }
    return true;
  });

  return (
    <div className="min-h-screen bg-gradient-to-br from-slate-50 via-blue-50 to-indigo-50">
      <div className="container mx-auto px-4 sm:px-6 lg:px-8 py-4 sm:py-6 lg:py-8 max-w-7xl">
        {/* Header */}
        <div className="glass-effect rounded-2xl p-4 sm:p-5 lg:p-6 mb-4 sm:mb-6 border border-white/20">
          <div className="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4">
            <div>
              <h1 className="text-2xl sm:text-3xl font-bold text-gray-800 mb-2">
                Welcome back, <span className="text-blue-600">{user?.name || 'User'}</span>
              </h1>
              <p className="text-sm sm:text-base text-gray-600">Relyon Email Campaign Management</p>
            </div>
            <div className="flex items-center gap-2 px-3 sm:px-4 py-2 bg-green-50 rounded-full border border-green-200">
              <span className="relative flex h-2 w-2">
                <span className="animate-ping absolute inline-flex h-full w-full rounded-full bg-green-400 opacity-75"></span>
                <span className="relative inline-flex rounded-full h-2 w-2 bg-green-500"></span>
              </span>
              <span className="text-xs sm:text-sm text-green-700 font-medium">Online</span>
            </div>
          </div>
        </div>

        {/* Stats Grid */}
        <div className="grid grid-cols-2 lg:grid-cols-4 gap-3 sm:gap-4 mb-4 sm:mb-6">
          <div className="glass-effect rounded-xl p-3 sm:p-4 border border-white/20 hover:shadow-lg transition-all duration-300">
            <div className="flex items-center justify-between mb-2">
              <span className="text-gray-600 text-xs sm:text-sm font-medium">Total Campaigns</span>
              <div className="p-1.5 sm:p-2 bg-blue-100 rounded-lg">
                <svg className="w-4 h-4 sm:w-5 sm:h-5 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z" />
                </svg>
              </div>
            </div>
            <p className="text-2xl sm:text-3xl font-bold text-gray-800">
              {loading ? '...' : stats.totalCampaigns.toLocaleString()}
            </p>
          </div>

          <div className="glass-effect rounded-xl p-4 sm:p-6 border border-white/20 hover:shadow-lg transition-all duration-300">
            <div className="flex items-center justify-between mb-2">
              <span className="text-gray-600 text-xs sm:text-sm font-medium">Emails Sent</span>
              <div className="p-1.5 sm:p-2 bg-green-100 rounded-lg">
                <svg className="w-4 h-4 sm:w-5 sm:h-5 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z" />
                </svg>
              </div>
            </div>
            <p className="text-2xl sm:text-3xl font-bold text-gray-800">
              {loading ? '...' : stats.emailsSent.toLocaleString()}
            </p>
          </div>

          <div className="glass-effect rounded-xl p-4 sm:p-6 border border-white/20 hover:shadow-lg transition-all duration-300">
            <div className="flex items-center justify-between mb-2">
              <span className="text-gray-600 text-xs sm:text-sm font-medium">Active SMTP</span>
              <div className="p-1.5 sm:p-2 bg-purple-100 rounded-lg">
                <svg className="w-4 h-4 sm:w-5 sm:h-5 text-purple-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M5 12h14M5 12a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v4a2 2 0 01-2 2M5 12a2 2 0 00-2 2v4a2 2 0 002 2h14a2 2 0 002-2v-4a2 2 0 00-2-2m-2-4h.01M17 16h.01" />
                </svg>
              </div>
            </div>
            <p className="text-2xl sm:text-3xl font-bold text-gray-800">
              {loading ? '...' : stats.activeSmtp.toLocaleString()}
            </p>
          </div>

          <div className="glass-effect rounded-xl p-4 sm:p-6 border border-white/20 hover:shadow-lg transition-all duration-300">
            <div className="flex items-center justify-between mb-2">
              <span className="text-gray-600 text-xs sm:text-sm font-medium">Success Rate</span>
              <div className="p-1.5 sm:p-2 bg-orange-100 rounded-lg">
                <svg className="w-4 h-4 sm:w-5 sm:h-5 text-orange-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6" />
                </svg>
              </div>
            </div>
            <p className="text-2xl sm:text-3xl font-bold text-gray-800">
              {loading ? '...' : `${stats.successRate}%`}
            </p>
          </div>
        </div>

        {/* Quick Actions */}
        <div className="glass-effect rounded-2xl p-4 sm:p-5 lg:p-6 border border-white/20">
          <h2 className="text-lg sm:text-xl font-bold text-gray-800 mb-3 sm:mb-4">Quick Actions</h2>
          <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-3">
            {filteredActions.map((action, index) => (
              <Link
                key={index}
                to={action.path}
                className="group relative overflow-hidden bg-white/50 backdrop-blur-sm rounded-xl p-4 border border-white/20 hover:bg-white/70 hover:shadow-xl transition-all duration-300"
              >
                <div className="flex items-start gap-3 sm:gap-4">
                  <div className={`p-2 sm:p-3 bg-gradient-to-br ${action.gradient} rounded-xl text-white group-hover:scale-110 transition-transform duration-300 shadow-lg flex-shrink-0`}>
                    {action.icon}
                  </div>
                  <div className="flex-1 min-w-0">
                    <h3 className="text-base sm:text-lg font-semibold text-gray-800 mb-1 group-hover:text-blue-600 transition-colors truncate">
                      {action.title}
                    </h3>
                    <p className="text-xs sm:text-sm text-gray-600 line-clamp-2">{action.description}</p>
                  </div>
                </div>
                <div className="absolute bottom-3 sm:bottom-4 right-3 sm:right-4 opacity-0 group-hover:opacity-100 transition-opacity duration-300">
                  <svg className="w-4 h-4 sm:w-5 sm:h-5 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M13 7l5 5m0 0l-5 5m5-5H6" />
                  </svg>
                </div>
              </Link>
            ))}
          </div>
        </div>
      </div>
    </div>
  );
};

export default Home;
