import React, { useState, useRef, useEffect } from "react";
import { NavLink, useLocation } from "react-router-dom";
import { getBaseUrl } from "../config";

const BASE_URL = getBaseUrl();

const allNavLinks = [
  { to: "/", icon: "fa-home", label: "Home", roles: ["admin", "user"] },
  { to: "/email-verification", icon: "fa-check-circle", label: "Verification", roles: ["admin", "user"] },
  { to: "/smtp", icon: "fa-server", label: "SMTP", roles: ["admin", "user"] },
  { to: "/workers", icon: "fa-users-cog", label: "Workers", roles: ["admin"] }, // Admin only
  { to: "/campaigns", icon: "fa-bullhorn", label: "Campaigns", roles: ["admin", "user"] },
  { to: "/mail-templates", icon: "fa-file-code", label: "Mail Templates", roles: ["admin", "user"] },
  { to: "/master", icon: "fa-crown", label: "Master", roles: ["admin", "user"] },
];

// COMMENTED OUT - Monitor links hidden until needed
/*
const monitorLinks = [
  { to: "/monitor/email-sent", icon: "fa-paper-plane", label: "Email Sent" },
  { to: "/monitor/received-response", icon: "fa-reply", label: "Received Response" },
];
*/

export default function Navbar({ user, onLogout }) {
  // Filter navigation links based on user role
  const navLinks = allNavLinks.filter(link => 
    link.roles.includes(user?.role || 'user')
  );
  const [mobileMenuOpen, setMobileMenuOpen] = useState(false);
  const [userMenuOpen, setUserMenuOpen] = useState(false);
  // COMMENTED OUT - Monitor dropdown hidden until needed
  /*
  const [monitorDropdownOpen, setMonitorDropdownOpen] = useState(false);
  const [monitorMobileOpen, setMonitorMobileOpen] = useState(false);
  const monitorRef = useRef();
  */
  const location = useLocation();

  // COMMENTED OUT - Monitor dropdown handler hidden until needed
  /*
  // Handle clicks outside monitor dropdown
  useEffect(() => {
    const handleClickOutside = (event) => {
      if (monitorRef.current && !monitorRef.current.contains(event.target)) {
        setMonitorDropdownOpen(false);
      }
    };
    document.addEventListener("mousedown", handleClickOutside);
    return () => document.removeEventListener("mousedown", handleClickOutside);
  }, []);
  */

  // Close all dropdowns on route change
  useEffect(() => {
    // COMMENTED OUT - Monitor dropdown state hidden until needed
    /*
    setMonitorDropdownOpen(false);
    setMonitorMobileOpen(false);
    */
    setMobileMenuOpen(false);
  }, [location.pathname]);

  return (
    <nav className="fixed top-0 left-0 right-0 bg-white shadow-sm z-50" role="navigation" aria-label="Main navigation">
      <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div className="flex justify-between h-16">
          {/* Logo */}
          <div className="flex items-center">
            <div className="flex-shrink-0 flex items-center">
              <img 
                src="data:image/svg+xml;base64,PHN2ZyB4bWxucz0iaHR0cDovL3d3dy53My5vcmcvMjAwMC9zdmciIHZpZXdCb3g9IjAgMCAxMDAgMTAwIj4KICA8cmVjdCB3aWR0aD0iMTAwIiBoZWlnaHQ9IjEwMCIgcng9IjIwIiBmaWxsPSIjM0I4MkY2Ii8+CiAgPHBhdGggZD0iTTI1IDM1IEw1MCA1NSBMNzUgMzUgTDc1IDY1IEwyNSA2NSBaIiBmaWxsPSJ3aGl0ZSIgc3Ryb2tlPSJ3aGl0ZSIgc3Ryb2tlLXdpZHRoPSIzIiBzdHJva2UtbGluZWpvaW49InJvdW5kIi8+CiAgPGNpcmNsZSBjeD0iNzAiIGN5PSIzMCIgcj0iOCIgZmlsbD0iIzEwQjk4MSIvPgo8L3N2Zz4="
                alt="Relyon CRM Logo" 
                className="h-8 w-8 mr-2"
                style={{ imageRendering: 'crisp-edges' }}
              />
              <span className="text-gray-800 font-semibold text-lg">Relyon CRM</span>
            </div>
          </div>

          {/* Desktop Nav */}
          <div className="hidden md:flex items-center space-x-1">
            {/* Navigation Links */}
            {navLinks.map((link) => (
              <NavLink
                key={link.to}
                to={link.to}
                aria-label={`Navigate to ${link.label}`}
                className={({ isActive }) =>
                  `${isActive ? "bg-blue-50 text-blue-600" : "text-gray-600 hover:bg-blue-50 hover:text-blue-600"
                  } px-3 py-2 rounded-md text-sm font-medium flex items-center`
                }
              >
                <i className={`fas ${link.icon} mr-2`} aria-hidden="true"></i>
                {link.label}
              </NavLink>
            ))}

            {/* COMMENTED OUT - Monitor Dropdown hidden until needed */}
            {/*
            <div className="relative" ref={monitorRef}>
              <button
                onClick={() => setMonitorDropdownOpen((v) => !v)}
                aria-expanded={monitorDropdownOpen}
                aria-haspopup="true"
                aria-label="Monitor menu"
                className={`${
                  location.pathname.startsWith("/monitor/")
                    ? "bg-blue-50 text-blue-600"
                    : "text-gray-600 hover:bg-blue-50 hover:text-blue-600"
                } px-3 py-2 rounded-md text-sm font-medium flex items-center`}
              >
                <i className="fas fa-chart-line mr-2" aria-hidden="true"></i> Monitor
                <i className="fas fa-chevron-down ml-1 text-xs" aria-hidden="true"></i>
              </button>
              {monitorDropdownOpen && (
                <div className="origin-top-right absolute right-0 mt-2 w-56 rounded-md shadow-lg bg-white ring-1 ring-black ring-opacity-5 z-50" role="menu">
                  <div className="py-1">
                    {monitorLinks.map((link) => (
                      <NavLink
                        key={link.to}
                        to={link.to}
                        role="menuitem"
                        aria-label={`Navigate to ${link.label}`}
                        className={({ isActive }) =>
                          `flex items-center px-4 py-2 text-sm ${isActive
                            ? "bg-blue-50 text-blue-600"
                            : "text-gray-700 hover:bg-blue-50 hover:text-blue-600"}`
                        }
                      >
                        <i className={`fas ${link.icon} mr-2 w-4 text-center`} aria-hidden="true"></i>
                        {link.label}
                      </NavLink>
                    ))}
                  </div>
                </div>
              )}
            </div>
            */}
            
            {/* User Info - Desktop */}
            {user && (
              <div className="ml-4 flex items-center gap-3">
                <div className="flex items-center gap-2 px-3 py-2 bg-gray-100 rounded-lg">
                  <div className="w-8 h-8 bg-gradient-to-r from-purple-600 to-blue-600 rounded-full flex items-center justify-center text-white font-semibold">
                    {user.name.charAt(0).toUpperCase()}
                  </div>
                  <div className="text-sm">
                    <div className="font-semibold text-gray-800">{user.name}</div>
                    <div className="text-xs text-gray-500 capitalize">{user.role}</div>
                  </div>
                </div>
                <button
                  onClick={onLogout}
                  className="px-4 py-2 bg-red-500 text-white rounded-lg hover:bg-red-600 transition-colors text-sm font-medium"
                  aria-label="Logout"
                >
                  <i className="fas fa-sign-out-alt mr-2"></i>
                  Logout
                </button>
              </div>
            )}
          </div>

          {/* Mobile Menu Button */}
          <div className="-mr-2 flex items-center md:hidden">
            <button
              onClick={() => setMobileMenuOpen((v) => !v)}
              aria-expanded={mobileMenuOpen}
              aria-label="Toggle main menu"
              className="inline-flex items-center justify-center p-2 rounded-md text-gray-400 hover:text-gray-500 hover:bg-gray-100 focus:outline-none"
            >
              <span className="sr-only">Open main menu</span>
              <i className={`fas ${mobileMenuOpen ? "fa-times" : "fa-bars"}`} aria-hidden="true"></i>
            </button>
          </div>
        </div>
      </div>

      {/* Mobile Menu */}
      {mobileMenuOpen && (
        <div className="md:hidden bg-white border-t border-gray-200 shadow">
          <div className="pt-2 pb-3 space-y-1">
            {navLinks.map((link) => (
              <NavLink
                key={link.to}
                to={link.to}
                onClick={() => setMobileMenuOpen(false)}
                aria-label={`Navigate to ${link.label}`}
                className={({ isActive }) =>
                  `flex items-center pl-3 pr-4 py-2 border-l-4 text-base font-medium ${isActive
                    ? "bg-blue-50 text-blue-600 border-blue-500"
                    : "text-gray-600 hover:bg-blue-50 hover:text-blue-600 border-transparent"}`
                }
              >
                <i className={`fas ${link.icon} mr-2`} aria-hidden="true"></i>
                {link.label}
              </NavLink>
            ))}

            {/* COMMENTED OUT - Mobile Monitor Dropdown hidden until needed */}
            {/*
            <div className="border-t border-gray-200 pt-2">
              <button
                onClick={() => setMonitorMobileOpen((v) => !v)}
                aria-expanded={monitorMobileOpen}
                aria-label="Monitor submenu"
                className={`w-full pl-3 pr-4 py-2 border-l-4 text-base font-medium flex justify-between items-center ${location.pathname.startsWith("/monitor/")
                    ? "bg-blue-50 text-blue-600 border-blue-500"
                    : "text-gray-600 hover:bg-blue-50 hover:text-blue-600 border-transparent"
                  }`}
              >
                <div className="flex items-center">
                  <i className="fas fa-chart-line mr-2" aria-hidden="true"></i> Monitor
                </div>
                <i className={`fas fa-chevron-right transition-transform duration-200 ${monitorMobileOpen ? "transform rotate-90" : ""}`} aria-hidden="true"></i>
              </button>
              {monitorMobileOpen && (
                <div className="pl-8">
                  {monitorLinks.map((link) => (
                    <NavLink
                      key={link.to}
                      to={link.to}
                      onClick={() => setMobileMenuOpen(false)}
                      aria-label={`Navigate to ${link.label}`}
                      className={({ isActive }) =>
                        `flex items-center pl-3 pr-4 py-2 text-base font-medium ${isActive
                          ? "bg-blue-50 text-blue-600"
                          : "text-gray-600 hover:bg-blue-50 hover:text-blue-600"}`
                      }
                    >
                      <i className={`fas ${link.icon} mr-2`} aria-hidden="true"></i>
                      {link.label}
                    </NavLink>
                  ))}
                </div>
              )}
            </div>
            */}
          </div>
          
          {/* User Info - Mobile */}
          {user && (
            <div className="border-t border-gray-200 pt-3 pb-3 px-4">
              <div className="flex items-center gap-3 mb-3">
                <div className="w-10 h-10 bg-gradient-to-r from-purple-600 to-blue-600 rounded-full flex items-center justify-center text-white font-semibold text-lg">
                  {user.name.charAt(0).toUpperCase()}
                </div>
                <div>
                  <div className="font-semibold text-gray-800">{user.name}</div>
                  <div className="text-sm text-gray-500">{user.email}</div>
                  <div className="text-xs text-gray-500 capitalize">Role: {user.role}</div>
                </div>
              </div>
              <button
                onClick={() => {
                  setMobileMenuOpen(false);
                  onLogout();
                }}
                className="w-full px-4 py-2 bg-red-500 text-white rounded-lg hover:bg-red-600 transition-colors text-sm font-medium flex items-center justify-center gap-2"
              >
                <i className="fas fa-sign-out-alt"></i>
                Logout
              </button>
            </div>
          )}
        </div>
      )}
    </nav>
  );
}
