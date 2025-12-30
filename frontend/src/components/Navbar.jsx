import React, { useState, useRef, useEffect } from "react";
import { NavLink, useLocation } from "react-router-dom";

const navLinks = [
  { to: "/", icon: "fa-check-circle", label: "Verification" },
  { to: "/smtp", icon: "fa-server", label: "SMTP" },
  { to: "/workers", icon: "fa-users-cog", label: "Workers" },
  { to: "/campaigns", icon: "fa-bullhorn", label: "Campaigns" },
  { to: "/mail-templates", icon: "fa-file-code", label: "Mail Templates" },
  { to: "/master", icon: "fa-crown", label: "Master" },
];

const monitorLinks = [
  { to: "/monitor/email-sent", icon: "fa-paper-plane", label: "Email Sent" },
  { to: "/monitor/received-response", icon: "fa-reply", label: "Received Response" },
];

export default function Navbar() {
  const [mobileMenuOpen, setMobileMenuOpen] = useState(false);
  const [monitorDropdownOpen, setMonitorDropdownOpen] = useState(false);
  const [monitorMobileOpen, setMonitorMobileOpen] = useState(false);
  const monitorRef = useRef();
  const location = useLocation();

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

  // Close all dropdowns on route change
  useEffect(() => {
    setMonitorDropdownOpen(false);
    setMonitorMobileOpen(false);
    setMobileMenuOpen(false);
  }, [location.pathname]);

  return (
    <nav className="fixed top-0 left-0 right-0 bg-white shadow-sm z-50" role="navigation" aria-label="Main navigation">
      <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div className="flex justify-between h-16">
          {/* Logo */}
          <div className="flex items-center">
            <div className="flex-shrink-0 flex items-center">
              <i className="fas fa-envelope text-blue-600 mr-2" aria-hidden="true"></i>
              <span className="text-gray-800 font-semibold">Email System</span>
            </div>
          </div>

          {/* Desktop Nav */}
          <div className="hidden md:flex items-center space-x-1">
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

            {/* Monitor Dropdown */}
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

            {/* Mobile Monitor Dropdown */}
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
          </div>
        </div>
      )}
    </nav>
  );
}
