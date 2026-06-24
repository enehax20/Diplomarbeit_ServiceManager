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
  // GET /kunden -> Liste aller Kund:innen
  list: () => request("/kunden"),
  // POST /kunden -> neue Kund:in anlegen, gibt den angelegten Datensatz zurück
  create: (kunde) =>
    request("/kunden", { method: "POST", body: JSON.stringify(kunde) }),
  // PUT /kunden/{id} -> bestehende Kund:in bearbeiten, gibt den Datensatz zurück
  update: (id, kunde) =>
    request(`/kunden/${id}`, { method: "PUT", body: JSON.stringify(kunde) }),
  // DELETE /kunden/{id} -> löscht eine Kund:in
  remove: (id) => request(`/kunden/${id}`, { method: "DELETE" }),
};
