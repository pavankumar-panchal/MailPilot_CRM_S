import React, { useEffect, useState } from "react";

const TopProgressBar = () => {
  const [percent, setPercent] = useState(0);
  const [active, setActive] = useState(false);

  useEffect(() => {
    let interval = null;

    const fetchProgress = async () => {
      try {
        const res = await fetch(
          "http://localhost/Verify_email/backend/includes/progress.php"
        );
        const data = await res.json();
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
      } catch {
        setActive(false);
        setPercent(0);
      }
    };

    fetchProgress();
    interval = setInterval(fetchProgress, 2000);

    return () => clearInterval(interval);
  }, []);

  if (!active) return null;

  return (
    <div className="fixed top-0 left-0 w-full z-50">
      <div
        className="h-1 bg-blue-500 transition-all duration-500"
        style={{ width: `${percent}%` }}
      ></div>
    </div>
  );
};

export default TopProgressBar;
