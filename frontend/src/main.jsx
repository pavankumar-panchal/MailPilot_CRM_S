import { Suspense, lazy, StrictMode } from "react";
import { createRoot } from "react-dom/client";
import { BrowserRouter, Routes, Route } from "react-router-dom";

import Navbar from "./components/Navbar.jsx";
import PageLoader from "./components/PageLoader.jsx";
import "./index.css";
import TopProgressBar from "./components/TopProgressBar.jsx";

// Lazy load pages for better performance and reduced TBT
const EmailVerification = lazy(() => import("./pages/EmailVerification.jsx"));
const Smtp = lazy(() => import("./pages/Smtp.jsx"));
const Campaigns = lazy(() => import("./pages/Campaigns.jsx"));
const Master = lazy(() => import("./pages/Master.jsx"));
const EmailSent = lazy(() => import("./pages/monitor/EmailSent.jsx"));
const ReceivedResponse = lazy(() => import("./pages/monitor/ReceivedResponse.jsx"));
const Workers = lazy(() => import("./pages/Workers.jsx"));

createRoot(document.getElementById("root")).render(
  <StrictMode>
    <BrowserRouter>
      <Navbar />
      <TopProgressBar />
      <main id="main-content" role="main" aria-label="Main application content">
        <Suspense fallback={<PageLoader />}>
          <Routes>
            <Route path="/" element={<EmailVerification />} />
            <Route path="/smtp" element={<Smtp />} />
            <Route path="/campaigns" element={<Campaigns />} />
            <Route path="/master" element={<Master />} />
            <Route path="/monitor/email-sent" element={<EmailSent />} />
            <Route path="/monitor/received-response" element={<ReceivedResponse />} />
            <Route path="/workers" element={<Workers />} />
          </Routes>
        </Suspense>
      </main>
    </BrowserRouter>
  </StrictMode>
);
