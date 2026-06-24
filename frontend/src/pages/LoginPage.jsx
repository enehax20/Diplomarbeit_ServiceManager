import { useState } from "react";
import { useAuth } from "../auth.jsx";

// Anmeldeseite: Benutzername + Passwort. Bei Erfolg setzt der AuthProvider
// die angemeldete Person; App.jsx zeigt dann automatisch die App an.
export default function LoginPage() {
  const { login } = useAuth();
  const [benutzername, setBenutzername] = useState("");
  const [passwort, setPasswort] = useState("");
  const [error, setError] = useState(null);
  const [busy, setBusy] = useState(false);

  async function handleSubmit(event) {
    event.preventDefault();
    setError(null);
    setBusy(true);
    try {
      await login(benutzername, passwort);
      // Kein manuelles Weiterleiten nötig: sobald "user" gesetzt ist,
      // rendert App.jsx den geschützten Bereich.
    } catch (e) {
      setError(e.message);
    } finally {
      setBusy(false);
    }
  }

  return (
    <div className="login-wrap">
      <section className="card login-card">
        <h2>Anmelden</h2>
        <form onSubmit={handleSubmit} className="login-form">
          <label>
            Benutzername
            <input
              name="benutzername"
              value={benutzername}
              onChange={(e) => setBenutzername(e.target.value)}
              autoFocus
              required
            />
          </label>
          <label>
            Passwort
            <input
              name="passwort"
              type="password"
              value={passwort}
              onChange={(e) => setPasswort(e.target.value)}
              required
            />
          </label>

          {error && <p className="error">{error}</p>}

          <button type="submit" disabled={busy}>
            {busy ? "Anmelden …" : "Anmelden"}
          </button>
        </form>
      </section>
    </div>
  );
}
