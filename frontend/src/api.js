// Zentrale Stelle für alle Backend-Aufrufe (plain fetch).
// Die Basis-URL kommt aus der Umgebungsvariable, mit sinnvollem Standard.
const API_BASE = import.meta.env.VITE_API_BASE_URL || "http://localhost:8000";

// Kleiner Helfer: führt fetch aus, prüft den Status und gibt JSON zurück.
async function request(path, options = {}) {
  const response = await fetch(`${API_BASE}${path}`, {
    headers: { "Content-Type": "application/json" },
    // credentials: "include" -> der Browser sendet das Session-Cookie mit,
    // damit das Backend die angemeldete Person erkennt (Login).
    credentials: "include",
    ...options,
  });

  // Antwort als Text lesen, dann zu JSON parsen (leerer Body -> null).
  const text = await response.text();
  const data = text ? JSON.parse(text) : null;

  // Bei Fehlerstatus (4xx/5xx) eine Exception mit der Backend-Meldung werfen.
  if (!response.ok) {
    const error = new Error(data?.error || `Fehler ${response.status}`);
    error.status = response.status; // z. B. 401 = nicht angemeldet
    error.fields = data?.fields || null; // Feld-Validierungsfehler (falls vorhanden)
    throw error;
  }

  return data;
}

// Anmeldung (Login).
export const authApi = {
  // POST /login -> meldet an, gibt die angemeldete Person (inkl. Rolle) zurück
  login: (benutzername, passwort) =>
    request("/login", {
      method: "POST",
      body: JSON.stringify({ benutzername, passwort }),
    }),
  // POST /logout -> beendet die Anmeldung
  logout: () => request("/logout", { method: "POST" }),
  // GET /me -> aktuell angemeldete Person (oder Fehler 401)
  me: () => request("/me"),
};

// Konkrete Endpunkte für Kund:innen.
export const kundenApi = {
  // GET /kunden?page=&perPage=&q= -> EINE Seite Kund:innen.
  // Antwort: { data, total, page, perPage, totalPages }
  list: ({ page = 1, perPage = 20, q = "" } = {}) => {
    const params = new URLSearchParams({ page, perPage });
    if (q) params.set("q", q);
    return request(`/kunden?${params.toString()}`);
  },
  // GET /kunden/auswahl -> schlanke Liste (ID + Name) für Auswahlfelder
  auswahl: () => request("/kunden/auswahl"),
  // POST /kunden -> neue Kund:in anlegen, gibt den angelegten Datensatz zurück
  create: (kunde) =>
    request("/kunden", { method: "POST", body: JSON.stringify(kunde) }),
  // PUT /kunden/{id} -> bestehende Kund:in bearbeiten, gibt den Datensatz zurück
  update: (id, kunde) =>
    request(`/kunden/${id}`, { method: "PUT", body: JSON.stringify(kunde) }),
  // DELETE /kunden/{id} -> löscht eine Kund:in
  remove: (id) => request(`/kunden/${id}`, { method: "DELETE" }),
};

// Mitarbeiter:innen (nur für Admins erreichbar).
export const mitarbeiterApi = {
  // GET /mitarbeiter -> Liste aller Mitarbeiter:innen (ohne Passwort)
  list: () => request("/mitarbeiter"),
  // POST /mitarbeiter -> neue:n Mitarbeiter:in anlegen (Admin tippt das Passwort)
  create: (mitarbeiter) =>
    request("/mitarbeiter", { method: "POST", body: JSON.stringify(mitarbeiter) }),
  // PUT /mitarbeiter/{id} -> bearbeiten (Passwort optional: leer = unverändert)
  update: (id, mitarbeiter) =>
    request(`/mitarbeiter/${id}`, { method: "PUT", body: JSON.stringify(mitarbeiter) }),
  // DELETE /mitarbeiter/{id} -> löscht eine:n Mitarbeiter:in
  remove: (id) => request(`/mitarbeiter/${id}`, { method: "DELETE" }),
  // GET /mitarbeiter/auswahl -> aktive Mitarbeiter:innen (ID + Name), auch für
  // Nicht-Admins erreichbar (Zuweisung am Auftrag).
  auswahl: () => request("/mitarbeiter/auswahl"),
};

// Endpunkte für Aufträge.
export const auftraegeApi = {
  // GET /auftraege?page=&perPage=&q= -> EINE Seite Aufträge (zuletzt aktualisierte zuerst).
  // Antwort: { data, total, page, perPage, totalPages }
  list: ({ page = 1, perPage = 10, q = "" } = {}) => {
    const params = new URLSearchParams({ page, perPage });
    if (q) params.set("q", q);
    return request(`/auftraege?${params.toString()}`);
  },
  // GET /auftraege/{id} -> ein Auftrag inkl. Statusverlauf (historie)
  get: (id) => request(`/auftraege/${id}`),
  // POST /auftraege -> neuen Auftrag anlegen
  create: (auftrag) =>
    request("/auftraege", { method: "POST", body: JSON.stringify(auftrag) }),
  // PUT /auftraege/{id} -> Stammfelder bearbeiten (nicht den Status)
  update: (id, auftrag) =>
    request(`/auftraege/${id}`, { method: "PUT", body: JSON.stringify(auftrag) }),
  // PUT /auftraege/{id}/status -> Status ändern (+ Verlaufszeile)
  setStatus: (id, status, bemerkung = "") =>
    request(`/auftraege/${id}/status`, {
      method: "PUT",
      body: JSON.stringify({ status, bemerkung }),
    }),
  // DELETE /auftraege/{id} -> Auftrag löschen
  remove: (id) => request(`/auftraege/${id}`, { method: "DELETE" }),
};

// Kennzahlen für die Startseite (Cockpit).
export const statistikApi = {
  // GET /statistik -> { kundenGesamt, auftraegeGesamt, auftraegeOffen, topKunden }
  get: () => request("/statistik"),
};

// Formatiert ein Datum (DATE "2026-06-24" oder DATETIME "2026-06-24 15:58:44")
// als "TT/MM/JJJJ". Wir zerlegen nur den Datumsteil als Text – so gibt es keine
// Zeitzonen-Verschiebung (new Date() würde den Wert als UTC interpretieren).
export function formatDatum(wert) {
  if (!wert) return "—";
  const [jahr, monat, tag] = String(wert).slice(0, 10).split("-");
  if (!jahr || !monat || !tag) return "—";
  return `${tag}/${monat}/${jahr}`;
}

// Die fünf Status-Werte mit deutscher Beschriftung (Reihenfolge = Arbeitsfluss).
// Werte müssen exakt dem ENUM in der Datenbank entsprechen.
export const AUFTRAG_STATUS = [
  { wert: "ANGENOMMEN", label: "Angenommen" },
  { wert: "IN_DIAGNOSE", label: "In Diagnose" },
  { wert: "IN_REPARATUR", label: "In Reparatur" },
  { wert: "FERTIG", label: "Fertig" },
  { wert: "ABGEHOLT", label: "Abgeholt" },
];

// Hilfsfunktion: deutsche Beschriftung zu einem Status-Wert.
export function statusLabel(wert) {
  return AUFTRAG_STATUS.find((s) => s.wert === wert)?.label ?? wert;
}
