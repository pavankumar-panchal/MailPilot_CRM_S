import { createRoot } from "react-dom/client";
import { BrowserRouter, Routes, Route } from "react-router-dom";
import EmailVerification from "./pages/EmailVerification.jsx";
import Smtp from "./pages/Smtp.jsx";
import Campaigns from "./pages/Campaigns.jsx";
import Master from "./pages/Master.jsx";
import EmailSent from "./pages/monitor/EmailSent.jsx";
import ReceivedResponse from "./pages/monitor/ReceivedResponse.jsx";
import Navbar from "./components/Navbar.jsx";
import "./index.css";
import TopProgressBar from "./components/TopProgressBar.jsx";
import Workers from "./pages/Workers.jsx";

createRoot(document.getElementById("root")).render(
  <BrowserRouter>
    <Navbar />
    <TopProgressBar />
    <Routes>
      <Route path="/" element={<EmailVerification />} />
      <Route path="/smtp" element={<Smtp />} />
      <Route path="/campaigns" element={<Campaigns />} />
      <Route path="/master" element={<Master />} />
      <Route path="/monitor/email-sent" element={<EmailSent />} />
      <Route path="/monitor/received-response" element={<ReceivedResponse />} />
      <Route path="/workers" element={<Workers />} />
    </Routes>
  </BrowserRouter>
);
