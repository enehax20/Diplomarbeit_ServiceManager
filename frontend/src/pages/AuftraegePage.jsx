import { Fragment, useEffect, useState } from "react";
import {
  auftraegeApi,
  kundenApi,
  mitarbeiterApi,
  AUFTRAG_STATUS,
  statusLabel,
} from "../api.js";

// Leeres Formular. Die Felder entsprechen den Spalten der Tabelle "auftrag",
// die beim Anlegen/Bearbeiten sinnvoll sind (Status wird separat geändert).
const EMPTY_FORM = {
  kunde_id: "",
  mitarbeiter_id: "",
  servicegegenstand: "",
  hersteller: "",
  titel: "",
  problembeschreibung: "",
  diagnose: "",
  voraussichtlich_fertig: "",
};

// Zeigt nur den Datumsteil eines DATETIME-Strings (z. B. "2026-06-24 15:58:44").
function nurDatum(wert) {
  if (!wert) return "—";
  return String(wert).slice(0, 10);
}

// Vergleicht das GEPLANTE Fertigstellungsdatum (voraussichtlich_fertig, ein DATE)
// mit dem TATSÄCHLICHEN Abschlusszeitpunkt (abgeschlossen_am, ein DATETIME) und
// gibt einen verständlichen deutschen Text zurück – oder null, wenn ein Wert fehlt.
// Positiv = später als geplant, negativ = früher, 0 = genau am geplanten Tag.
function fertigstellungText(plan, abgeschlossen) {
  if (!plan || !abgeschlossen) return null;
  // Beide auf den reinen Tag reduzieren (ohne Uhrzeit), dann Tage berechnen.
  const planTag = new Date(String(plan).slice(0, 10) + "T00:00:00");
  const istTag = new Date(String(abgeschlossen).slice(0, 10) + "T00:00:00");
  const tage = Math.round((istTag - planTag) / 86400000); // ms pro Tag
  if (tage > 0) return `${tage} Tag(e) später als geplant`;
  if (tage < 0) return `${-tage} Tag(e) früher als geplant`;
  return "pünktlich (genau am geplanten Tag)";
}

const PER_PAGE = 10; // Aufträge pro Seite

export default function AuftraegePage() {
  const [liste, setListe] = useState([]); // Aufträge der AKTUELLEN Seite
  const [kundenOpts, setKundenOpts] = useState([]); // Auswahl: Kund:innen
  const [maOpts, setMaOpts] = useState([]); // Auswahl: Mitarbeiter:innen
  const [form, setForm] = useState(EMPTY_FORM);
  const [editId, setEditId] = useState(null); // null = anlegen, sonst bearbeiten
  const [loading, setLoading] = useState(true);
  const [saving, setSaving] = useState(false);
  const [error, setError] = useState(null);
  const [detail, setDetail] = useState(null); // aufgeklappter Auftrag (inkl. Verlauf)

  // Pagination + Suche (serverseitig: das Backend liefert nur eine Seite).
  const [page, setPage] = useState(1); // aktuelle Seite (1-basiert)
  const [total, setTotal] = useState(0); // Gesamtzahl der Treffer
  const [totalPages, setTotalPages] = useState(1); // Anzahl Seiten
  const [query, setQuery] = useState(""); // Suchbegriff (Gegenstand/Kund:in …)

  // Auswahllisten (Kund:innen, Mitarbeiter:innen) einmalig beim Start laden.
  useEffect(() => {
    Promise.all([kundenApi.auswahl(), mitarbeiterApi.auswahl()])
      .then(([kunden, mitarbeiter]) => {
        setKundenOpts(kunden);
        setMaOpts(mitarbeiter);
      })
      .catch((e) => setError(e.message));
  }, []);

  // Aufträge laden, sobald sich Seite oder Suchbegriff ändert (mit Debounce).
  useEffect(() => {
    const timer = setTimeout(() => {
      loadAuftraege();
    }, 300);
    return () => clearTimeout(timer);
    // loadAuftraege nutzt page/query aus dem Closure -> bewusst als Abhängigkeit.
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [page, query]);

  async function loadAuftraege() {
    setLoading(true);
    setError(null);
    try {
      const res = await auftraegeApi.list({ page, perPage: PER_PAGE, q: query });
      setListe(res.data);
      setTotal(res.total);
      setTotalPages(res.totalPages);
      // Falls das Backend die Seite begrenzt hat (z. B. nach dem Löschen des
      // letzten Eintrags einer Seite), unseren Zustand angleichen.
      if (res.page !== page) setPage(res.page);
    } catch (e) {
      setError(e.message);
    } finally {
      setLoading(false);
    }
  }

  // Suchfeld: bei jeder Änderung zurück auf Seite 1.
  function handleSearch(event) {
    setQuery(event.target.value);
    setPage(1);
  }

  function handleChange(event) {
    const { name, value } = event.target;
    setForm((prev) => ({ ...prev, [name]: value }));
  }

  function startEdit(a) {
    setEditId(a.auftrag_id);
    setForm({
      kunde_id: String(a.kunde_id ?? ""),
      mitarbeiter_id: a.mitarbeiter_id ? String(a.mitarbeiter_id) : "",
      servicegegenstand: a.servicegegenstand ?? "",
      hersteller: a.hersteller ?? "",
      titel: a.titel ?? "",
      problembeschreibung: a.problembeschreibung ?? "",
      diagnose: a.diagnose ?? "",
      voraussichtlich_fertig: a.voraussichtlich_fertig ?? "",
    });
    setError(null);
    window.scrollTo({ top: 0, behavior: "smooth" });
  }

  // Beim Bearbeiten brauchen wir die vollständigen Felder (Problembeschreibung,
  // Diagnose). Die Liste enthält diese nicht -> zuerst den Auftrag nachladen.
  async function handleEditClick(a) {
    setError(null);
    try {
      const voll = await auftraegeApi.get(a.auftrag_id);
      startEdit(voll);
    } catch (e) {
      setError(e.message);
    }
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
        await auftraegeApi.create(form);
      } else {
        await auftraegeApi.update(editId, form);
      }
      cancelEdit();
      await loadAuftraege();
    } catch (e) {
      const fieldMessages = e.fields ? Object.values(e.fields).join(" ") : null;
      setError(fieldMessages || e.message);
    } finally {
      setSaving(false);
    }
  }

  // Status direkt in der Liste ändern (eigener Endpunkt -> schreibt auch Verlauf).
  async function handleStatusChange(a, neuerStatus) {
    if (neuerStatus === a.status) return;
    setError(null);
    try {
      await auftraegeApi.setStatus(a.auftrag_id, neuerStatus);
      await loadAuftraege();
      // Falls der Verlauf dieses Auftrags offen ist: neu laden.
      if (detail && detail.auftrag_id === a.auftrag_id) {
        setDetail(await auftraegeApi.get(a.auftrag_id));
      }
    } catch (e) {
      setError(e.message);
    }
  }

  async function handleDelete(a) {
    if (!window.confirm(`Auftrag #${a.auftrag_id} wirklich löschen?`)) return;
    setError(null);
    try {
      await auftraegeApi.remove(a.auftrag_id);
      if (editId === a.auftrag_id) cancelEdit();
      if (detail && detail.auftrag_id === a.auftrag_id) setDetail(null);
      await loadAuftraege();
    } catch (e) {
      setError(e.message);
    }
  }

  // Verlauf eines Auftrags ein-/ausklappen.
  async function toggleDetail(a) {
    if (detail && detail.auftrag_id === a.auftrag_id) {
      setDetail(null);
      return;
    }
    setError(null);
    try {
      setDetail(await auftraegeApi.get(a.auftrag_id));
    } catch (e) {
      setError(e.message);
    }
  }

  const editing = editId !== null;

  return (
    <div className="auftraege-page">
      <section className="card">
        <h2>{editing ? `Auftrag #${editId} bearbeiten` : "Neuer Auftrag"}</h2>
        <form onSubmit={handleSubmit} className="form-grid">
          <label>
            Kund:in *
            <select name="kunde_id" value={form.kunde_id} onChange={handleChange} required>
              <option value="">– bitte wählen –</option>
              {kundenOpts.map((k) => (
                <option key={k.kunde_id} value={k.kunde_id}>
                  {k.nachname}, {k.vorname}
                </option>
              ))}
            </select>
          </label>
          <label>
            Bearbeiter:in
            <select name="mitarbeiter_id" value={form.mitarbeiter_id} onChange={handleChange}>
              <option value="">– nicht zugewiesen –</option>
              {maOpts.map((m) => (
                <option key={m.mitarbeiter_id} value={m.mitarbeiter_id}>
                  {m.nachname}, {m.vorname}
                </option>
              ))}
            </select>
          </label>
          <label>
            Gegenstand *
            <input
              name="servicegegenstand"
              value={form.servicegegenstand}
              onChange={handleChange}
              placeholder="z. B. VW Golf VII, iPhone 12, Bosch Waschmaschine"
              required
            />
          </label>
          <label>
            Hersteller
            <input name="hersteller" value={form.hersteller} onChange={handleChange} />
          </label>
          <label>
            Titel
            <input name="titel" value={form.titel} onChange={handleChange} />
          </label>
          <label>
            Voraussichtlich fertig
            <input
              name="voraussichtlich_fertig"
              type="date"
              value={form.voraussichtlich_fertig}
              onChange={handleChange}
            />
          </label>
          <label className="full">
            Problembeschreibung *
            <textarea
              name="problembeschreibung"
              value={form.problembeschreibung}
              onChange={handleChange}
              rows={2}
              required
            />
          </label>
          {editing && (
            <label className="full">
              Diagnose
              <textarea
                name="diagnose"
                value={form.diagnose}
                onChange={handleChange}
                rows={2}
              />
            </label>
          )}

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
                : "Auftrag anlegen"}
            </button>
          </div>
        </form>
      </section>

      <section className="card">
        <div className="list-head">
          <h2>Aufträge ({total})</h2>
          <input
            className="search-input"
            type="search"
            placeholder="Suchen (Gegenstand, Hersteller, Kund:in, Bearbeiter:in) …"
            value={query}
            onChange={handleSearch}
          />
        </div>

        {!editing && error && <p className="error">{error}</p>}

        {loading ? (
          <p>Wird geladen …</p>
        ) : liste.length === 0 ? (
          <p className="muted">
            {query ? "Keine Treffer für die Suche." : "Noch keine Aufträge vorhanden."}
          </p>
        ) : (
          <table className="table">
            <thead>
              <tr>
                <th>Nr.</th>
                <th>Kund:in</th>
                <th>Gegenstand</th>
                <th>Bearbeiter:in</th>
                <th>Angenommen</th>
                <th>Status</th>
                <th></th>
              </tr>
            </thead>
            <tbody>
              {liste.map((a) => (
                <Fragment key={a.auftrag_id}>
                  <tr className={a.auftrag_id === editId ? "row-editing" : ""}>
                    <td>#{a.auftrag_id}</td>
                    <td>
                      {a.kunde_nachname}, {a.kunde_vorname}
                    </td>
                    <td>
                      {a.servicegegenstand}
                      {a.hersteller ? ` (${a.hersteller})` : ""}
                    </td>
                    <td>
                      {a.mitarbeiter_nachname
                        ? `${a.mitarbeiter_nachname}, ${a.mitarbeiter_vorname}`
                        : "—"}
                    </td>
                    <td>{nurDatum(a.angenommen_am)}</td>
                    <td>
                      {/* Status-Auswahl: ändert direkt (inkl. Verlaufseintrag). */}
                      <select
                        className={`status-select status-${a.status}`}
                        value={a.status}
                        onChange={(e) => handleStatusChange(a, e.target.value)}
                      >
                        {AUFTRAG_STATUS.map((s) => (
                          <option key={s.wert} value={s.wert}>
                            {s.label}
                          </option>
                        ))}
                      </select>
                    </td>
                    <td className="row-actions">
                      <button
                        type="button"
                        className="btn-edit"
                        onClick={() => toggleDetail(a)}
                      >
                        Verlauf
                      </button>
                      <button
                        type="button"
                        className="btn-edit"
                        onClick={() => handleEditClick(a)}
                      >
                        Bearbeiten
                      </button>
                      <button
                        type="button"
                        className="btn-delete"
                        onClick={() => handleDelete(a)}
                      >
                        Löschen
                      </button>
                    </td>
                  </tr>

                  {/* Aufgeklappter Statusverlauf zu diesem Auftrag. */}
                  {detail && detail.auftrag_id === a.auftrag_id && (
                    <tr className="detail-row">
                      <td colSpan={7}>
                        <div className="detail-box">
                          {/* Stammdaten des Auftrags, die in der Tabelle keinen Platz haben. */}
                          <dl className="detail-fields">
                            <div>
                              <dt>Titel</dt>
                              <dd>{detail.titel || "—"}</dd>
                            </div>
                            <div>
                              <dt>Hersteller</dt>
                              <dd>{detail.hersteller || "—"}</dd>
                            </div>
                            <div>
                              <dt>Angenommen am</dt>
                              <dd>{nurDatum(detail.angenommen_am)}</dd>
                            </div>
                            <div>
                              <dt>Voraussichtlich fertig</dt>
                              <dd>{nurDatum(detail.voraussichtlich_fertig)}</dd>
                            </div>
                            {detail.abgeschlossen_am && (
                              <div>
                                <dt>Tatsächlich fertig</dt>
                                <dd>{nurDatum(detail.abgeschlossen_am)}</dd>
                              </div>
                            )}
                          </dl>

                          <p>
                            <strong>Problem:</strong> {detail.problembeschreibung}
                          </p>
                          <p>
                            <strong>Diagnose:</strong>{" "}
                            {detail.diagnose || <span className="muted">noch keine Diagnose</span>}
                          </p>

                          {/* Vergleich geplant vs. tatsächlich – nur wenn der Auftrag
                              abgeschlossen ist (FERTIG/ABGEHOLT) und ein Plandatum hat. */}
                          {fertigstellungText(detail.voraussichtlich_fertig, detail.abgeschlossen_am) && (
                            <p className="fertig-hinweis">
                              <strong>Fertigstellung:</strong>{" "}
                              {fertigstellungText(detail.voraussichtlich_fertig, detail.abgeschlossen_am)}
                            </p>
                          )}

                          <strong>Statusverlauf</strong>
                          <ul className="historie">
                            {detail.historie.map((h) => (
                              <li key={h.historie_id}>
                                <span className={`status-badge status-${h.status}`}>
                                  {statusLabel(h.status)}
                                </span>
                                <span className="muted"> {nurDatum(h.geaendert_am)}</span>
                                {h.mitarbeiter_nachname && (
                                  <span className="muted">
                                    {" "}
                                    – {h.mitarbeiter_nachname}, {h.mitarbeiter_vorname}
                                  </span>
                                )}
                                {h.bemerkung && <span> – {h.bemerkung}</span>}
                              </li>
                            ))}
                          </ul>
                        </div>
                      </td>
                    </tr>
                  )}
                </Fragment>
              ))}
            </tbody>
          </table>
        )}

        {/* Seitennavigation (nur sinnvoll, wenn es mehr als eine Seite gibt). */}
        {!loading && totalPages > 1 && (
          <div className="pagination">
            <button
              type="button"
              className="btn-secondary"
              onClick={() => setPage((p) => Math.max(1, p - 1))}
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
              onClick={() => setPage((p) => Math.min(totalPages, p + 1))}
              disabled={page >= totalPages}
            >
              Weiter →
            </button>
          </div>
        )}
      </section>
    </div>
  );
}
