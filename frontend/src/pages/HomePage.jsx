import { useEffect, useState } from "react";
import { Link } from "react-router-dom";
import { statistikApi } from "../api.js";
import { useAuth } from "../auth.jsx";

// Startseite (Cockpit): kurzer Überblick nach dem Anmelden. Zeigt ein paar
// Kennzahlen und die Kund:innen mit den meisten Aufträgen. Die Daten kommen
// gebündelt vom Endpunkt GET /statistik.
export default function HomePage() {
  const { user } = useAuth();
  const [stats, setStats] = useState(null);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState(null);

  // Kennzahlen einmalig beim Öffnen der Seite laden.
  useEffect(() => {
    statistikApi
      .get()
      .then(setStats)
      .catch((e) => setError(e.message))
      .finally(() => setLoading(false));
  }, []);

  return (
    <div className="home-page">
      <section className="card">
        <h2>Willkommen, {user.vorname}!</h2>
        <p className="muted">Kurzüberblick über den aktuellen Stand.</p>
      </section>

      {error && <p className="error">{error}</p>}

      {loading ? (
        <p>Wird geladen …</p>
      ) : (
        stats && (
          <>
            {/* Kennzahlen als anklickbare Karten (führen zur passenden Liste).
                Die "meine"-Karten öffnen die Auftragsliste mit dem eigenen Filter
                (?mein=1), die Gesamt-Karten mit allen Aufträgen (parameterloses
                /auftraege). */}
            <section className="stat-grid" data-tour="home-stats">
              <Link to="/auftraege?mein=1" className="stat-card" data-tour="home-meine">
                <span className="stat-zahl">{stats.meineOffen}</span>
                <span className="stat-label">Meine Aufträge in Arbeit</span>
              </Link>
              <Link to="/auftraege?mein=1" className="stat-card">
                <span className="stat-zahl">{stats.meineGesamt}</span>
                <span className="stat-label">Meine Aufträge gesamt</span>
              </Link>
              <Link to="/auftraege" className="stat-card">
                <span className="stat-zahl">{stats.auftraegeOffen}</span>
                <span className="stat-label">Aufträge in Arbeit (alle)</span>
              </Link>
              <Link to="/auftraege" className="stat-card">
                <span className="stat-zahl">{stats.auftraegeGesamt}</span>
                <span className="stat-label">Aufträge gesamt</span>
              </Link>
              <Link to="/kunden" className="stat-card">
                <span className="stat-zahl">{stats.kundenGesamt}</span>
                <span className="stat-label">Kund:innen</span>
              </Link>
            </section>

            <section className="card" data-tour="home-top">
              <h2>Kund:innen mit den meisten Aufträgen</h2>
              {stats.topKunden.length === 0 ? (
                <p className="muted">Noch keine Aufträge vorhanden.</p>
              ) : (
                <table className="table">
                  <thead>
                    <tr>
                      <th>Kund:in</th>
                      <th>Aufträge</th>
                    </tr>
                  </thead>
                  <tbody>
                    {stats.topKunden.map((k) => (
                      <tr key={k.kunde_id}>
                        <td>
                          {k.nachname}, {k.vorname}
                        </td>
                        <td>{k.anzahl}</td>
                      </tr>
                    ))}
                  </tbody>
                </table>
              )}
            </section>
          </>
        )
      )}
    </div>
  );
}
