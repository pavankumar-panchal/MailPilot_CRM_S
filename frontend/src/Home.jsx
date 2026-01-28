import React from 'react';
import { Link } from 'react-router-dom';

const Home = ({ user }) => {
  return (
    <div className="min-h-screen bg-gradient-to-br from-gray-50 to-gray-100">
      <div className="container mx-auto px-4 py-12 mt-8">
        <div className="max-w-6xl mx-auto">
          {/* Welcome Header */}
          <div className="bg-white rounded-2xl shadow-xl p-8 mb-8">
            <div className="flex items-center justify-between">
              <div>
                <h1 className="text-4xl font-bold text-gray-800 mb-2">
                  Welcome to Relyon CRM
                </h1>
                <p className="text-gray-600 text-lg">
                  Hello, <span className="font-semibold text-purple-600">{user?.name}</span>!
                </p>
                <p className="text-gray-500 mt-2">
                  Your powerful email campaign management system
                </p>
              </div>
              <div className="hidden md:block">
                <div className="p-4 bg-gradient-to-r from-purple-600 to-blue-600 rounded-full">
                  <svg className="w-16 h-16 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z" />
                  </svg>
                </div>
              </div>
            </div>
          </div>

          {/* Quick Actions */}
          <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
            {/* Email Verification */}
            <Link
              to="/email-verification"
              className="bg-white rounded-xl shadow-lg p-5 hover:shadow-2xl transition-all transform hover:scale-105 group"
            >
              <div className="flex items-center mb-3">
                <div className="p-2 bg-green-100 rounded-lg group-hover:bg-green-600 transition-colors">
                  <svg className="w-6 h-6 text-green-600 group-hover:text-white transition-colors" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                  </svg>
                </div>
              </div>
              <h3 className="text-lg font-bold text-gray-800 mb-1">Email Verification</h3>
              <p className="text-gray-600 text-sm">Upload and verify email lists for deliverability</p>
            </Link>

            {/* SMTP Management */}
            <Link
              to="/smtp"
              className="bg-white rounded-xl shadow-lg p-5 hover:shadow-2xl transition-all transform hover:scale-105 group"
            >
              <div className="flex items-center mb-3">
                <div className="p-2 bg-blue-100 rounded-lg group-hover:bg-blue-600 transition-colors">
                  <svg className="w-6 h-6 text-blue-600 group-hover:text-white transition-colors" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M5 12h14M5 12a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v4a2 2 0 01-2 2M5 12a2 2 0 00-2 2v4a2 2 0 002 2h14a2 2 0 002-2v-4a2 2 0 00-2-2m-2-4h.01M17 16h.01" />
                  </svg>
                </div>
              </div>
              <h3 className="text-lg font-bold text-gray-800 mb-1">SMTP Servers</h3>
              <p className="text-gray-600 text-sm">Configure and manage your SMTP accounts</p>
            </Link>

            {/* Campaigns */}
            <Link
              to="/campaigns"
              className="bg-white rounded-xl shadow-lg p-5 hover:shadow-2xl transition-all transform hover:scale-105 group"
            >
              <div className="flex items-center mb-3">
                <div className="p-2 bg-purple-100 rounded-lg group-hover:bg-purple-600 transition-colors">
                  <svg className="w-6 h-6 text-purple-600 group-hover:text-white transition-colors" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z" />
                  </svg>
                </div>
              </div>
              <h3 className="text-lg font-bold text-gray-800 mb-1">Campaigns</h3>
              <p className="text-gray-600 text-sm">Create and manage email campaigns</p>
            </Link>

            {/* Master Campaigns */}
            <Link
              to="/master"
              className="bg-white rounded-xl shadow-lg p-5 hover:shadow-2xl transition-all transform hover:scale-105 group"
            >
              <div className="flex items-center mb-3">
                <div className="p-2 bg-orange-100 rounded-lg group-hover:bg-orange-600 transition-colors">
                  <svg className="w-6 h-6 text-orange-600 group-hover:text-white transition-colors" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-3 7h3m-3 4h3m-6-4h.01M9 16h.01" />
                  </svg>
                </div>
              </div>
              <h3 className="text-lg font-bold text-gray-800 mb-1">Master Campaigns</h3>
              <p className="text-gray-600 text-sm">Advanced campaign management and tracking</p>
            </Link>

            {/* Mail Templates */}
            <Link
              to="/mail-templates"
              className="bg-white rounded-xl shadow-lg p-5 hover:shadow-2xl transition-all transform hover:scale-105 group"
            >
              <div className="flex items-center mb-3">
                <div className="p-2 bg-pink-100 rounded-lg group-hover:bg-pink-600 transition-colors">
                  <svg className="w-6 h-6 text-pink-600 group-hover:text-white transition-colors" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M7 21h10a2 2 0 002-2V9.414a1 1 0 00-.293-.707l-5.414-5.414A1 1 0 0012.586 3H7a2 2 0 00-2 2v14a2 2 0 002 2z" />
                  </svg>
                </div>
              </div>
              <h3 className="text-lg font-bold text-gray-800 mb-1">Mail Templates</h3>
              <p className="text-gray-600 text-sm">Design and save reusable email templates</p>
            </Link>

            {/* Workers - Admin Only */}
            {user?.role === 'admin' && (
              <Link
                to="/workers"
                className="bg-white rounded-xl shadow-lg p-5 hover:shadow-2xl transition-all transform hover:scale-105 group"
              >
                <div className="flex items-center mb-3">
                  <div className="p-2 bg-yellow-100 rounded-lg group-hover:bg-yellow-600 transition-colors">
                    <svg className="w-6 h-6 text-yellow-600 group-hover:text-white transition-colors" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                      <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z" />
                      <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                    </svg>
                  </div>
                </div>
                <h3 className="text-lg font-bold text-gray-800 mb-1">Workers</h3>
                <p className="text-gray-600 text-sm">Manage background job processors</p>
              </Link>
            )}
          </div>

          {/* Quick Stats */}
          <div className="mt-6 bg-white rounded-2xl shadow-xl p-6">
            <h2 className="text-xl font-bold text-gray-800 mb-4">Quick Stats</h2>
            <div className="grid grid-cols-2 md:grid-cols-4 gap-4">
              <div className="text-center">
                <div className="text-2xl font-bold text-purple-600 mb-1">--</div>
                <div className="text-gray-600 text-sm">Total Campaigns</div>
              </div>
              <div className="text-center">
                <div className="text-2xl font-bold text-blue-600 mb-1">--</div>
                <div className="text-gray-600 text-sm">Emails Sent</div>
              </div>
              <div className="text-center">
                <div className="text-2xl font-bold text-green-600 mb-1">--</div>
                <div className="text-gray-600 text-sm">Verified Emails</div>
              </div>
              <div className="text-center">
                <div className="text-2xl font-bold text-orange-600 mb-1">--</div>
                <div className="text-gray-600 text-sm">Active SMTP</div>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  );
};

export default Home;
