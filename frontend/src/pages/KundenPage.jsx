import { useEffect, useState } from "react";
import { useSearchParams } from "react-router-dom";
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

const PER_PAGE = 20; // Kund:innen pro Seite

export default function KundenPage() {
  const [kunden, setKunden] = useState([]); // Kund:innen der AKTUELLEN Seite
  const [form, setForm] = useState(EMPTY_FORM); // aktuelle Formularwerte
  const [editId, setEditId] = useState(null); // null = anlegen, sonst bearbeiten
  const [loading, setLoading] = useState(true); // Liste wird geladen
  const [saving, setSaving] = useState(false); // Formular wird gesendet
  const [error, setError] = useState(null); // Fehlermeldung (Laden/Speichern)

  // Gesamtzahl + Seitenanzahl kommen aus der Backend-Antwort.
  const [total, setTotal] = useState(0); // Gesamtzahl der Treffer
  const [totalPages, setTotalPages] = useState(1); // Anzahl Seiten

  // Aktuelle Seite und Suchbegriff stehen in der URL (?page=2&q=…), damit die
  // Adresse den Listenzustand widerspiegelt: teilbar, als Lesezeichen speicherbar
  // und über die Zurück-Taste erreichbar. (GET-Prinzip: alles steht in der URL.)
  const [searchParams, setSearchParams] = useSearchParams();
  const page = Math.max(1, Number(searchParams.get("page")) || 1);
  const query = searchParams.get("q") ?? "";

  // Schreibt Seite/Suche in die URL. Standardwerte (Seite 1, leere Suche) lassen
  // wir weg, damit die URL sauber bleibt. replace=true beim Tippen, damit die
  // Zurück-Taste nicht bei jedem Buchstaben einen Eintrag anlegt.
  function updateParams(next, { replace = false } = {}) {
    const p = next.page ?? page;
    const q = next.q ?? query;
    const params = {};
    if (p > 1) params.page = String(p);
    if (q) params.q = q;
    setSearchParams(params, { replace });
  }

  // Liste laden, sobald sich Seite oder Suchbegriff ändert.
  // Kleiner Timer (Debounce): beim Tippen nicht bei jedem Tastendruck laden.
  useEffect(() => {
    const timer = setTimeout(() => {
      loadKunden();
    }, 300);
    return () => clearTimeout(timer);
    // loadKunden nutzt page/query aus dem Closure -> bewusst als Abhängigkeit.
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [page, query]);

  async function loadKunden() {
    setLoading(true);
    setError(null);
    try {
      const res = await kundenApi.list({ page, perPage: PER_PAGE, q: query });
      setKunden(res.data);
      setTotal(res.total);
      setTotalPages(res.totalPages);
      // Falls das Backend die Seite begrenzt hat (z. B. nach dem Löschen des
      // letzten Eintrags einer Seite), die URL angleichen.
      if (res.page !== page) updateParams({ page: res.page }, { replace: true });
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

  // Suchfeld: bei jeder Änderung zurück auf Seite 1 und den Begriff in die URL.
  function handleSearch(event) {
    updateParams({ page: 1, q: event.target.value }, { replace: true });
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
        await kundenApi.create(form);
      } else {
        await kundenApi.update(editId, form);
      }
      cancelEdit(); // Formular zurücksetzen und Modus auf "anlegen"
      // Bei serverseitiger Pagination einfach die aktuelle Seite neu laden,
      // damit Sortierung, Seitenanzahl und Gesamtzahl korrekt bleiben.
      await loadKunden();
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
      if (editId === kunde.kunde_id) cancelEdit();
      await loadKunden(); // aktuelle Seite neu laden
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
        <div className="list-head">
          <h2>Kund:innen ({total})</h2>
          <input
            className="search-input"
            type="search"
            placeholder="Suchen (Name oder E-Mail) …"
            value={query}
            onChange={handleSearch}
          />
        </div>

        {!editing && error && <p className="error">{error}</p>}

        {loading ? (
          <p>Wird geladen …</p>
        ) : kunden.length === 0 ? (
          <p className="muted">
            {query ? "Keine Treffer für die Suche." : "Noch keine Kund:innen vorhanden."}
          </p>
        ) : (
          <>
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

            {/* Seitennavigation */}
            <div className="pagination">
              <button
                type="button"
                className="btn-secondary"
                onClick={() => updateParams({ page: Math.max(1, page - 1) })}
                disabled={page <= 1}
              >
                ← Zurück
              </button>
              <span className="muted">
                Seite {page} von {totalPages}
              </span>
              <button
                type="button"
                className="btn-secondary"
                onClick={() => updateParams({ page: Math.min(totalPages, page + 1) })}
                disabled={page >= totalPages}
              >
                Weiter →
              </button>
            </div>
          </>
        )}
      </section>
    </div>
  );
}
