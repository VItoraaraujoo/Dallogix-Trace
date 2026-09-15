import { ArmazenamentoTrace } from "./classes/ArmazenamentoTrace.js?v=202609150500";
import { FORM_ACTIONS } from "./constantes/acoes.js?v=202609140210";
import { el, esc } from "./funcoes/html.js";
import { numero } from "./funcoes/formato.js";
import { rotuloEstado } from "./funcoes/rotulos.js";
import { settings } from "./telas/configuracoes.js?v=202609140210";
import { dalaActions, dalaEdit, dalas, dalaView } from "./telas/dalas.js?v=202609150200";
import { company } from "./telas/empresa.js";
import { companies } from "./telas/empresas.js";
import { errorLogs } from "./telas/logs.js";
import { masterHome } from "./telas/master.js?v=202609150200";
import {
    alerts,
    emergency,
    history,
    occurrences,
    products,
    summary,
} from "./telas/monitoramento.js?v=202609150400";
import {
    division,
    importScreen,
    manifestEdit,
    manifests,
    manifestView,
    work,
} from "./telas/operacoes.js?v=202609150500";
import { dashboard } from "./telas/painel.js?v=202609150300";
import { users } from "./telas/usuarios.js";
import { atualizarStatusDasDalas, linhaItemRomaneio } from "./controladores/operacao.js";

const store = new ArmazenamentoTrace();
let renderRequestId = 0;
let workViewSignature = "";
const screens = {
  dashboard,
  manifests,
  import: importScreen,
  division,
  work,
  occurrences,
  summary,
  history,
  products,
  alerts,
  emergency,
  settings,
  dalas,
  dala: dalaView,
  "dala-edit": dalaEdit,
  "dala-actions": dalaActions,
  manifest: manifestView,
  "manifest-edit": manifestEdit,
  companies,
  "master-home": masterHome,
  company,
  users,
  "error-logs": errorLogs,
};
const ROLE_LABELS = {
  ADMIN_DALLOGIX: "Master Dallogix",
  ADMIN_EMPRESA: "Administrador da empresa",
  SUPERVISOR: "Supervisor",
  USUARIO: "Operador",
};
const ROLE_PAGES = {
  ADMIN_DALLOGIX: ["master-home", "companies", "company", "users", "error-logs"],
  ADMIN_EMPRESA: [
    "dashboard",
    "manifests",
    "manifest",
    "manifest-edit",
    "import",
    "division",
    "work",
    "occurrences",
    "summary",
    "history",
    "products",
    "alerts",
    "emergency",
    "settings",
    "dalas",
    "dala",
    "dala-edit",
    "dala-actions",
    "users",
    "error-logs",
  ],
  SUPERVISOR: [
    "dashboard",
    "manifests",
    "manifest",
    "manifest-edit",
    "import",
    "division",
    "work",
    "occurrences",
    "summary",
    "history",
    "products",
    "alerts",
    "emergency",
    "dala",
    "dala-actions",
  ],
  USUARIO: [
    "dashboard",
    "manifests",
    "manifest",
    "dala",
    "work",
    "summary",
    "occurrences",
    "alerts",
    "emergency",
  ],
};
const NAV_GROUPS = [
  [
    "Operação",
    [
      ["manifests", "Romaneios"],
      ["dashboard", "Dashboard"],
    ],
  ],
  [
    "Administração",
    [
      ["master-home", "Visão geral"],
      ["companies", "Empresas"],
    ],
  ],
  [
    "Cadastros",
    [
      ["products", "Produtos"],
      ["dalas", "Dalas"],
    ],
  ],
  [
    "Sistema",
    [
      ["settings", "Configurações"],
      ["error-logs", "Logs de erros"],
    ],
  ],
];
const PAGE_LABELS = {
  dashboard: "Dashboard",
  manifests: "Romaneios",
  import: "Importar romaneio",
  division: "Divisão de carga",
  work: "Operação",
  occurrences: "Ocorrências",
  summary: "Resumo final",
  history: "Histórico",
  products: "Produtos",
  alerts: "Alertas",
  emergency: "Emergência",
  settings: "Configurações",
  dalas: "Dalas",
  dala: "Visualizar Dala",
  "dala-edit": "Editar Dala",
  "dala-actions": "Ações da Dala",
  manifest: "Visualizar romaneio",
  "manifest-edit": "Editar romaneio",
  companies: "Empresas",
  "master-home": "Visão geral",
  company: "Empresa",
  users: "Usuários",
  "error-logs": "Logs de erros",
};
// Os arquivos HTML continuam acessíveis diretamente, mas, após o primeiro carregamento,
// as mudanças de tela usam o History API para não reiniciar toda a aplicação.
const initialPage =
  document.querySelector("script[data-page]")?.dataset.page || "";
let currentPage = initialPage;
let authenticatedUser = null;
let workTimer = null;
let workPolling = false;
let localHealthTimer = null;
let localHealthRequest = false;

function sidebarCollapsed() {
  try {
    return localStorage.getItem("trace-sidebar-collapsed") === "1";
  } catch (error) {
    return false;
  }
}
function normalizeRole(role) {
  return typeof role === "string" ? role.toUpperCase() : "";
}
function allowedPages() {
  return ROLE_PAGES[normalizeRole(authenticatedUser?.role)] || [];
}
function defaultPage() {
  const normalizedRole = normalizeRole(authenticatedUser?.role);
  if (normalizedRole === "ADMIN_DALLOGIX") return "master-home";
  const permittedPages = allowedPages();
  return permittedPages.includes("dashboard") ? "dashboard" : permittedPages[0] || "manifests";
}
function isAllowedPage(page) {
  return typeof page === "string" && allowedPages().includes(page);
}
function resolveAuthorizedPage(page, fallbackPage = defaultPage()) {
  if (typeof page !== "string" || !isAllowedPage(page)) return fallbackPage;
  return page;
}
function pagePath(page, query = "") {
  return `${page}.html${query}`;
}
function pageFromPath(pathname = window.location.pathname) {
  const file = pathname.split("/").pop() || "index.html";
  return file === "index.html" ? "login" : file.replace(/\.html$/, "");
}
async function navigate(page, query = "", { replace = false } = {}) {
  const safePage = resolveAuthorizedPage(page, defaultPage());
  page = safePage;
  if (!screens[page]) {
    page = defaultPage();
    query = "";
  }
  const path = pagePath(page, query);
  if (currentPage === page && window.location.search === query) return;
  currentPage = page;
  if (replace) window.history.replaceState({ page }, "", path);
  else window.history.pushState({ page }, "", path);
  await renderPage();
  window.scrollTo({ top: 0, behavior: "auto" });
}
function queryId() {
  return new URLSearchParams(window.location.search).get("id");
}
function queryReturnPage() {
  const page = new URLSearchParams(window.location.search).get("from");
  return ["dashboard", "dalas", "master-home", "companies"].includes(page || "")
    ? page
    : "dalas";
}

function navigationIcon(page) {
  const icons = {
    manifests:
      '<svg viewBox="0 0 24 24" aria-hidden="true"><rect x="6" y="4" width="12" height="16" rx="2"/><path d="M9 4.5h6M9 10h6M9 14h4"/></svg>',
    dashboard:
      '<svg viewBox="0 0 24 24" aria-hidden="true"><rect x="4" y="4" width="6" height="6" rx="1"/><rect x="14" y="4" width="6" height="6" rx="1"/><rect x="4" y="14" width="6" height="6" rx="1"/><rect x="14" y="14" width="6" height="6" rx="1"/></svg>',
    products:
      '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="m12 3 8 4.5v9L12 21l-8-4.5v-9L12 3Z"/><path d="m4.5 7.8 7.5 4.3 7.5-4.3M12 12v9"/></svg>',
    dalas:
      '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M3 7h10v9H3zM13 10h4l3 3v3h-7z"/><circle cx="7" cy="18" r="2"/><circle cx="17" cy="18" r="2"/></svg>',
    settings:
      '<svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.7 1.7 0 0 0 .34 1.88l.06.06-2.1 2.1-.06-.06a1.7 1.7 0 0 0-1.88-.34 1.7 1.7 0 0 0-1.03 1.55v.1h-3v-.1A1.7 1.7 0 0 0 10.7 18.6a1.7 1.7 0 0 0-1.88.34l-.06.06-2.1-2.1.06-.06A1.7 1.7 0 0 0 7.06 15a1.7 1.7 0 0 0-1.55-1.03h-.1v-3h.1A1.7 1.7 0 0 0 7.06 9.94 1.7 1.7 0 0 0 6.72 8.06L6.66 8l2.1-2.1.06.06a1.7 1.7 0 0 0 1.88.34 1.7 1.7 0 0 0 1.03-1.55v-.1h3v.1A1.7 1.7 0 0 0 15.76 6.3a1.7 1.7 0 0 0 1.88-.34l.06-.06 2.1 2.1-.06.06a1.7 1.7 0 0 0-.34 1.88 1.7 1.7 0 0 0 1.55 1.03h.1v3h-.1A1.7 1.7 0 0 0 19.4 15Z"/></svg>',
    work: '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M3 8h18v8H3zM7 8V5h10v3M7 16v3h10v-3"/><path d="M8 12h8"/></svg>',
    occurrences:
      '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 3 2.8 20h18.4L12 3Z"/><path d="M12 9v5M12 17h.01"/></svg>',
    history:
      '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M3 12a9 9 0 1 0 3-6.7"/><path d="M3 4v5h5M12 7v5l3 2"/></svg>',
    summary:
      '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M5 3h14v18H5z"/><path d="M8 8h8M8 12h8M8 16h5"/></svg>',
    alerts:
      '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M18 8a6 6 0 0 0-12 0c0 7-3 7-3 9h18c0-2-3-2-3-9M10 21h4"/></svg>',
    companies:
      '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M4 21V5l8-3 8 3v16M4 10h16M8 7h.01M12 7h.01M16 7h.01M8 14h.01M12 14h.01M16 14h.01"/></svg>',
    "master-home":
      '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M4 20V5h16v15M8 9h8M8 13h4M8 17h8"/><path d="M16 13h3v4h-3z"/></svg>',
    users:
      '<svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="9" cy="8" r="3"/><circle cx="17" cy="9" r="2"/><path d="M3 20c.7-4 10.3-4 12 0M15 15c3.2-.4 5.3 1.1 6 3.2"/></svg>',
  };
  return icons[page] || icons.dashboard;
}

function setLocalIndicator(state, checkedAt = null) {
  const labels = {
    online: "Sistema local online",
    degraded: "Sistema local sem confirmação",
    offline: "Sistema local sem comunicação",
  };
  const detail = checkedAt
    ? `Última verificação: ${new Date(checkedAt).toLocaleTimeString("pt-BR")}`
    : "Aguardando verificação";
  document.querySelectorAll(".local").forEach((indicator) => {
    indicator.classList.remove("is-online", "is-degraded", "is-offline");
    indicator.classList.add(`is-${state}`);
    indicator.innerHTML = `<i></i> ${labels[state] || labels.degraded}`;
    indicator.title = detail;
    indicator.setAttribute("aria-label", `${labels[state] || labels.degraded}. ${detail}`);
  });
}

async function refreshLocalIndicator() {
  if (localHealthRequest) return;
  if (!navigator.onLine) {
    setLocalIndicator("offline");
    return;
  }
  localHealthRequest = true;
  try {
    const response = await fetch(`/api/health.php?ts=${Date.now()}`, {
      cache: "no-store",
      headers: { Accept: "application/json" },
    });
    const result = await response.json().catch(() => ({}));
    const state = response.ok && result.status === "ok" ? "online" : "degraded";
    setLocalIndicator(state, result.checked_at || Date.now());
  } catch (error) {
    setLocalIndicator("offline");
  } finally {
    localHealthRequest = false;
  }
}

function installLocalIndicator() {
  if (localHealthTimer) return;
  window.addEventListener("online", refreshLocalIndicator);
  window.addEventListener("offline", refreshLocalIndicator);
  refreshLocalIndicator();
  localHealthTimer = window.setInterval(refreshLocalIndicator, 30000);
}

function installOfflineShell() {
  if (!("serviceWorker" in navigator) || window.location.protocol === "file:") return;
  navigator.serviceWorker.register("/service-worker.js").catch(() => {
    // A aplicação continua funcional quando o navegador não oferece suporte ao cache offline.
  });
}

function renderLogin(message = "") {
  const box = el("#login-error");
  if (box) {
    box.setAttribute("role", "alert");
    box.setAttribute("aria-live", "assertive");
    box.innerHTML = message
      ? `<div class="login-error">${esc(message)}</div>`
      : "";
    if (message) box.focus();
  }
}
function hydrateChrome() {
  const shell = document.getElementById("shell");
  const menuToggle = document.querySelector('[data-action="toggle-menu"]');
  const nav = document.querySelector(".sidebar nav");
  if (nav) nav.id = "trace-sidebar-nav";
  if (menuToggle) {
    menuToggle.setAttribute("aria-controls", "trace-sidebar-nav");
    const mobileOpen = shell?.classList.contains("mobile-menu-open") || false;
    menuToggle.setAttribute("aria-expanded", String(mobileOpen));
    menuToggle.setAttribute("title", mobileOpen ? "Fechar menu" : "Abrir menu");
    if (menuToggle.dataset.menuBound !== "1") {
      menuToggle.dataset.menuBound = "1";
      menuToggle.dataset.actionBound = "1";
      menuToggle.addEventListener("click", () => {
        const currentShell = document.querySelector(".shell");
        if (window.matchMedia("(max-width: 760px)").matches) {
          const open = currentShell?.classList.toggle("mobile-menu-open") || false;
          document.body.classList.toggle("mobile-menu-open", open);
          menuToggle.setAttribute("aria-expanded", String(open));
          menuToggle.setAttribute("title", open ? "Fechar menu" : "Abrir menu");
          return;
        }
        const collapsed = currentShell?.classList.toggle("sidebar-collapsed") || false;
        try {
          localStorage.setItem("trace-sidebar-collapsed", collapsed ? "1" : "0");
        } catch (storageError) {
          /* modo privado */
        }
      });
    }
  }
  if (shell && sidebarCollapsed()) shell.classList.add("sidebar-collapsed");
  if (nav)
    nav.innerHTML = NAV_GROUPS.map(([group, items]) => {
      const permitted = items.filter(([page]) => isAllowedPage(page));
      if (!permitted.length) return "";
      return `<span class="nav-label">${esc(group)}</span>${permitted.map(([page, label]) => `<a class="nav-item${page === currentPage ? " active" : ""}${["dashboard", "dalas"].includes(page) ? " nav-section-end" : ""}" data-page="${page}" data-label="${esc(label)}" href="${pagePath(page)}"><span class="nav-icon">${navigationIcon(page)}</span><span class="nav-text">${esc(label)}</span></a>`).join("")}`;
    }).join("");
  if (nav) nav.classList.add("is-hydrated");
  const pageLabel = PAGE_LABELS[currentPage] || "Dallogix Trace";
  document.title = `${pageLabel} • Dallogix Trace`;
  const pageHeading = document.querySelector(".topbar h1");
  if (pageHeading) {
    pageHeading.innerHTML = `<span class="brand-context">TracePlatform</span><small>/ Dallogix Trace</small><span class="page-context"> · ${esc(pageLabel)}</span>`;
  }
  installLocalIndicator();
  const userRole = normalizeRole(authenticatedUser?.role);
  el("#user-avatar").textContent = (authenticatedUser?.name || "A")
    .slice(0, 1)
    .toUpperCase();
  el("#user-name").textContent = authenticatedUser?.name || "";
  el("#user-role").textContent = ROLE_LABELS[userRole] || (authenticatedUser?.role || "");
}
function render() {
  hydrateChrome();
  el("#screen-root").innerHTML = screens[currentPage](store);
  bindActions();
  bindForms();
  if (["dalas", "dala"].includes(currentPage)) atualizarStatusDasDalas(store);
  workViewSignature = currentPage === "work" ? workStructureSignature() : "";
  if (currentPage === "work") startWorkPolling();
  else stopWorkPolling();
}
function stopWorkPolling() {
  if (workTimer) {
    window.clearInterval(workTimer);
    workTimer = null;
  }
}
function startWorkPolling() {
  if (workTimer) return;
  workTimer = window.setInterval(async () => {
    if (currentPage !== "work") {
      stopWorkPolling();
      return;
    }
    if (workPolling) return;
    workPolling = true;
    try {
      await Promise.all([
        store.loadActiveLoading(store.state.selectedLoadingId),
        store.loadMonitoring(),
      ]);
      const nextSignature = workStructureSignature();
      if (nextSignature !== workViewSignature) {
        render();
      } else {
        refreshWorkLiveView();
      }
    } catch (error) {
      /* mantém o último estado visível */
    } finally {
      workPolling = false;
    }
  }, 2000);
}
function workStructureSignature() {
  const pending = (store.state.pendingReadings || []).map((reading) => reading.id).join(",");
  const command = store.state.plcCommand;
  return JSON.stringify([
    store.state.selectedLoadingId || null,
    store.state.emergency,
    store.state.operationalState,
    pending,
    command?.status || null,
    command?.response_message || null,
    store.clpDisponivel(),
  ]);
}
function refreshWorkLiveView() {
  const loaded = Number(store.state.loaded) || 0;
  const planned = Number(store.state.planned) || 0;
  const remaining = Math.max(0, planned - loaded);
  const percent = planned > 0 ? Math.min(100, Math.round((loaded / planned) * 100)) : 0;
  const values = {
    planned: numero(planned),
    loaded: numero(loaded),
    remaining: numero(remaining),
    "loaded-secondary": numero(loaded),
    "operational-state": rotuloEstado(store.state.operationalState),
    "progress-percent": `${percent}% concluído`,
    "progress-count": `${numero(loaded)} / ${numero(planned)} sacas`,
  };
  Object.entries(values).forEach(([key, value]) => {
    document.querySelectorAll(`[data-live="${key}"]`).forEach((node) => {
      node.textContent = String(value);
    });
  });
  const progressBar = document.querySelector('[data-live="progress-bar"]');
  if (progressBar) progressBar.style.width = `${percent}%`;
  const statuses = store.state.monitoring?.dispositivos || [];
  const selectedEquipmentId = Number(store.state.equipmentId) || null;
  const statusByType = Object.fromEntries(
    ["SENSOR", "SCANNER", "CLP", "CAMERA", "SERVER"].map((type) => {
      const device = statuses.find((item) =>
        item.device_type === type &&
        (!selectedEquipmentId || Number(item.equipment_id) === selectedEquipmentId),
      );
      const value = device?.status || (type === "SERVER" ? "OK" : "NÃO REGISTRADO");
      return [type, ["ONLINE", "LOCAL"].includes(value) ? "OK" : value];
    }),
  );
  document.querySelectorAll("[data-live-status]").forEach((node) => {
    node.textContent = statusByType[node.dataset.liveStatus] || "NÃO REGISTRADO";
  });
}
function installInteractionGuards() {
  document.addEventListener("dragstart", (event) => {
    if (event.target.closest("a, button, img, svg, [data-action]"))
      event.preventDefault();
  });
  document.addEventListener("dragover", (event) => {
    if (event.target.closest("a, button, img, svg, [data-action]"))
      event.preventDefault();
  });
  document.addEventListener("drop", (event) => {
    if (event.target.closest("a, button, img, svg, [data-action]"))
      event.preventDefault();
  });
}
function downloadCsv(filename, rows) {
  const csvCell = (value) => {
    let text = String(value ?? "");
    // Planilhas interpretam células iniciadas por estes caracteres como fórmulas.
    // Dados operacionais exportados devem permanecer texto ao abrir no Excel/LibreOffice.
    if (/^[\t\r\n ]*[=+\-@]/.test(text)) text = `'${text}`;
    return `"${text.replaceAll('"', '""')}"`;
  };
  const csv = rows
    .map((row) =>
      row
        .map(csvCell)
        .join(";"),
    )
    .join("\n");
  const link = document.createElement("a");
  link.href = URL.createObjectURL(
    new Blob([`\ufeff${csv}`], { type: "text/csv;charset=utf-8" }),
  );
  link.download = filename;
  link.click();
  URL.revokeObjectURL(link.href);
}
function timedCommandConfirmation({
  title,
  message,
  confirmLabel = "Sim",
  timeout = 8,
}) {
  return new Promise((resolve) => {
    const overlay = document.createElement("div");
    overlay.className = "command-confirm-overlay";
    overlay.innerHTML = `<section class="command-confirm" role="dialog" aria-modal="true" aria-labelledby="command-confirm-title">
      <span class="kicker">Confirmação obrigatória</span>
      <h2 id="command-confirm-title">${esc(title)}</h2>
      <p>${esc(message)}</p>
      <strong class="command-confirm-timer">Confirme em <span>${timeout}</span> s</strong>
      <div class="command-confirm-actions"><button class="button ghost" data-confirm="no" type="button">Não</button><button class="button primary" data-confirm="yes" type="button">${esc(confirmLabel)}</button></div>
    </section>`;
    document.body.appendChild(overlay);
    const timer = overlay.querySelector(".command-confirm-timer span");
    let remaining = timeout;
    let settled = false;
    const finish = (confirmed) => {
      if (settled) return;
      settled = true;
      window.clearInterval(interval);
      overlay.remove();
      resolve(confirmed);
    };
    const interval = window.setInterval(() => {
      remaining -= 1;
      if (timer) timer.textContent = String(Math.max(remaining, 0));
      if (remaining <= 0) finish(false);
    }, 1000);
    overlay
      .querySelector('[data-confirm="yes"]')
      .addEventListener("click", () => finish(true));
    overlay
      .querySelector('[data-confirm="no"]')
      .addEventListener("click", () => finish(false));
  });
}
function bindActions() {
  if (!document.body.dataset.shellInteractionsBound) {
    document.body.dataset.shellInteractionsBound = "1";
    document.addEventListener("keydown", (event) => {
      if (event.key !== "Escape") return;
      const shell = document.querySelector(".shell");
      const toggle = document.querySelector('[data-action="toggle-menu"]');
      if (!shell?.classList.contains("mobile-menu-open")) return;
      shell.classList.remove("mobile-menu-open");
      document.body.classList.remove("mobile-menu-open");
      toggle?.setAttribute("aria-expanded", "false");
      toggle?.setAttribute("title", "Abrir menu");
    });
  }
  document.querySelectorAll("[data-action]").forEach((node) => {
    if (node.dataset.actionBound === "1") return;
    node.dataset.actionBound = "1";
    node.addEventListener("click", async () => {
      const action = node.dataset.action;
      // Botões com uma ação declarada precisam seguir sua rota contextual
      // (por exemplo, Dala aberta pelo dashboard volta ao dashboard). O
      // fallback de histórico fica reservado para botões sem rota própria.
      if (node.classList.contains("page-back") && !action) {
        if (window.history.length > 1) window.history.back();
        else await navigate(defaultPage(), "", { replace: true });
        return;
      }
      if (FORM_ACTIONS.has(action)) {
        // Botões `submit` já disparam o evento do formulário. Chamar
        // requestSubmit() novamente aqui duplicava cadastros e comandos.
        if (node.getAttribute("type") !== "submit") {
          node.closest("form")?.requestSubmit();
        }
        return;
      }
      if (action === "reload-page") {
        await renderPage();
        return;
      }
      if (action === "toggle-menu") {
        const shell = document.querySelector(".shell");
        if (window.matchMedia("(max-width: 760px)").matches) {
          const open = shell?.classList.toggle("mobile-menu-open") || false;
          document.body.classList.toggle("mobile-menu-open", open);
          node.setAttribute("aria-expanded", String(open));
          node.setAttribute("title", open ? "Fechar menu" : "Abrir menu");
          return;
        }
        const collapsed = shell
          ? shell.classList.toggle("sidebar-collapsed")
          : false;
        try {
          localStorage.setItem(
            "trace-sidebar-collapsed",
            collapsed ? "1" : "0",
          );
        } catch (storageError) {
          /* modo privado */
        }
        return;
      }
      if (action === "open-company") {
        navigate("company", `?id=${node.dataset.id}&from=${currentPage}`);
        return;
      }
      if (action === "delete-company") {
        const name = node.dataset.name || "esta empresa";
        if (!confirm(`Remover a empresa "${name}"? Esta ação não poderá ser desfeita.`)) return;
        const typed = prompt(`Para confirmar, digite EXCLUIR para remover "${name}".`);
        if (typed !== "EXCLUIR") {
          if (typed !== null) alert("Confirmação inválida. A empresa não foi removida.");
          return;
        }
        try {
          await store.deleteCompany(node.dataset.id);
          alert("Empresa removida.");
          render();
        } catch (error) {
          alert(error.message);
        }
        return;
      }
      if (action === "toggle-license") {
        const active = node.dataset.status === "ATIVA";
        const reason = active
          ? prompt("Motivo do bloqueio da licença:", "Bloqueio manual")
          : "Licença desbloqueada manualmente";
        if (reason === null) return;
        try {
          await store.updateLicense({
            company_id: Number(node.dataset.id),
            status: active ? "BLOQUEADA" : "ATIVA",
            blocked_reason: active ? reason : "",
          });
          render();
        } catch (error) {
          alert(error.message);
        }
        return;
      }
      if (action === "back-companies") {
        const from = new URLSearchParams(window.location.search).get("from");
        navigate(["master-home", "companies"].includes(from || "") ? from : "companies");
        return;
      }
      if (action === "back-settings") {
        navigate("settings");
        return;
      }
      if (action === "open-users") {
        navigate(
          "users",
          node.dataset.companyId ? `?company_id=${node.dataset.companyId}` : "",
        );
        return;
      }
      if (action === "toggle-user") {
        const active = node.dataset.active !== "1";
        if (
          !confirm(
            `${active ? "Ativar" : "Desativar"} o login de ${node.dataset.name}?`,
          )
        )
          return;
        try {
          await store.updateUser({
            id: node.dataset.id,
            name: node.dataset.name,
            role: node.dataset.role,
            active,
            ...(store.state.userRole === "ADMIN_DALLOGIX" &&
            store.state.selectedCompanyId
              ? { company_id: store.state.selectedCompanyId }
              : {}),
          });
          render();
        } catch (error) {
          alert(error.message);
        }
        return;
      }
      if (action === "reset-user-password") {
        const password = prompt(
          `Nova senha para ${node.dataset.name} (mínimo 10 caracteres):`,
        );
        if (password === null) return;
        if (password.length < 10) {
          alert("A senha deve ter pelo menos 10 caracteres.");
          return;
        }
        try {
          await store.updateUser({
            id: node.dataset.id,
            name: node.dataset.name,
            role: node.dataset.role,
            active: node.dataset.active === "1",
            password,
            ...(store.state.userRole === "ADMIN_DALLOGIX" &&
            store.state.selectedCompanyId
              ? { company_id: store.state.selectedCompanyId }
              : {}),
          });
          alert("Senha atualizada.");
          render();
        } catch (error) {
          alert(error.message);
        }
        return;
      }
      if (action === "logout") {
        await fetch("/api/logout.php", {
          method: "POST",
          headers: store.csrfToken ? { "X-CSRF-Token": store.csrfToken } : {},
        });
        window.location.href = "index.html";
        return;
      }
      if (action === "export-report") {
        downloadCsv(
          "dallogix-relatorio-operacional.csv",
          store.state.reportCsvRows || [],
        );
        return;
      }
      if (action === "retry-sync") {
        try {
          const result = await store.retrySync(node.dataset.id);
          alert(
            result?.reason ||
              (result?.processed ? "Evento enviado." : "Evento reprocessado."),
          );
          render();
        } catch (error) {
          alert(error.message);
        }
        return;
      }
      if (action === "goto-manifests") {
        const returnToDashboard = new URLSearchParams(window.location.search).get("from") === "dashboard";
        navigate(returnToDashboard ? "dashboard" : "manifests");
        return;
      }
      if (action === "manifest-page") {
        try {
          await store.setManifestPage(node.dataset.page);
          render();
        } catch (error) {
          alert(error.message);
        }
        return;
      }
      if (action === "goto-dalas") {
        navigate("dalas");
        return;
      }
      if (action === "goto-companies") {
        navigate("companies");
        return;
      }
      if (action === "goto-error-logs") {
        navigate("error-logs");
        return;
      }
      if (action === "reload-master-home") {
        try {
          await Promise.all([store.loadCompanies(), store.loadErrorLogs()]);
          render();
        } catch (error) {
          alert(error.message || "Não foi possível atualizar a visão geral.");
        }
        return;
      }
      if (action === "new-manifest") {
        navigate("import");
        return;
      }
      if (action === "view-manifest") {
        const fromDashboard = currentPage === "dashboard" ? "&from=dashboard" : "";
        navigate("manifest", `?id=${node.dataset.id}${fromDashboard}`);
        return;
      }
      if (action === "edit-manifest") {
        navigate("manifest-edit", `?id=${node.dataset.id}`);
        return;
      }
      if (action === "prepare-manifest") {
        navigate("division", `?id=${node.dataset.id}`);
        return;
      }
      if (action === "cancel-manifest") {
        if (!confirm("Confirma o cancelamento deste romaneio? Essa ação não poderá ser desfeita.")) return;
        if (!confirm("SEGUNDA CONFIRMAÇÃO: cancelar este romaneio agora?")) return;
        try {
          await store.cancelManifest(node.dataset.id || queryId());
          alert("Romaneio cancelado.");
          await navigate("manifests");
          render();
        } catch (error) {
          alert(error.message);
        }
        return;
      }
      if (action === "cancel-manifest-progress") {
        const reason = prompt("Informe o motivo do cancelamento da operação:") || "";
        if (!reason.trim()) return;
        if (!confirm("Confirma o cancelamento? A operação precisa estar pausada ou em emergência.")) return;
        try {
          await store.cancelManifest(node.dataset.id || queryId(), reason.trim());
          alert("Operação cancelada e registrada na auditoria.");
          await navigate("manifests");
          render();
        } catch (error) {
          alert(error.message);
        }
        return;
      }
      if (action === "back-manifest") {
        navigate("manifest", `?id=${queryId()}`);
        return;
      }
      if (action === "resume-loading") {
        store.state.selectedLoadingId = Number(node.dataset.loadingId) || null;
        navigate("work");
        return;
      }
      if (action === "select-loading") {
        try {
          await store.loadActiveLoading(node.dataset.id);
          await store.loadMonitoring();
          render();
        } catch (error) {
          alert(error.message);
        }
        return;
      }
      if (action === "change-loading") {
        store.state.selectedLoadingId = null;
        await store.loadActiveLoading(null);
        render();
        return;
      }
      if (action === "open-summary") {
        if (!["FINALIZANDO", "FINALIZADO"].includes(store.state.operationalState)) {
          alert("O resumo final ficará disponível quando a quantidade prevista for atingida.");
          return;
        }
        await navigate("summary");
        return;
      }
      if (action === "back-work") {
        await navigate("work");
        return;
      }
      if (action === "view-dala") {
        const from =
          currentPage === "dashboard" ? "dashboard" : queryReturnPage();
        navigate("dala", `?id=${node.dataset.id}&from=${from}`);
        return;
      }
      if (action === "dala-actions") {
        navigate("dala-actions", `?id=${node.dataset.id}&from=dalas`);
        return;
      }
      if (action === "edit-dala") {
        navigate(
          "dala-edit",
          `?id=${node.dataset.id}&from=${queryReturnPage()}`,
        );
        return;
      }
      if (action === "back-dala") {
        navigate(queryReturnPage());
        return;
      }
      if (action === "open-dala-operation") {
        store.state.selectedLoadingId = Number(node.dataset.loadingId) || null;
        await navigate("work");
        return;
      }
      if (action === "new-dala-action" || action === "edit-dala-action") {
        const form = document.querySelector("#dala-action-form");
        const panel = document.querySelector(".dala-action-editor");
        const selected = action === "edit-dala-action"
          ? (store.state.dalaActionConfig?.acoes || []).find((item) => String(item.id) === String(node.dataset.id))
          : null;
        if (form && panel) {
          form.reset();
          form.elements.id.value = selected?.id || "";
          form.elements.comando.value = selected?.comando || "INICIAR_CARREGAMENTO";
          form.elements.rotulo.value = selected?.rotulo || "";
          form.elements.cor.value = selected?.cor || "CINZA";
          form.elements.modo.value = selected?.modo || "DIRETO";
          form.elements.visivel.checked = selected ? Boolean(Number(selected.visivel)) : true;
          panel.hidden = false;
          panel.scrollIntoView({ behavior: "smooth", block: "nearest" });
        }
        return;
      }
      if (action === "cancel-dala-action") {
        const panel = document.querySelector(".dala-action-editor");
        if (panel) panel.hidden = true;
        return;
      }
      if (action === "delete-dala-action") {
        if (!confirm(`Excluir a ação "${node.dataset.name}"?`)) return;
        try {
          await store.deleteDalaAction(queryId(), node.dataset.id);
          render();
        } catch (error) {
          alert(error.message);
        }
        return;
      }
      if (action === "move-dala-action") {
        const actions = [...(store.state.dalaActionConfig?.acoes || [])];
        const index = actions.findIndex((item) => String(item.id) === String(node.dataset.id));
        const target = index + (node.dataset.direction === "up" ? -1 : 1);
        if (index < 0 || target < 0 || target >= actions.length) return;
        [actions[index], actions[target]] = [actions[target], actions[index]];
        try {
          await store.reorderDalaActions(queryId(), actions.map((item) => item.id));
          render();
        } catch (error) {
          alert(error.message);
        }
        return;
      }
      if (action === "new-dala-trigger" || action === "edit-dala-trigger") {
        const form = document.querySelector("#dala-trigger-form");
        const panel = document.querySelector(".dala-trigger-editor");
        const triggers = store.state.dalaActionConfig?.gatilhos || [];
        const selected = action === "edit-dala-trigger"
          ? triggers.find((item) => String(item.id) === String(node.dataset.id))
          : triggers[0];
        if (form && panel && selected) {
          form.elements.trigger_id.value = selected.id;
          form.elements.acao_id.value = selected.acao_id || "";
          panel.hidden = false;
          panel.scrollIntoView({ behavior: "smooth", block: "nearest" });
        }
        return;
      }
      if (action === "cancel-dala-trigger") {
        const panel = document.querySelector(".dala-trigger-editor");
        if (panel) panel.hidden = true;
        return;
      }
      if (action === "reload-dala-diagnostics") {
        try {
          await store.loadDalaCommandHistory(node.dataset.id);
          render();
        } catch (error) {
          alert(error.message);
        }
        return;
      }
      if (action === "reload-error-logs") {
        try {
          await store.loadErrorLogs();
          render();
        } catch (error) {
          alert(error.message);
        }
        return;
      }
      if (action === "reload-dalas") {
        render();
        return;
      }
      if (action === "toggle-product-form") {
        store.state.productFormOpen = !store.state.productFormOpen;
        store.state.editingProductId = null;
        render();
        return;
      }
      if (action === "cancel-product") {
        store.state.productFormOpen = false;
        store.state.editingProductId = null;
        render();
        return;
      }
      if (action === "edit-product") {
        store.state.productFormOpen = true;
        store.state.editingProductId = node.dataset.id;
        render();
        window.scrollTo({ top: 0, behavior: "smooth" });
        return;
      }
      if (action === "delete-product") {
        if (!confirm(`Excluir o produto "${node.dataset.name}"?`)) return;
        try {
          const result = await store.deleteProduct(node.dataset.id);
          alert(
            result?.deactivated
              ? "Produto possui histórico vinculado e foi desativado."
              : "Produto excluído.",
          );
          render();
        } catch (error) {
          alert(error.message);
        }
        return;
      }
      if (action === "toggle-dala-form") {
        store.state.dalaFormOpen = !store.state.dalaFormOpen;
        render();
        return;
      }
      if (action === "delete-dala") {
        if (!["ADMIN_DALLOGIX", "ADMIN_EMPRESA"].includes(store.state.userRole)) {
          alert("Somente administradores podem excluir Dala.");
          return;
        }
        if (!confirm(`Excluir a Dala "${node.dataset.name}"?`)) return;
        if (!confirm(`SEGUNDA CONFIRMAÇÃO: excluir a Dala "${node.dataset.name}" definitivamente?`)) return;
        try {
          await store.deleteEquipment(node.dataset.id);
          alert("Dala excluída.");
          render();
        } catch (error) {
          alert(error.message);
        }
        return;
      }
      if (action === "start-machine" || action === "stop-machine") {
        if (
          action === "start-machine" &&
          !(await timedCommandConfirmation({
            title: "Iniciar carregamento?",
            message:
              "Confirme que a esteira está livre e pronta para carregar no sentido normal.",
          }))
        )
          return;
        try {
          await store.changeLoadingState(
            action === "start-machine" ? "CARREGANDO" : "PAUSADO",
            node.dataset.loadingId,
          );
          await Promise.all([
            store.loadMonitoring(),
            store.loadActiveLoading(),
            store.loadDashboard(),
          ]);
          render();
        } catch (error) {
          alert(error.message);
        }
        return;
      }
      if (action === "reverse-machine") {
        if (
          !(await timedCommandConfirmation({
            title: "Ativar reversão?",
            message:
              "A esteira precisa estar parada. O gateway só enviará o comando após validar os intertravamentos do CLP.",
          }))
        )
          return;
        try {
          const result = await store.requestMachineReverse(
            node.dataset.loadingId,
            "REVERSAO_ATIVAR",
          );
          alert(result.message);
          await store.loadMonitoring();
          render();
        } catch (error) {
          alert(error.message);
        }
        return;
      }
      if (action === "add-item") {
        const tbody = document.querySelector("#manifest-items tbody");
        if (tbody) tbody.insertAdjacentHTML("beforeend", linhaItemRomaneio(store));
        return;
      }
      if (action === "remove-item") {
        const rows = document.querySelectorAll("#manifest-items tbody tr");
        if (rows.length > 1) node.closest("tr")?.remove();
        return;
      }
      if (screens[action]) navigate(action);
      else if (action === "run") {
        if (
          !(await timedCommandConfirmation({
            title: "Iniciar carregamento?",
            message:
              "Confirme que a esteira está livre e pronta para carregar no sentido normal.",
          }))
        )
          return;
        try {
          await store.changeLoadingState("CARREGANDO");
          render();
        } catch (error) {
          alert(error.message);
        }
      } else if (action === "stop") {
        try {
          await store.changeLoadingState("PAUSADO");
          render();
        } catch (error) {
          alert(error.message);
        }
      } else if (action === "reverse-on" || action === "reverse-off" || action === "reverse-toggle") {
        if (store.state.operationalState !== "PAUSADO") {
          alert("Para alterar a reversão, pause a esteira primeiro.");
          return;
        }
        const activating = action === "reverse-on"
          ? true
          : action === "reverse-off"
            ? false
            : store.state.plcCommand?.command !== "REVERSAO_ATIVAR";
        if (
          !(await timedCommandConfirmation({
            title: activating ? "Ativar reversão?" : "Desativar reversão?",
            message:
              "O gateway só enviará o comando após validar os intertravamentos do CLP.",
            confirmLabel: activating ? "Ativar" : "Desativar",
          }))
        )
          return;
        try {
          const result = await store.requestMachineReverse(
            store.state.loadingId,
            activating ? "REVERSAO_ATIVAR" : "REVERSAO_DESATIVAR",
          );
          alert(result.message);
          render();
        } catch (error) {
          alert(error.message);
        }
      } else if (action === "emergency") {
        try {
          await store.changeLoadingState("EMERGENCIA");
          store.activateEmergency();
          render();
        } catch (error) {
          alert(error.message);
        }
      } else if (action === "unlock") {
        node.disabled = true;
        try {
          const result = await store.unlockMachine();
          alert(
            result?.alreadyUnlocked
              ? "A emergência já havia sido liberada. O estado atual foi atualizado."
              : "Emergência liberada. A máquina permanece em preparação até o próximo comando seguro.",
          );
          await navigate("work");
          render();
        } catch (error) {
          alert(error.message);
        } finally {
          node.disabled = false;
        }
      } else if (action === "finish") {
        try {
          const justification =
            store.state.loaded < store.state.planned
              ? prompt("Justificativa obrigatória para finalizar com divergência:") || ""
              : "";
          if (store.state.loaded < store.state.planned && !justification.trim()) return;
          await store.finishLoading(justification.trim());
          alert("Carregamento finalizado.");
          render();
        } catch (error) {
          alert(error.message);
        }
      } else if (action === "export") {
        downloadCsv(`dallogix-${currentPage}.csv`, [
          ["Campo", "Valor"],
          ["Romaneio", store.state.romaneio],
          ["Caminhão", store.state.truck],
          ["Estado", store.state.operationalState],
          ["Carregado", store.state.loaded],
          ["Planejado", store.state.planned],
          [
            "Sincronização pendente",
            store.state.monitoring?.sync_pendente || 0,
          ],
        ]);
      }
    });
  });
  document.querySelectorAll(".sidebar .nav-item[data-page]").forEach((item) => {
    if (item.dataset.navigationBound === "1") return;
    item.dataset.navigationBound = "1";
    item.addEventListener("click", (event) => {
      if (
        event.metaKey ||
        event.ctrlKey ||
        event.shiftKey ||
        event.button !== 0
      )
        return;
      event.preventDefault();
      if (window.matchMedia("(max-width: 760px)").matches) {
        document.querySelector(".shell")?.classList.remove("mobile-menu-open");
        document.body.classList.remove("mobile-menu-open");
        document.querySelector('[data-action="toggle-menu"]')?.setAttribute("aria-expanded", "false");
        document.querySelector('[data-action="toggle-menu"]')?.setAttribute("title", "Abrir menu");
      }
      navigate(item.dataset.page);
    });
  });
  document.querySelectorAll('input[data-action="open-users"]').forEach((node) => {
    node.addEventListener("keydown", (event) => {
      if (event.key === "Enter" || event.key === " ") node.click();
    });
  });
}
function bindLoginForm() {
  const form = document.querySelector("#login-form");
  if (!form || form.dataset.bound === "1") return;
  form.dataset.bound = "1";
  const password = form.querySelector("#login-password");
  const passwordToggle = form.querySelector(".password-toggle");
  const capsLock = form.querySelector("#login-capslock");
  const updateCapsLock = (event) => {
    if (capsLock) capsLock.hidden = !event.getModifierState?.("CapsLock");
  };
  password?.addEventListener("keydown", updateCapsLock);
  password?.addEventListener("keyup", updateCapsLock);
  password?.addEventListener("input", updateCapsLock);
  passwordToggle?.addEventListener("click", () => {
    if (!password) return;
    const visible = password.type === "text";
    password.type = visible ? "password" : "text";
    passwordToggle.textContent = visible ? "Mostrar" : "Ocultar";
    passwordToggle.setAttribute("aria-label", visible ? "Mostrar senha" : "Ocultar senha");
    passwordToggle.setAttribute("aria-pressed", String(!visible));
    password.focus();
  });
  form.addEventListener("submit", async (event) => {
    event.preventDefault();
    const submit = form.querySelector('button[type="submit"]');
    const originalLabel = submit?.textContent || "Entrar no sistema";
    if (submit) {
      submit.disabled = true;
      submit.textContent = "Entrando…";
    }
    renderLogin("");
    try {
      const data = Object.fromEntries(new FormData(form));
      const response = await fetch("/api/login.php", {
        method: "POST",
        headers: {
          "Content-Type": "application/json",
          Accept: "application/json",
        },
        body: JSON.stringify(data),
      });
      const result = await response.json().catch(() => ({}));
      if (!response.ok || result.authenticated !== true) {
        renderLogin(result.error || "E-mail ou senha inválidos.");
        return;
      }
      if (result.password_change_required) {
        const newPassword = prompt(
          "Esta senha é temporária. Informe uma nova senha (mínimo 10 caracteres):",
        );
        if (newPassword === null) {
          renderLogin("Troque a senha temporária antes de continuar.");
          return;
        }
        const confirmation = prompt("Confirme a nova senha:");
        if (newPassword.length < 10 || newPassword !== confirmation) {
          renderLogin("As senhas não coincidem ou têm menos de 10 caracteres.");
          return;
        }
        const passwordResponse = await fetch("/api/trocar_senha.php", {
          method: "POST",
          headers: {
            "Content-Type": "application/json",
            "X-CSRF-Token": result.csrf_token,
          },
          body: JSON.stringify({
            current_password: data.password,
            new_password: newPassword,
          }),
        });
        const passwordResult = await passwordResponse.json().catch(() => ({}));
        if (!passwordResponse.ok) {
          renderLogin(passwordResult.error || "Não foi possível atualizar a senha.");
          return;
        }
        result.user = passwordResult.user;
        result.csrf_token = passwordResult.csrf_token;
      }
      authenticatedUser = result.user;
      store.setUser(authenticatedUser);
      store.setCsrfToken(result.csrf_token);
      window.location.replace(pagePath(defaultPage()));
    } catch (error) {
      renderLogin(
        "Não foi possível conectar ao servidor local. Verifique se o sistema está em execução.",
      );
    } finally {
      if (submit) {
        submit.disabled = false;
        submit.textContent = originalLabel;
      }
    }
  });
}
function bindForms() {
  const occurrenceForm = document.querySelector("#occurrence-form");
  if (occurrenceForm)
    occurrenceForm.addEventListener("submit", async (event) => {
      event.preventDefault();
      try {
        await store.createOccurrence(
          Object.fromEntries(new FormData(occurrenceForm)),
        );
        render();
      } catch (error) {
        if (error.status === 401) {
          try {
            sessionStorage.setItem("trace-login-message", error.message);
          } catch (storageError) {
            /* armazenamento indisponível */
          }
          window.location.replace("index.html");
          return;
        }
        alert(error.message);
      }
    });
  // Rede do cliente: preserva os parâmetros de PDF já salvos.
  const networkForm = document.querySelector("#network-form");
  if (networkForm)
    networkForm.addEventListener("submit", async (event) => {
      event.preventDefault();
      const raw = Object.fromEntries(new FormData(networkForm));
      const saved = store.state.configuration?.settings || {};
      try {
        await store.saveConfiguration({
          gateway_public_ip: raw.gateway_public_ip,
          pdf_field_mapping: saved.pdf_field_mapping || {},
          pdf_search_field: saved.pdf_search_field || "barcode",
        });
        alert("Configuração salva.");
        render();
      } catch (error) {
        alert(error.message);
      }
    });
  // Parâmetros da importação PDF: preserva a rede já salva.
  const pdfSettingsForm = document.querySelector("#pdf-settings-form");
  if (pdfSettingsForm)
    pdfSettingsForm.addEventListener("submit", async (event) => {
      event.preventDefault();
      const raw = Object.fromEntries(new FormData(pdfSettingsForm));
      const pdf_field_mapping = {};
      Object.entries(raw)
        .filter(([key]) => key.startsWith("pdf_") && key !== "pdf_search_field")
        .forEach(([key, value]) => {
          if (value) pdf_field_mapping[key.slice(4)] = value;
        });
      const saved = store.state.configuration?.settings || {};
      try {
        await store.saveConfiguration({
          gateway_public_ip: saved.gateway_public_ip || "",
          pdf_field_mapping,
          pdf_search_field: raw.pdf_search_field,
        });
        alert("Parâmetros salvos.");
        render();
      } catch (error) {
        alert(error.message);
      }
    });
  const productForm = document.querySelector("#product-form");
  if (productForm)
    productForm.addEventListener("submit", async (event) => {
      event.preventDefault();
      if (productForm.dataset.submitting === "1") return;
      productForm.dataset.submitting = "1";
      productForm.querySelectorAll("button").forEach((button) => {
        button.disabled = true;
      });
      const raw = Object.fromEntries(new FormData(productForm));
      const editingId = productForm.dataset.editing;
      try {
        if (editingId) {
          await store.updateProduct({ id: editingId, ...raw });
          alert("Produto atualizado.");
        } else {
          await store.createProduct(raw);
          alert("Produto cadastrado.");
        }
        store.state.productFormOpen = false;
        store.state.editingProductId = null;
        render();
      } catch (error) {
        if (error.status === 401) {
          try {
            sessionStorage.setItem("trace-login-message", error.message);
          } catch (storageError) {
            /* armazenamento indisponível */
          }
          window.location.replace("index.html");
          return;
        }
        alert(error.message);
      } finally {
        productForm.dataset.submitting = "0";
        productForm.querySelectorAll("button").forEach((button) => {
          button.disabled = false;
        });
      }
    });
  const dalaCreateForm = document.querySelector("#dala-create-form");
  if (dalaCreateForm)
    dalaCreateForm.addEventListener("submit", async (event) => {
      event.preventDefault();
      try {
        await store.createEquipment(
          Object.fromEntries(new FormData(dalaCreateForm)),
        );
        alert("Dala cadastrada.");
        store.state.dalaFormOpen = false;
        render();
      } catch (error) {
        alert(error.message);
      }
    });
  const dalaEditForm = document.querySelector("#dala-edit-form");
  if (dalaEditForm)
    dalaEditForm.addEventListener("submit", async (event) => {
      event.preventDefault();
      const raw = Object.fromEntries(new FormData(dalaEditForm));
      try {
        await store.updateEquipment({ id: dalaEditForm.dataset.id, ...raw });
        alert("Dala atualizada.");
        navigate(
          "dala",
          `?id=${dalaEditForm.dataset.id}&from=${queryReturnPage()}`,
        );
      } catch (error) {
        alert(error.message);
      }
    });
  const dalaActionForm = document.querySelector("#dala-action-form");
  if (dalaActionForm)
    dalaActionForm.addEventListener("submit", async (event) => {
      event.preventDefault();
      const raw = Object.fromEntries(new FormData(dalaActionForm));
      raw.visivel = dalaActionForm.elements.visivel.checked;
      try {
        await store.saveDalaAction(queryId(), raw);
        document.querySelector(".dala-action-editor")?.setAttribute("hidden", "");
        render();
      } catch (error) {
        alert(error.message);
      }
    });
  const dalaTriggerForm = document.querySelector("#dala-trigger-form");
  if (dalaTriggerForm)
    dalaTriggerForm.addEventListener("submit", async (event) => {
      event.preventDefault();
      const raw = Object.fromEntries(new FormData(dalaTriggerForm));
      try {
        await store.saveDalaTrigger(queryId(), raw);
        document.querySelector(".dala-trigger-editor")?.setAttribute("hidden", "");
        render();
      } catch (error) {
        alert(error.message);
      }
    });
  const manifestForm = document.querySelector("#new-manifest-form");
  if (manifestForm)
    manifestForm.addEventListener("submit", async (event) => {
      event.preventDefault();
      const raw = Object.fromEntries(new FormData(manifestForm));
      const scheduledDate = raw.scheduled_date;
      const today = new Date();
      const todayString = `${today.getFullYear()}-${String(today.getMonth() + 1).padStart(2, "0")}-${String(today.getDate()).padStart(2, "0")}`;
      if (!scheduledDate || scheduledDate < todayString) {
        alert("Informe uma data igual ou posterior ao dia atual do PC industrial.");
        return;
      }
      const items = [...document.querySelectorAll("#manifest-items tbody tr")]
        .map((row) => ({
          product_id: row.querySelector('[name="item_product"]')?.value,
          quantity: row.querySelector('[name="item_quantity"]')?.value,
        }))
        .filter((item) => item.product_id && Number(item.quantity) >= 1);
      if (!items.length) {
        alert("Adicione ao menos um item com produto e quantidade.");
        return;
      }
      try {
        await store.createManifest({
          number: raw.number,
          scheduled_date: scheduledDate,
          plate: raw.plate,
          expedidor: raw.expedidor,
          driver_name: raw.driver_name,
          items,
        });
        alert("Romaneio cadastrado.");
        navigate("manifests");
      } catch (error) {
        alert(error.message);
      }
    });
  const manifestEditForm = document.querySelector("#edit-manifest-form");
  if (manifestEditForm)
    manifestEditForm.addEventListener("submit", async (event) => {
      event.preventDefault();
      const raw = Object.fromEntries(new FormData(manifestEditForm));
      const today = new Date();
      const todayString = `${today.getFullYear()}-${String(today.getMonth() + 1).padStart(2, "0")}-${String(today.getDate()).padStart(2, "0")}`;
      if (!raw.scheduled_date || raw.scheduled_date < todayString) {
        alert("Informe uma data igual ou posterior ao dia atual do PC industrial.");
        return;
      }
      const items = [...document.querySelectorAll("#manifest-items tbody tr")]
        .map((row) => ({
          product_id: row.querySelector('[name="item_product"]')?.value,
          quantity: row.querySelector('[name="item_quantity"]')?.value,
        }))
        .filter((item) => item.product_id && Number(item.quantity) >= 1);
      if (!items.length) {
        alert("Adicione ao menos um item com produto e quantidade.");
        return;
      }
      try {
        await store.updateManifest({
          romaneio_id: manifestEditForm.dataset.id,
          number: raw.number,
          scheduled_date: raw.scheduled_date,
          plate: raw.plate,
          expedidor: raw.expedidor,
          driver_name: raw.driver_name,
          items,
        });
        alert("Romaneio atualizado.");
        await navigate("manifest", `?id=${manifestEditForm.dataset.id}`);
      } catch (error) {
        alert(error.message);
      }
    });
  // Importação PDF: preenche o formulário manual com os campos extraídos.
  const pdfForm = document.querySelector("#pdf-form");
  if (pdfForm) {
    const pdfFile = pdfForm.querySelector(".file-input");
    const pdfFileName = pdfForm.querySelector("[data-file-name]");
    const pdfFeedback = document.querySelector("#pdf-import-feedback");
    const showPdfFeedback = (message, tone = "") => {
      if (!pdfFeedback) return;
      pdfFeedback.className = `import-feedback${tone ? ` is-${tone}` : ""}`;
      pdfFeedback.textContent = message;
    };
    pdfFile?.addEventListener("change", () => {
      pdfFileName.textContent = pdfFile.files?.[0]?.name || "Nenhum arquivo escolhido";
    });
    pdfForm.addEventListener("submit", async (event) => {
      event.preventDefault();
      const buttonNode = pdfForm.querySelector("button.button");
      if (buttonNode) buttonNode.disabled = true;
      showPdfFeedback("Lendo o PDF…");
      try {
        const data = await store.importPdf(new FormData(pdfForm));
        const fields = data.fields || {};
        const set = (name, value) => {
          const input = document.querySelector(
            `#new-manifest-form [name="${name}"]`,
          );
          if (input && value) input.value = value;
        };
        set("number", fields.codigo);
        set("scheduled_date", fields.data_iso);
        set("plate", fields.placa);
        set("expedidor", fields.expedidor);
        set("driver_name", fields.motorista);
        if (data.product && fields.quantidade_numero) {
          const tbody = document.querySelector("#manifest-items tbody");
          if (tbody) {
            tbody.innerHTML = "";
            tbody.insertAdjacentHTML(
              "beforeend",
              linhaItemRomaneio(store, data.product.id, fields.quantidade_numero),
            );
          }
        }
        const missing = data.missing || [];
        showPdfFeedback(
          `PDF importado.${missing.length ? ` Campos não encontrados: ${missing.join(", ")}.` : " Confira os dados e salve."}`,
          missing.length ? "warning" : "success",
        );
      } catch (error) {
        showPdfFeedback(error.message || "Não foi possível importar o PDF.", "error");
      } finally {
        if (buttonNode) buttonNode.disabled = false;
      }
    });
  }
  const csvForm = document.querySelector("#csv-form");
  if (csvForm) {
    const csvFile = csvForm.querySelector(".file-input");
    const csvFileName = csvForm.querySelector("[data-file-name]");
    const csvFeedback = document.querySelector("#csv-import-feedback");
    const showCsvFeedback = (message, tone = "") => {
      if (!csvFeedback) return;
      csvFeedback.className = `import-feedback${tone ? ` is-${tone}` : ""}`;
      csvFeedback.textContent = message;
    };
    csvFile?.addEventListener("change", () => {
      csvFileName.textContent = csvFile.files?.[0]?.name || "Nenhum arquivo escolhido";
    });
    csvForm.addEventListener("submit", async (event) => {
      event.preventDefault();
      const submit = csvForm.querySelector("button.button");
      if (submit) submit.disabled = true;
      showCsvFeedback("Validando o CSV…");
      try {
        const response = await fetch("/api/importar_csv.php", {
          method: "POST",
          headers: store.csrfToken ? { "X-CSRF-Token": store.csrfToken } : {},
          body: new FormData(csvForm),
        });
        const result = await response.json();
        if (!response.ok) throw new Error(result.error || "Falha ao importar CSV.");
        await store.loadManifests();
        showCsvFeedback(
          `${result.data.romaneios} romaneio(s), ${result.data.items} item(ns) consolidados em ${result.data.linhas} linha(s) importada(s).`,
          "success",
        );
      } catch (error) {
        showCsvFeedback(error.message || "Falha ao importar CSV.", "error");
      } finally {
        if (submit) submit.disabled = false;
      }
    });
  }
  const prepareLoadingForm = document.querySelector("#prepare-loading-form");
  if (prepareLoadingForm)
    prepareLoadingForm.addEventListener("submit", async (event) => {
      event.preventDefault();
      const submit = prepareLoadingForm.querySelector('button[type="submit"]');
      if (submit) submit.disabled = true;
      try {
        const raw = Object.fromEntries(new FormData(prepareLoadingForm));
        const result = await store.prepareLoading({
          romaneio_id: queryId(),
          truck_id: raw.truck_id,
          equipment_id: raw.equipment_id,
        });
        alert(
          `Operação #${result.id} preparada. Confirme as condições físicas antes de iniciar a esteira.`,
        );
        await navigate("work");
      } catch (error) {
        alert(error.message);
      } finally {
        if (submit) submit.disabled = false;
      }
    });
  document.querySelectorAll(".manual-reading-form").forEach((form) => {
    form.addEventListener("submit", async (event) => {
      event.preventDefault();
      try {
        const raw = Object.fromEntries(new FormData(form));
        const result = await store.identifyReading(form.dataset.readingId, raw.barcode);
        alert(`Leitura identificada como ${result.result}.`);
        render();
      } catch (error) {
        alert(error.message);
      }
    });
  });
  const userForm = document.querySelector("#user-create-form");
  if (userForm)
    userForm.addEventListener("submit", async (event) => {
      event.preventDefault();
      try {
        await store.createUser(Object.fromEntries(new FormData(userForm)));
        userForm.reset();
        await store.loadUsers();
        render();
        alert("Login criado.");
      } catch (error) {
        alert(error.message);
      }
    });
  const companyForm = document.querySelector("#company-create-form");
  if (companyForm)
    companyForm.addEventListener("submit", async (event) => {
      event.preventDefault();
      if (companyForm.dataset.submitting === "1") return;
      companyForm.dataset.submitting = "1";
      const submit = companyForm.querySelector('button[type="submit"]');
      if (submit) submit.disabled = true;
      try {
        await store.createCompany(
          Object.fromEntries(new FormData(companyForm)),
        );
        companyForm.reset();
        await store.loadCompanies();
        render();
        alert("Empresa criada. Agora crie o login de administrador.");
      } catch (error) {
        alert(error.message);
      } finally {
        companyForm.dataset.submitting = "0";
        if (submit) submit.disabled = false;
      }
    });
  // Filtros de romaneios: aplicam automaticamente ao alterar/confirmar.
  const manifestFilters = document.querySelector("#manifest-filters");
  if (manifestFilters) {
    const apply = async () => {
      try {
        await store.applyManifestFilters(
          Object.fromEntries(new FormData(manifestFilters)),
        );
        render();
      } catch (error) {
        alert(error.message);
      }
    };
    manifestFilters.addEventListener("submit", async (event) => {
      event.preventDefault();
      await apply();
    });
    manifestFilters.addEventListener("change", apply);
  }
  // Busca de produtos: filtra as linhas da tabela sem recarregar a tela.
  const productSearch = document.querySelector("#product-search");
  const usersCompanySelector = document.querySelector(
    "#users-company-selector",
  );
  if (usersCompanySelector)
    usersCompanySelector.addEventListener("change", async () => {
      store.state.selectedCompanyId = usersCompanySelector.value || null;
      await store.loadUsers();
      render();
    });
  const reportFilters = document.querySelector("#report-filters");
  if (reportFilters)
    reportFilters.addEventListener("submit", async (event) => {
      event.preventDefault();
      try {
        await store.applyReportFilters(
          Object.fromEntries(new FormData(reportFilters)),
        );
        render();
      } catch (error) {
        alert(error.message);
      }
    });
  if (productSearch)
    productSearch.addEventListener("input", () => {
      const term = productSearch.value.toLowerCase();
      store.state.productSearch = productSearch.value;
      document.querySelectorAll(".table-wrap tbody tr").forEach((row) => {
        row.style.display =
          !term || row.textContent.toLowerCase().includes(term) ? "" : "none";
      });
    });
}
async function loadPageData(page) {
  if (authenticatedUser.role === "ADMIN_DALLOGIX") {
    await store.loadCompanies();
    if (["master-home", "error-logs"].includes(page)) {
      await store.loadErrorLogs();
    }
    if (page === "company") {
      const id = queryId();
      if (!id) throw new Error("Empresa não informada.");
      store.selectCompany(id);
      await store.loadCompanyDetail();
    }
    if (page === "users") {
      store.state.selectedCompanyId =
        new URLSearchParams(window.location.search).get("company_id") || null;
      if (store.state.selectedCompanyId) await store.loadUsers();
    }
    if (page === "settings") await store.loadConfiguration();
    return;
  }
  const tasks = {
    dashboard: () => [
      store.loadDashboard(),
      store.loadMonitoring(),
      store.loadEquipments(),
    ],
    manifests: () => [store.loadManifests()],
    manifest: () => [store.loadManifest(queryId())],
    "manifest-edit": () => [store.loadManifest(queryId()), store.loadProducts()],
    import: () => [store.loadProducts()],
    division: () => [
      store.loadManifest(queryId()),
      store.loadEquipments(),
      store.loadActiveLoading(),
    ],
    work: () => [
      store.loadActiveLoading().then(() => store.loadPendingReadings()),
      store.loadMonitoring(),
      store.loadEquipments(),
      store.loadManifests(),
    ],
    occurrences: () => [store.loadMonitoring(), store.loadActiveLoading()],
    summary: () => [store.loadMonitoring(), store.loadActiveLoading()],
    history: () => [store.loadMonitoring(), store.loadReport()],
    products: () => [store.loadProducts()],
    alerts: () => [
      store.loadMonitoring(),
      store.loadEquipments(),
      store.loadSyncStatus(),
    ],
    emergency: () => [store.loadActiveLoading(), store.loadMonitoring()],
    settings: () => [store.loadConfiguration(), store.loadEquipments(), store.loadSyncStatus()],
    dalas: () => [store.loadEquipments()],
    dala: () => [loadDalaView(queryId())],
    "dala-edit": () => [store.loadEquipment(queryId())],
    "dala-actions": () => [store.loadEquipment(queryId()), store.loadDalaActionConfig(queryId())],
    "error-logs": () => [store.loadErrorLogs()],
    users: () => [store.loadUsers()],
  };
  if (
    ["manifest", "manifest-edit", "division", "dala", "dala-edit", "dala-actions"].includes(page) &&
    !queryId()
  )
    throw new Error("Registro não informado.");
  await Promise.all(tasks[page]?.() || []);
}

async function loadDalaView(id) {
  await store.loadEquipment(id);
  const results = await Promise.allSettled([
    store.loadMonitoring(),
    store.loadDalaCommandHistory(id),
  ]);
  results.forEach((result) => {
    if (result.status === "rejected") {
      console.warn("Consulta auxiliar da Dala indisponível:", result.reason);
    }
  });
}

async function renderPage() {
  const root = el("#screen-root");
  const requestId = ++renderRequestId;

  // A navegação não fica bloqueada pelas APIs. A tela abre com o estado local
  // disponível e recebe os dados atualizados assim que cada consulta termina.
  if (root) {
    try {
      render();
    } catch (error) {
      root.innerHTML =
        '<div class="panel page-loading" role="status">Abrindo tela…</div>';
    }
  }
  try {
    await loadPageData(currentPage);
    if (requestId !== renderRequestId) return;
    render();
  } catch (error) {
    if (requestId !== renderRequestId) return;
    console.error(error);
    if (["manifest", "manifest-edit", "dala-edit", "company"].includes(currentPage)) {
      await navigate(
        ["manifest", "manifest-edit"].includes(currentPage)
          ? "manifests"
          : currentPage === "company"
            ? "companies"
            : "dalas",
        "",
        { replace: true },
      );
      return;
    }
    if (root)
      root.innerHTML = `<div class="panel page-error"><h2>Não foi possível atualizar esta tela</h2><p>${esc(error.message || "Verifique a conexão local e tente novamente.")}</p><button class="button primary" data-action="reload-page" type="button">Tentar novamente</button></div>`;
    bindActions();
  }
}

async function bootstrap() {
  installInteractionGuards();
  installOfflineShell();
  currentPage = initialPage || pageFromPath();
  if (currentPage === "login") {
    if (window.location.search)
      window.history.replaceState(null, "", window.location.pathname);
    let loginMessage = "";
    try {
      loginMessage = sessionStorage.getItem("trace-login-message") || "";
      sessionStorage.removeItem("trace-login-message");
    } catch (storageError) {
      /* armazenamento indisponível */
    }
    renderLogin(loginMessage);
    bindLoginForm();
    return;
  }
  if (!screens[currentPage]) {
    window.location.replace("index.html");
    return;
  }
  const response = await fetch("/api/me.php");
  if (!response.ok) {
    window.location.replace("index.html");
    return;
  }
  const result = await response.json();
  authenticatedUser = result.user;
  store.setUser(authenticatedUser);
  store.setCsrfToken(result.csrf_token);
  const requestedPage = resolveAuthorizedPage(currentPage, defaultPage());
  if (requestedPage !== currentPage) {
    currentPage = requestedPage;
    window.location.replace(pagePath(currentPage));
    return;
  }
  await renderPage();
  window.addEventListener("popstate", async () => {
    const page = pageFromPath();
    if (!screens[page] || !isAllowedPage(page)) {
      window.location.replace(pagePath(defaultPage()));
      return;
    }
    currentPage = page;
    await renderPage();
  });
}
bootstrap();
