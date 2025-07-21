import React, { useState } from "react";
import { Link, NavLink } from "react-router-dom";

const navLinks = [
  { to: "/", icon: "fa-check-circle", label: "Verification" },
  { to: "/smtp", icon: "fa-server", label: "SMTP" },
  { to: "/workers", icon: "fa-users-cog", label: "Workers" }, // Moved Workers link here
  { to: "/campaigns", icon: "fa-bullhorn", label: "Campaigns" },
  { to: "/master", icon: "fa-crown", label: "Master" },
];

const monitorLinks = [
  { to: "/monitor/email-sent", icon: "fa-paper-plane", label: "Email Sent" },
  {
    to: "/monitor/received-response",
    icon: "fa-reply",
    label: "Received Response",
  },
];

export default function Navbar() {
  const [mobileMenuOpen, setMobileMenuOpen] = useState(false);
  const [monitorDropdownOpen, setMonitorDropdownOpen] = useState(false);
  const [monitorMobileOpen, setMonitorMobileOpen] = useState(false);

  return (
    <nav className="fixed top-0 left-0 right-0 bg-white shadow-sm z-50">
      <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div className="flex justify-between h-16 ">
          {/* Logo/Brand */}
          <div className="flex items-center">
            <div className="flex-shrink-0 flex items-center">
              <i className="fas fa-envelope text-blue-600 mr-2"></i>
              <span className="text-gray-800 font-semibold">Email System</span>
            </div>
          </div>

          {/* Desktop Nav */}
          <div className="hidden md:flex items-center space-x-1">
            {navLinks.map((link) => (
              <NavLink
                key={link.to}
                to={link.to}
                className={({ isActive }) =>
                  `${
                    isActive
                      ? "bg-blue-50 text-blue-600"
                      : "text-gray-600 hover:bg-blue-50 hover:text-blue-600"
                  } px-3 py-2 rounded-md text-sm font-medium flex items-center`
                }
              >
                <i className={`fas ${link.icon} mr-2`}></i>
                {link.label}
              </NavLink>
            ))}

            {/* Monitor Dropdown */}
            <div className="relative">
              <button
                onClick={() => setMonitorDropdownOpen((v) => !v)}
                onBlur={() =>
                  setTimeout(() => setMonitorDropdownOpen(false), 150)
                }
                className={`${
                  window.location.pathname.startsWith("/monitor/")
                    ? "bg-blue-50 text-blue-600"
                    : "text-gray-600 hover:bg-blue-50 hover:text-blue-600"
                } px-3 py-2 rounded-md text-sm font-medium flex items-center`}
                type="button"
              >
                <i className="fas fa-chart-line mr-2"></i> Monitor
                <i className="fas fa-chevron-down ml-1 text-xs"></i>
              </button>
              {monitorDropdownOpen && (
                <div className="origin-top-right absolute right-0 mt-2 w-56 rounded-md shadow-lg bg-white ring-1 ring-black ring-opacity-5 z-50">
                  <div className="py-1">
                    {monitorLinks.map((link) => (
                      <NavLink
                        key={link.to}
                        to={link.to}
                        className={({ isActive }) =>
                          `block px-4 py-2 text-sm flex items-center ${
                            isActive
                              ? "bg-blue-50 text-blue-600"
                              : "text-gray-700 hover:bg-blue-50 hover:text-blue-600"
                          }`
                        }
                      >
                        <i
                          className={`fas ${link.icon} mr-2 w-4 text-center`}
                        ></i>
                        {link.label}
                      </NavLink>
                    ))}
                  </div>
                </div>
              )}
            </div>
          </div>

          {/* Mobile menu button */}
          <div className="-mr-2 flex items-center md:hidden">
            <button
              onClick={() => setMobileMenuOpen((v) => !v)}
              type="button"
              className="inline-flex items-center justify-center p-2 rounded-md text-gray-400 hover:text-gray-500 hover:bg-gray-100 focus:outline-none"
            >
              <span className="sr-only">Open main menu</span>
              <i
                className={`fas ${mobileMenuOpen ? "fa-times" : "fa-bars"}`}
              ></i>
            </button>
          </div>
        </div>
      </div>

      {/* Mobile menu */}
      {mobileMenuOpen && (
        <div className="md:hidden bg-white border-t border-gray-200 shadow">
          <div className="pt-2 pb-3 space-y-1">
            {navLinks.map((link) => (
              <NavLink
                key={link.to}
                to={link.to}
                className={({ isActive }) =>
                  `block pl-3 pr-4 py-2 border-l-4 text-base font-medium flex items-center ${
                    isActive
                      ? "bg-blue-50 text-blue-600 border-blue-500"
                      : "text-gray-600 hover:bg-blue-50 hover:text-blue-600 border-transparent"
                  }`
                }
                onClick={() => setMobileMenuOpen(false)}
              >
                <i className={`fas ${link.icon} mr-2`}></i>
                {link.label}
              </NavLink>
            ))}

            {/* Mobile Monitor Dropdown */}
            <div className="border-t border-gray-200 pt-2">
              <button
                onClick={() => setMonitorMobileOpen((v) => !v)}
                className={`w-full pl-3 pr-4 py-2 border-l-4 text-base font-medium flex justify-between items-center ${
                  window.location.pathname.startsWith("/monitor/")
                    ? "bg-blue-50 text-blue-600 border-blue-500"
                    : "text-gray-600 hover:bg-blue-50 hover:text-blue-600 border-transparent"
                }`}
              >
                <div className="flex items-center">
                  <i className="fas fa-chart-line mr-2"></i> Monitor
                </div>
                <i
                  className={`fas fa-chevron-right transition-transform duration-200 ${
                    monitorMobileOpen ? "transform rotate-90" : ""
                  }`}
                ></i>
              </button>
              {monitorMobileOpen && (
                <div className="pl-8">
                  {monitorLinks.map((link) => (
                    <NavLink
                      key={link.to}
                      to={link.to}
                      className={({ isActive }) =>
                        `block pl-3 pr-4 py-2 text-base font-medium flex items-center ${
                          isActive
                            ? "bg-blue-50 text-blue-600"
                            : "text-gray-600 hover:bg-blue-50 hover:text-blue-600"
                        }`
                      }
                      onClick={() => setMobileMenuOpen(false)}
                    >
                      <i className={`fas ${link.icon} mr-2`}></i>
                      {link.label}
                    </NavLink>
                  ))}
                </div>
              )}
            </div>
          </div>
        </div>
      )}
      {/* Spacer for fixed navbar */}
      {/* <div className="h-16"></div> */}
    </nav>
  );
}
