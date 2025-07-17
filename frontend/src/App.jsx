import { Routes, Route, useLocation } from "react-router-dom";
import TopProgressBar from "./components/TopProgressBar";
import Home from "./Home";
import TableData from "./table_data";
import Navbar from "./Navbar";

const App = () => {
  const location = useLocation();
  const hideNavbarRoutes = ["/table-data/2"]; // Add all routes where navbar should be hidden
  const hideNavbar = hideNavbarRoutes.includes(location.pathname);

  return (
    <>
      {!hideNavbar && <Navbar />}
      <div className="text-3xl bg-gray-500 font-bold underline">working</div>
      <TopProgressBar />
      <Routes>
        <Route path="/" element={<Home />} />
        <Route path="/details" element={<TableData />} />
      </Routes>
    </>
  );
};

export default App;
