import { useCallback, useEffect, useLayoutEffect, useRef, useState } from "react";
import { createPortal } from "react-dom";

// Geführter Rundgang ("Tour"): hebt nacheinander Elemente hervor und erklärt sie.
//
// Diese Komponente wird nur eingehängt, WÄHREND der Rundgang läuft (siehe App.jsx:
// `{tourActive && <Tour .../>}`). Dadurch beginnt jeder Start automatisch beim
// ersten Schritt – ohne Zurücksetzen per Effekt.
//
// Idee (bewusst einfach und selbst gebaut):
//   - Zu jedem Schritt suchen wir das Zielelement per data-tour-Selektor.
//   - Mit getBoundingClientRect() holen wir seine Position im sichtbaren Bereich.
//   - Ein "Spotlight"-Div legt sich genau darüber. Sein riesiger Schatten
//     (box-shadow) dunkelt die restliche Seite ab -> nur das Ziel bleibt hell.
//   - Ein Kästchen (Tooltip) daneben zeigt Titel, Text, Zähler und Buttons.
//
// Props:
//   steps   – Array der Schritte { selector, titel, text }
//   onClose – wird aufgerufen, wenn der Rundgang endet (Fertig/Überspringen/Esc)
export default function Tour({ steps, onClose }) {
  const [stepIndex, setStepIndex] = useState(0);
  const [rect, setRect] = useState(null); // Position des Zielelements (oder null)
  const [tipPos, setTipPos] = useState(null); // berechnete Tooltip-Position
  const tooltipRef = useRef(null);

  // Zielelement + Tooltip vermessen und beide Positionen setzen. Fehlt das
  // Zielelement, gibt es kein Spotlight (rect = null) und der Tooltip erscheint
  // mittig (tipPos = null -> CSS zentriert ihn).
  const measure = useCallback(() => {
    const step = steps[stepIndex];
    const el = step ? document.querySelector(step.selector) : null;
    const r = el ? el.getBoundingClientRect() : null;
    setRect(r);

    const tip = tooltipRef.current;
    if (!r || !tip) {
      setTipPos(null);
      return;
    }
    // Tooltip standardmäßig unter das Ziel; kein Platz -> darüber; immer in den
    // sichtbaren Bereich klemmen.
    const t = tip.getBoundingClientRect();
    const gap = 12;
    const rand = 12; // Mindestabstand zum Fensterrand
    let top = r.bottom + gap;
    if (top + t.height > window.innerHeight - rand) {
      top = r.top - gap - t.height;
    }
    if (top < rand) top = rand;
    let left = r.left;
    if (left + t.width > window.innerWidth - rand) {
      left = window.innerWidth - rand - t.width;
    }
    if (left < rand) left = rand;
    setTipPos({ top, left });
  }, [steps, stepIndex]);

  // Bei Schrittwechsel: Ziel in den sichtbaren Bereich scrollen, dann im nächsten
  // Frame vermessen (nach dem Layout des Browsers). Zusätzlich bei Größen-/
  // Scroll-Änderung neu vermessen, damit das Spotlight dem Element folgt.
  useLayoutEffect(() => {
    const step = steps[stepIndex];
    const el = step ? document.querySelector(step.selector) : null;
    if (el) el.scrollIntoView({ block: "center", behavior: "smooth" });
    const raf = requestAnimationFrame(measure);
    window.addEventListener("resize", measure);
    // capture=true: auch Scrollen innerhalb von Containern erfassen.
    window.addEventListener("scroll", measure, true);
    return () => {
      cancelAnimationFrame(raf);
      window.removeEventListener("resize", measure);
      window.removeEventListener("scroll", measure, true);
    };
  }, [stepIndex, steps, measure]);

  const goNext = useCallback(() => {
    if (stepIndex >= steps.length - 1) onClose();
    else setStepIndex(stepIndex + 1);
  }, [stepIndex, steps.length, onClose]);

  const goPrev = useCallback(() => {
    setStepIndex((i) => Math.max(0, i - 1));
  }, []);

  // Tastatursteuerung: Esc schließt, Pfeile/Enter blättern.
  useEffect(() => {
    function onKey(e) {
      if (e.key === "Escape") onClose();
      else if (e.key === "ArrowRight" || e.key === "Enter") goNext();
      else if (e.key === "ArrowLeft") goPrev();
    }
    window.addEventListener("keydown", onKey);
    return () => window.removeEventListener("keydown", onKey);
  }, [goNext, goPrev, onClose]);

  if (steps.length === 0) return null;

  const step = steps[stepIndex];
  const istLetzter = stepIndex === steps.length - 1;
  const pad = 6; // etwas Rand um das hervorgehobene Element

  // Das Overlay wird per Portal an <body> gehängt, damit es über allem liegt.
  return createPortal(
    <div className="tour-root">
      {/* Fängt Klicks ab, damit die Oberfläche während des Rundgangs ruhig bleibt. */}
      <div className="tour-backdrop" />

      {/* Spotlight nur, wenn ein Zielelement gefunden wurde. */}
      {rect && (
        <div
          className="tour-spotlight"
          style={{
            top: rect.top - pad,
            left: rect.left - pad,
            width: rect.width + pad * 2,
            height: rect.height + pad * 2,
          }}
        />
      )}

      {/* Erklär-Kästchen. Ohne Ziel (tipPos null) zeigt CSS es mittig. */}
      <div
        ref={tooltipRef}
        className={`tour-tooltip${tipPos ? "" : " tour-tooltip-center"}`}
        style={tipPos ?? undefined}
      >
        <div className="tour-tooltip-head">
          <span className="tour-step-chip">
            {stepIndex + 1} / {steps.length}
          </span>
          <button
            type="button"
            className="tour-close"
            aria-label="Rundgang beenden"
            onClick={onClose}
          >
            ×
          </button>
        </div>

        <h3 className="tour-titel">{step.titel}</h3>
        <p className="tour-text">{step.text}</p>

        <div className="tour-actions">
          <button
            type="button"
            className="btn-secondary"
            onClick={goPrev}
            disabled={stepIndex === 0}
          >
            Zurück
          </button>
          <button type="button" onClick={goNext}>
            {istLetzter ? "Fertig" : "Weiter"}
          </button>
        </div>
      </div>
    </div>,
    document.body
  );
}
