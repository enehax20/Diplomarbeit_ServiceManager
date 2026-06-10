<?php

namespace App;

/**
 * Kleine Helfer für einheitliche JSON-Antworten.
 * So sieht jede Antwort der API gleich aus und ist im Frontend leicht
 * zu verarbeiten.
 */
class Response
{
    /** Sendet $data als JSON mit dem angegebenen HTTP-Statuscode. */
    public static function json($data, int $status = 200): void
    {
        http_response_code($status);
        header('Content-Type: application/json; charset=utf-8');
        // UNESCAPED_UNICODE: Umlaute bleiben lesbar (ä statt ä).
        echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    /**
     * Sendet eine Fehlermeldung im einheitlichen Format { "error": ... }.
     * Über $extra lassen sich Zusatzinfos ergänzen (z. B. Feldfehler).
     */
    public static function error(string $message, int $status = 400, array $extra = []): void
    {
        self::json(array_merge(['error' => $message], $extra), $status);
    }
}
