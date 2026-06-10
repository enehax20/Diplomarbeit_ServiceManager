// Zentrale Stelle für alle Backend-Aufrufe (plain fetch).
// Die Basis-URL kommt aus der Umgebungsvariable, mit sinnvollem Standard.
const API_BASE = import.meta.env.VITE_API_BASE_URL || "http://localhost:8000";

// Kleiner Helfer: führt fetch aus, prüft den Status und gibt JSON zurück.
async function request(path, options = {}) {
  const response = await fetch(`${API_BASE}${path}`, {
    headers: { "Content-Type": "application/json" },
    ...options,
  });

  // Antwort als Text lesen, dann zu JSON parsen (leerer Body -> null).
  const text = await response.text();
  const data = text ? JSON.parse(text) : null;

  // Bei Fehlerstatus (4xx/5xx) eine Exception mit der Backend-Meldung werfen.
  if (!response.ok) {
    const error = new Error(data?.error || `Fehler ${response.status}`);
    error.fields = data?.fields || null; // Feld-Validierungsfehler (falls vorhanden)
    throw error;
  }

  return data;
}

// Konkrete Endpunkte für Kund:innen.
export const kundenApi = {
  // GET /kunden -> Liste aller Kund:innen
  list: () => request("/kunden"),
  // POST /kunden -> neue Kund:in anlegen, gibt den angelegten Datensatz zurück
  create: (kunde) =>
    request("/kunden", { method: "POST", body: JSON.stringify(kunde) }),
  // DELETE /kunden/{id} -> löscht eine Kund:in
  remove: (id) => request(`/kunden/${id}`, { method: "DELETE" }),
};
