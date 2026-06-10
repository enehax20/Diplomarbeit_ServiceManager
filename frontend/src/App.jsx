import { Routes, Route, Navigate, NavLink } from "react-router-dom";
import KundenPage from "./pages/KundenPage.jsx";

// Grundgerüst der App: Kopfzeile mit Navigation + Routen-Bereich.
export default function App() {
  return (
    <div className="app">
      <header className="app-header">
        <h1>ServiceManager</h1>
        <nav>
          <NavLink to="/kunden">Kund:innen</NavLink>
        </nav>
      </header>

      <main className="app-main">
        <Routes>
          {/* Startseite leitet auf die Kundenliste weiter. */}
          <Route path="/" element={<Navigate to="/kunden" replace />} />
          <Route path="/kunden" element={<KundenPage />} />
        </Routes>
      </main>
    </div>
  );
}
