import { useEffect, useState } from "react";
import { kundenApi } from "../api.js";

// Leeres Formular als Ausgangszustand (ein Feld je Spalte der Tabelle "kunde").
const EMPTY_FORM = {
  vorname: "",
  nachname: "",
  telefon: "",
  email: "",
  strasse: "",
  plz: "",
  ort: "",
};

export default function KundenPage() {
  const [kunden, setKunden] = useState([]); // Liste der Kund:innen
  const [form, setForm] = useState(EMPTY_FORM); // aktuelle Formularwerte
  const [loading, setLoading] = useState(true); // Liste wird geladen
  const [saving, setSaving] = useState(false); // Formular wird gesendet
  const [error, setError] = useState(null); // Fehlermeldung (Laden/Speichern)

  // Beim ersten Anzeigen die Liste vom Backend holen.
  useEffect(() => {
    loadKunden();
  }, []);

  async function loadKunden() {
    setLoading(true);
    setError(null);
    try {
      const data = await kundenApi.list();
      setKunden(data);
    } catch (e) {
      setError(e.message);
    } finally {
      setLoading(false);
    }
  }

  // Ein Eingabefeld im Formular aktualisieren.
  function handleChange(event) {
    const { name, value } = event.target;
    setForm((prev) => ({ ...prev, [name]: value }));
  }

  // Formular absenden -> neue Kund:in anlegen.
  async function handleSubmit(event) {
    event.preventDefault();
    setSaving(true);
    setError(null);
    try {
      const neu = await kundenApi.create(form);
      // Den neuen Datensatz an die Liste anhängen (ohne komplett neu zu laden).
      setKunden((prev) => [...prev, neu]);
      setForm(EMPTY_FORM); // Formular zurücksetzen
    } catch (e) {
      setError(e.message);
    } finally {
      setSaving(false);
    }
  }

  return (
    <div className="kunden-page">
      <section className="card">
        <h2>Neue:r Kund:in</h2>
        <form onSubmit={handleSubmit} className="form-grid">
          <label>
            Vorname *
            <input name="vorname" value={form.vorname} onChange={handleChange} required />
          </label>
          <label>
            Nachname *
            <input name="nachname" value={form.nachname} onChange={handleChange} required />
          </label>
          <label>
            Telefon
            <input name="telefon" value={form.telefon} onChange={handleChange} />
          </label>
          <label>
            E-Mail
            <input name="email" type="email" value={form.email} onChange={handleChange} />
          </label>
          <label>
            Straße
            <input name="strasse" value={form.strasse} onChange={handleChange} />
          </label>
          <label>
            PLZ
            <input name="plz" value={form.plz} onChange={handleChange} />
          </label>
          <label>
            Ort
            <input name="ort" value={form.ort} onChange={handleChange} />
          </label>

          <div className="form-actions">
            <button type="submit" disabled={saving}>
              {saving ? "Speichern …" : "Kund:in anlegen"}
            </button>
          </div>
        </form>
      </section>

      <section className="card">
        <h2>Kund:innen ({kunden.length})</h2>

        {error && <p className="error">{error}</p>}

        {loading ? (
          <p>Wird geladen …</p>
        ) : kunden.length === 0 ? (
          <p className="muted">Noch keine Kund:innen vorhanden.</p>
        ) : (
          <table className="table">
            <thead>
              <tr>
                <th>Name</th>
                <th>Telefon</th>
                <th>E-Mail</th>
                <th>Ort</th>
              </tr>
            </thead>
            <tbody>
              {kunden.map((k) => (
                <tr key={k.kunde_id}>
                  <td>
                    {k.nachname}, {k.vorname}
                  </td>
                  <td>{k.telefon || "—"}</td>
                  <td>{k.email || "—"}</td>
                  <td>{k.ort || "—"}</td>
                </tr>
              ))}
            </tbody>
          </table>
        )}
      </section>
    </div>
  );
}
