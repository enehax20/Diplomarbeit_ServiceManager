import { useState } from "react";
import { Routes, Route, Navigate, NavLink, Link, useLocation } from "react-router-dom";
import HomePage from "./pages/HomePage.jsx";
import KundenPage from "./pages/KundenPage.jsx";
import AuftraegePage from "./pages/AuftraegePage.jsx";
import MitarbeiterPage from "./pages/MitarbeiterPage.jsx";
import LoginPage from "./pages/LoginPage.jsx";
import Tour from "./tour/Tour.jsx";
import { tourSteps } from "./tour/tourSteps.js";
import { useAuth } from "./auth.jsx";

// Grundgerüst der App. Zeigt je nach Anmelde-Zustand entweder die
// Anmeldeseite oder den geschützten Bereich (Kopfzeile + Routen).
export default function App() {
  const { user, loading, logout, isAdmin } = useAuth();

  // Geführter Rundgang: an/aus. Die Schritte richten sich nach der aktuellen Seite.
  const [tourActive, setTourActive] = useState(false);
  const { pathname } = useLocation();
  const steps = tourSteps[pathname] ?? [];

  // Solange die erste Session-Prüfung (GET /me) läuft: kurzer Hinweis.
  if (loading) {
    return <p className="app-loading">Wird geladen …</p>;
  }

  // Nicht angemeldet -> nur die Anmeldeseite.
  if (!user) {
    return <LoginPage />;
  }

  // Angemeldet -> volle App.
  return (
    <div className="app">
      <header className="app-header">
        {/* Logo führt zurück zur Startseite (auch von jeder anderen Seite aus). */}
        <h1>
          <Link to="/" className="logo-link">ServiceManager</Link>
        </h1>
        <nav data-tour="nav">
          <NavLink to="/" end>Start</NavLink>
          <NavLink to="/kunden">Kund:innen</NavLink>
          <NavLink to="/auftraege">Aufträge</NavLink>
          {/* Mitarbeiter-Verwaltung ist nur für Admins sichtbar. */}
          {isAdmin && <NavLink to="/mitarbeiter">Mitarbeiter:innen</NavLink>}
        </nav>

        {/* Angemeldete Person + Rolle anzeigen (zwei Rollen: admin/mitarbeiter). */}
        <div className="user-box">
          <span className="user-name">
            {user.vorname} {user.nachname}
            <span className={`role-badge role-${user.rolle}`}>{user.rolle}</span>
          </span>
          {/* Hilfe: startet den Rundgang für die aktuelle Seite (nur wenn es
              dafür Schritte gibt). */}
          {steps.length > 0 && (
            <button
              type="button"
              className="btn-help"
              data-tour="help"
              aria-label="Hilfe / Rundgang starten"
              title="Rundgang starten"
              onClick={() => setTourActive(true)}
            >
              ?
            </button>
          )}
          <button type="button" className="btn-logout" onClick={logout}>
            Abmelden
          </button>
        </div>
      </header>

      <main className="app-main">
        <Routes>
          {/* Startseite (Cockpit) mit den Kennzahlen. */}
          <Route path="/" element={<HomePage />} />
          <Route path="/kunden" element={<KundenPage />} />
          <Route path="/auftraege" element={<AuftraegePage />} />
          {/* Mitarbeiter-Seite nur für Admins; sonst zurück zu Kund:innen. */}
          <Route
            path="/mitarbeiter"
            element={isAdmin ? <MitarbeiterPage /> : <Navigate to="/kunden" replace />}
          />
        </Routes>
      </main>

      {/* Geführter Rundgang (liegt als Overlay über allem). Nur eingehängt,
          während er läuft -> jeder Start beginnt automatisch bei Schritt 1. */}
      {tourActive && steps.length > 0 && (
        <Tour steps={steps} onClose={() => setTourActive(false)} />
      )}
    </div>
  );
}
