<?php

namespace App;

/**
 * Sehr kleiner Router: ordnet (HTTP-Methode + Pfad) einer Handler-Funktion zu.
 *
 * Begründung: So ist jede neue Route eine Zeile, statt einer wachsenden
 * switch-Anweisung im Front-Controller. Pfade dürfen Platzhalter wie "{id}"
 * enthalten – der gefangene Wert wird dem Handler als Array übergeben
 * (vorbereitet für GET/PUT/DELETE /kunden/{id} in M4).
 */
class Router
{
    /** @var array<int, array{method:string, pattern:string, handler:callable}> */
    private array $routes = [];

    public function get(string $path, callable $handler): void
    {
        $this->add('GET', $path, $handler);
    }

    public function post(string $path, callable $handler): void
    {
        $this->add('POST', $path, $handler);
    }

    public function put(string $path, callable $handler): void
    {
        $this->add('PUT', $path, $handler);
    }

    public function delete(string $path, callable $handler): void
    {
        $this->add('DELETE', $path, $handler);
    }

    private function add(string $method, string $path, callable $handler): void
    {
        $this->routes[] = [
            'method'  => $method,
            'pattern' => $this->toPattern($path),
            'handler' => $handler,
        ];
    }

    /** Wandelt z. B. "/kunden/{id}" in einen regulären Ausdruck mit benannter Gruppe um. */
    private function toPattern(string $path): string
    {
        $regex = preg_replace('#\{([a-zA-Z_]+)\}#', '(?<$1>[^/]+)', $path);
        return '#^' . $regex . '$#';
    }

    /**
     * Sucht die passende Route und ruft ihren Handler auf.
     *  - 404, wenn kein Pfad passt.
     *  - 405, wenn der Pfad passt, aber die Methode nicht.
     */
    public function dispatch(string $method, string $path): void
    {
        $pathMatched = false;

        foreach ($this->routes as $route) {
            if (preg_match($route['pattern'], $path, $matches)) {
                $pathMatched = true;
                if ($route['method'] === $method) {
                    // Nur benannte Platzhalter (z. B. "id") an den Handler geben.
                    $params = array_filter($matches, 'is_string', ARRAY_FILTER_USE_KEY);
                    $route['handler']($params);
                    return;
                }
            }
        }

        if ($pathMatched) {
            Response::error('Methode nicht erlaubt', 405);
        } else {
            Response::error('Nicht gefunden', 404);
        }
    }
}
