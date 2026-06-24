import { useEffect, useState } from "react";
import { mitarbeiterApi } from "../api.js";
import { useAuth } from "../auth.jsx";

// Leeres Formular. Beim Anlegen tippt der Admin das Passwort selbst.
const EMPTY_FORM = {
  vorname: "",
  nachname: "",
  email: "",
  benutzername: "",
  rolle: "mitarbeiter",
  aktiv: true,
  passwort: "",
};

export default function MitarbeiterPage() {
  const { user } = useAuth(); // angemeldeter Admin (für Selbst-Erkennung)
  const [liste, setListe] = useState([]); // alle Mitarbeiter:innen
  const [form, setForm] = useState(EMPTY_FORM); // Formularwerte
  const [editId, setEditId] = useState(null); // null = anlegen, sonst bearbeiten
  const [loading, setLoading] = useState(true);
  const [saving, setSaving] = useState(false);
  const [error, setError] = useState(null);

  useEffect(() => {
    loadListe();
  }, []);

  async function loadListe() {
    setLoading(true);
    setError(null);
    try {
      setListe(await mitarbeiterApi.list());
    } catch (e) {
      setError(e.message);
    } finally {
      setLoading(false);
    }
  }

  // Ein Feld aktualisieren (Checkbox "aktiv" gesondert behandeln).
  function handleChange(event) {
    const { name, value, type, checked } = event.target;
    setForm((prev) => ({ ...prev, [name]: type === "checkbox" ? checked : value }));
  }

  function startEdit(m) {
    setEditId(m.mitarbeiter_id);
    setForm({
      vorname: m.vorname ?? "",
      nachname: m.nachname ?? "",
      email: m.email ?? "",
      benutzername: m.benutzername ?? "",
      rolle: m.rolle ?? "mitarbeiter",
      aktiv: Number(m.aktiv) === 1,
      passwort: "", // leer lassen = Passwort unverändert
    });
    setError(null);
    window.scrollTo({ top: 0, behavior: "smooth" });
  }

  function cancelEdit() {
    setEditId(null);
    setForm(EMPTY_FORM);
    setError(null);
  }

  async function handleSubmit(event) {
    event.preventDefault();
    setSaving(true);
    setError(null);
    try {
      if (editId === null) {
        const neu = await mitarbeiterApi.create(form);
        setListe((prev) => [...prev, neu]);
      } else {
        const geaendert = await mitarbeiterApi.update(editId, form);
        setListe((prev) =>
          prev.map((m) => (m.mitarbeiter_id === editId ? geaendert : m))
        );
      }
      cancelEdit();
    } catch (e) {
      const fieldMessages = e.fields ? Object.values(e.fields).join(" ") : null;
      setError(fieldMessages || e.message);
    } finally {
      setSaving(false);
    }
  }

  // Eine:n Mitarbeiter:in löschen (nach Rückfrage). Das Backend blockiert das
  // Löschen, wenn noch Aufträge zugewiesen sind (HTTP 409) – dann lieber deaktivieren.
  async function handleDelete(m) {
    const name = `${m.vorname} ${m.nachname}`.trim();
    if (!window.confirm(`Mitarbeiter:in „${name}" wirklich löschen?`)) return;
    setError(null);
    try {
      await mitarbeiterApi.remove(m.mitarbeiter_id);
      setListe((prev) => prev.filter((x) => x.mitarbeiter_id !== m.mitarbeiter_id));
      if (editId === m.mitarbeiter_id) cancelEdit();
    } catch (e) {
      setError(e.message);
    }
  }

  const editing = editId !== null;

  return (
    <div className="mitarbeiter-page">
      <section className="card">
        <h2>{editing ? "Mitarbeiter:in bearbeiten" : "Neue:r Mitarbeiter:in"}</h2>
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
            Benutzername *
            <input
              name="benutzername"
              value={form.benutzername}
              onChange={handleChange}
              required
            />
          </label>
          <label>
            E-Mail
            <input name="email" type="email" value={form.email} onChange={handleChange} />
          </label>
          <label>
            Rolle *
            <select name="rolle" value={form.rolle} onChange={handleChange}>
              <option value="mitarbeiter">mitarbeiter</option>
              <option value="admin">admin</option>
            </select>
          </label>
          <label>
            {editing ? "Neues Passwort (optional)" : "Passwort *"}
            <input
              name="passwort"
              type="password"
              value={form.passwort}
              onChange={handleChange}
              placeholder={editing ? "leer lassen = unverändert" : ""}
              required={!editing}
              minLength={8}
            />
          </label>

          {/* Hinweis: "aktiv" wird bewusst NICHT im Formular angeboten – neue
              Mitarbeiter:innen sind automatisch aktiv und können sich selbst
              anmelden. Das Feld bleibt in der DB (Standard: aktiv) und im
              Formularzustand erhalten, damit ein späteres Deaktivieren möglich
              bliebe, ohne bestehende Werte zu überschreiben. */}

          {editing && error && <p className="error form-error">{error}</p>}

          <div className="form-actions">
            {editing && (
              <button type="button" className="btn-secondary" onClick={cancelEdit}>
                Abbrechen
              </button>
            )}
            <button type="submit" disabled={saving}>
              {saving
                ? "Speichern …"
                : editing
                ? "Änderungen speichern"
                : "Mitarbeiter:in anlegen"}
            </button>
          </div>
        </form>
      </section>

      <section className="card">
        <h2>Mitarbeiter:innen ({liste.length})</h2>

        {!editing && error && <p className="error">{error}</p>}

        {loading ? (
          <p>Wird geladen …</p>
        ) : liste.length === 0 ? (
          <p className="muted">Noch keine Mitarbeiter:innen vorhanden.</p>
        ) : (
          <table className="table">
            <thead>
              <tr>
                <th>Name</th>
                <th>Benutzername</th>
                <th>Rolle</th>
                <th>Status</th>
                <th></th>
              </tr>
            </thead>
            <tbody>
              {liste.map((m) => (
                <tr
                  key={m.mitarbeiter_id}
                  className={m.mitarbeiter_id === editId ? "row-editing" : ""}
                >
                  <td>
                    {m.nachname}, {m.vorname}
                  </td>
                  <td>{m.benutzername}</td>
                  <td>
                    <span className={`role-badge role-${m.rolle}`}>{m.rolle}</span>
                  </td>
                  <td>{Number(m.aktiv) === 1 ? "aktiv" : "inaktiv"}</td>
                  <td className="row-actions">
                    <button
                      type="button"
                      className="btn-edit"
                      onClick={() => startEdit(m)}
                    >
                      Bearbeiten
                    </button>
                    {/* Eigenes Konto kann nicht gelöscht werden -> Button ausblenden. */}
                    {user?.mitarbeiter_id !== m.mitarbeiter_id && (
                      <button
                        type="button"
                        className="btn-delete"
                        onClick={() => handleDelete(m)}
                      >
                        Löschen
                      </button>
                    )}
                  </td>
                </tr>
              ))}
            </tbody>
          </table>
        )}
      </section>
    </div>
  );
}
