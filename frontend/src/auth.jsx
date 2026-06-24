import { createContext, useContext, useEffect, useState } from "react";
import { authApi } from "./api.js";

// Kleiner globaler Anmelde-Zustand über die React-Context-API.
// Begründung: So kennt JEDE Komponente die angemeldete Person (inkl. Rolle),
// ohne dass wir die Daten von Hand durch alle Ebenen reichen müssen.
const AuthContext = createContext(null);

export function AuthProvider({ children }) {
  const [user, setUser] = useState(null); // angemeldete Person oder null
  const [loading, setLoading] = useState(true); // erste Prüfung läuft noch

  // Beim Start prüfen, ob bereits eine gültige Session besteht (GET /me).
  useEffect(() => {
    authApi
      .me()
      .then((u) => setUser(u))
      .catch(() => setUser(null)) // 401 = nicht angemeldet -> bleibt null
      .finally(() => setLoading(false));
  }, []);

  // Anmelden: Zugangsdaten ans Backend, bei Erfolg Person merken.
  async function login(benutzername, passwort) {
    const u = await authApi.login(benutzername, passwort);
    setUser(u);
    return u;
  }

  // Abmelden: Session im Backend beenden und Zustand leeren.
  async function logout() {
    try {
      await authApi.logout();
    } finally {
      setUser(null);
    }
  }

  const value = { user, loading, login, logout, isAdmin: user?.rolle === "admin" };
  return <AuthContext.Provider value={value}>{children}</AuthContext.Provider>;
}

// Bequemer Zugriff: const { user, login, logout } = useAuth();
export function useAuth() {
  return useContext(AuthContext);
}
