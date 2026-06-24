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
  const [editId, setEditId] = useState(null); // null = anlegen, sonst bearbeiten
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

  // Eine Zeile zum Bearbeiten ins Formular laden.
  function startEdit(kunde) {
    setEditId(kunde.kunde_id);
    setForm({
      vorname: kunde.vorname ?? "",
      nachname: kunde.nachname ?? "",
      telefon: kunde.telefon ?? "",
      email: kunde.email ?? "",
      strasse: kunde.strasse ?? "",
      plz: kunde.plz ?? "",
      ort: kunde.ort ?? "",
    });
    setError(null);
    // Nach oben zum Formular scrollen (das Formular steht über der Liste).
    window.scrollTo({ top: 0, behavior: "smooth" });
  }

  // Bearbeitung abbrechen -> zurück zum Anlegen-Modus.
  function cancelEdit() {
    setEditId(null);
    setForm(EMPTY_FORM);
    setError(null);
  }

  // Formular absenden -> je nach Modus anlegen (POST) oder bearbeiten (PUT).
  async function handleSubmit(event) {
    event.preventDefault();
    setSaving(true);
    setError(null);
    try {
      if (editId === null) {
        // Anlegen
        const neu = await kundenApi.create(form);
        setKunden((prev) => [...prev, neu]);
      } else {
        // Bearbeiten: den geänderten Datensatz in der Liste ersetzen.
        const geaendert = await kundenApi.update(editId, form);
        setKunden((prev) =>
          prev.map((k) => (k.kunde_id === editId ? geaendert : k))
        );
      }
      cancelEdit(); // Formular zurücksetzen und Modus auf "anlegen"
    } catch (e) {
      // Feld-spezifische Meldungen bevorzugt anzeigen (z. B. doppelte E-Mail/Telefon).
      const fieldMessages = e.fields ? Object.values(e.fields).join(" ") : null;
      setError(fieldMessages || e.message);
    } finally {
      setSaving(false);
    }
  }

  // Eine Kund:in löschen (nach Rückfrage). Bei verknüpften Daten blockiert das
  // Backend mit einer verständlichen Meldung (HTTP 409).
  async function handleDelete(kunde) {
    const name = `${kunde.vorname} ${kunde.nachname}`.trim();
    if (!window.confirm(`Kund:in „${name}" wirklich löschen?`)) return;
    setError(null);
    try {
      await kundenApi.remove(kunde.kunde_id);
      setKunden((prev) => prev.filter((k) => k.kunde_id !== kunde.kunde_id));
      // Falls gerade diese Kund:in bearbeitet wurde: Formular zurücksetzen.
      if (editId === kunde.kunde_id) cancelEdit();
    } catch (e) {
      setError(e.message);
    }
  }

  const editing = editId !== null;

  return (
    <div className="kunden-page">
      <section className="card">
        <h2>{editing ? "Kund:in bearbeiten" : "Neue:r Kund:in"}</h2>
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
                : "Kund:in anlegen"}
            </button>
          </div>
        </form>
      </section>

      <section className="card">
        <h2>Kund:innen ({kunden.length})</h2>

        {!editing && error && <p className="error">{error}</p>}

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
                <th></th>
              </tr>
            </thead>
            <tbody>
              {kunden.map((k) => (
                <tr key={k.kunde_id} className={k.kunde_id === editId ? "row-editing" : ""}>
                  <td>
                    {k.nachname}, {k.vorname}
                  </td>
                  <td>{k.telefon || "—"}</td>
                  <td>{k.email || "—"}</td>
                  <td>{k.ort || "—"}</td>
                  <td className="row-actions">
                    <button
                      type="button"
                      className="btn-edit"
                      onClick={() => startEdit(k)}
                    >
                      Bearbeiten
                    </button>
                    <button
                      type="button"
                      className="btn-delete"
                      onClick={() => handleDelete(k)}
                    >
                      Löschen
                    </button>
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
