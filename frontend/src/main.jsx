import React from "react";
import ReactDOM from "react-dom/client";
import { BrowserRouter } from "react-router-dom";
import App from "./App.jsx";
import { AuthProvider } from "./auth.jsx";
import "./index.css";

// Einstiegspunkt: React in das <div id="root"> aus index.html rendern.
// BrowserRouter aktiviert das clientseitige Routing (React Router).
// AuthProvider stellt den Anmelde-Zustand (Login) der ganzen App bereit.
ReactDOM.createRoot(document.getElementById("root")).render(
  <React.StrictMode>
    <BrowserRouter>
      <AuthProvider>
        <App />
      </AuthProvider>
    </BrowserRouter>
  </React.StrictMode>
);
