import React, { useEffect, useState, useRef } from "react";

import { API_CONFIG } from "../config";
import { authFetch } from "../utils/authFetch";

const TopProgressBar = () => {
  const [percent, setPercent] = useState(0);
  const [active, setActive] = useState(false);
  const isPolling = useRef(false); // Prevent request pile-up
  const abortController = useRef(null); // For timeout control

  useEffect(() => {
    let interval = null;
    let isMounted = true;

    const fetchProgress = async () => {
      // CRITICAL: Prevent overlapping requests
      if (isPolling.current) {
        return;
      }

      isPolling.current = true;

      try {
        // Create abort controller with 3-second timeout
        abortController.current = new AbortController();
        const timeoutId = setTimeout(() => abortController.current.abort(), 3000);

        const res = await authFetch(API_CONFIG.PROGRESS, {
          signal: abortController.current.signal
        });

        clearTimeout(timeoutId);

        if (!res.ok) throw new Error('Request failed');
        
        const data = await res.json();

        // Only update if component is still mounted
        if (!isMounted) return;

        // Check for validation progress
        if (
          data &&
          typeof data.percent === "number" &&
          data.total > 0 &&
          data.percent < 100
        ) {
          setPercent(data.percent);
          setActive(true);
        } else {
          setActive(false);
          setPercent(0);
        }
      } catch (error) {
        if (error.name === 'AbortError') {
          console.warn('Progress request timed out');
        }
        if (isMounted) {
          setActive(false);
          setPercent(0);
        }
      } finally {
        if (isMounted) {
          isPolling.current = false;
        }
      }
    };

    // Initial fetch
    fetchProgress();

    // Poll every 2.5 seconds (giving 500ms buffer)
    interval = setInterval(fetchProgress, 2500);

    return () => {
      isMounted = false;
      clearInterval(interval);
      if (abortController.current) {
        abortController.current.abort();
      }
    };
  }, []);

  if (!active) return null;

  return (
    <div className="fixed top-0 left-0 w-full z-50">
      <div className="relative w-full h-1.5 bg-gray-300 overflow-hidden shadow">
        {/* Progress bar fills the window */}
        <div
          className="absolute top-0 left-0 h-full bg-gradient-to-r from-cyan-400 to-blue-600 transition-all duration-700 ease-in-out flex items-center"
          style={{ width: `${percent}%` }}
        >
          {/* % label inside the bar, moving with the bar */}
          <span
            className="absolute right-0 mr-2 text-[8.5px] font-bold text-white bg-opacity-90 px-1 py-0.5 rounded-full shadow"
            style={{
              transform: "translateY(-50%)",
              top: "50%",
              whiteSpace: "nowrap"
            }}
          >
            {percent.toFixed(1)}%
          </span>
        </div>
      </div>
    </div>
  );
};

export default TopProgressBar;
