import { Routes, Route, Navigate, NavLink } from "react-router-dom";
import KundenPage from "./pages/KundenPage.jsx";
import MitarbeiterPage from "./pages/MitarbeiterPage.jsx";
import LoginPage from "./pages/LoginPage.jsx";
import { useAuth } from "./auth.jsx";

// Grundgerüst der App. Zeigt je nach Anmelde-Zustand entweder die
// Anmeldeseite oder den geschützten Bereich (Kopfzeile + Routen).
export default function App() {
  const { user, loading, logout, isAdmin } = useAuth();

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
        <h1>ServiceManager</h1>
        <nav>
          <NavLink to="/kunden">Kund:innen</NavLink>
          {/* Mitarbeiter-Verwaltung ist nur für Admins sichtbar. */}
          {isAdmin && <NavLink to="/mitarbeiter">Mitarbeiter:innen</NavLink>}
        </nav>

        {/* Angemeldete Person + Rolle anzeigen (zwei Rollen: admin/mitarbeiter). */}
        <div className="user-box">
          <span className="user-name">
            {user.vorname} {user.nachname}
            <span className={`role-badge role-${user.rolle}`}>{user.rolle}</span>
          </span>
          <button type="button" className="btn-logout" onClick={logout}>
            Abmelden
          </button>
        </div>
      </header>

      <main className="app-main">
        <Routes>
          {/* Startseite leitet auf die Kundenliste weiter. */}
          <Route path="/" element={<Navigate to="/kunden" replace />} />
          <Route path="/kunden" element={<KundenPage />} />
          {/* Mitarbeiter-Seite nur für Admins; sonst zurück zu Kund:innen. */}
          <Route
            path="/mitarbeiter"
            element={isAdmin ? <MitarbeiterPage /> : <Navigate to="/kunden" replace />}
          />
        </Routes>
      </main>
    </div>
  );
}
