import { FORM_ACTIONS } from "../constantes/acoes.js";

export const el = (selector) => document.querySelector(selector);
export const esc = (value) =>
  String(value ?? "").replace(
    /[&<>"']/g,
    (char) =>
      ({
        "&": "&amp;",
        "<": "&lt;",
        ">": "&gt;",
        '"': "&quot;",
        "'": "&#039;",
      })[char],
  );
export const button = (label, action, tone = "primary", attributes = "") =>
  `<button class="button ${esc(tone)}" data-action="${esc(action)}" type="${FORM_ACTIONS.has(action) ? "submit" : "button"}"${attributes ? ` ${attributes}` : ""}>${esc(label)}</button>`;
