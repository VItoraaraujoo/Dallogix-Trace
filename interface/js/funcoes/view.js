import { button, esc } from "./html.js";
import { data, numero, relativo } from "./formato.js";
import { rotuloEstado, rotuloStatusRomaneio } from "./rotulos.js";

export function pageHeader(kicker, title, description, action = "") {
  return `<div class="title-row"><div><span class="kicker">${esc(kicker)}</span><h2>${esc(title)}</h2><p>${esc(description)}</p></div>${action}</div>`;
}
export function statuses(store = null) {
  const selectedEquipmentId = Number(store?.state?.equipmentId) || null;
  const devices = (store?.state?.monitoring?.dispositivos || []).filter(
    (item) =>
      !selectedEquipmentId || Number(item.equipment_id) === selectedEquipmentId,
  );
  const known = ["SENSOR", "SCANNER", "CLP", "CAMERA", "SERVER"];
  const labels = {
    SENSOR: "Sensor",
    SCANNER: "Scanner",
    CLP: "CLP",
    CAMERA: "Câmera",
    SERVER: "Servidor",
  };
  const status = (type) =>
    devices.find((item) => item.device_type === type)?.status ||
    (type === "SERVER" ? "LOCAL" : "NÃO REGISTRADO");
  const tone = (value) => (["ONLINE", "LOCAL"].includes(value) ? "OK" : value);
  return `<div class="status">${known.map((type) => `<span>● ${labels[type]} <b data-live-status="${type}">${esc(tone(status(type)))}</b></span>`).join("")}</div>`;
}
export function progress(store) {
  const loaded = Number(store.state.loaded) || 0;
  const planned = Number(store.state.planned) || 0;
  const pct =
    planned > 0 ? Math.min(100, Math.round((loaded / planned) * 100)) : 0;
  return `<div class="progress"><i data-live="progress-bar" style="width:${pct}%"></i></div><div class="progress-label"><span data-live="progress-percent">${pct}% concluído</span><span data-live="progress-count">${numero(loaded)} / ${numero(planned)} sacas</span></div>`;
}
export function badge(status) {
  const label = rotuloStatusRomaneio(status);
  const tone = ["FINALIZADO", "Finalizado"].includes(status)
    ? "green"
    : ["CANCELADO", "Cancelado"].includes(status)
      ? "red"
      : "yellow";
  return `<span class="badge ${tone}">${label}</span>`;
}

// Badge de status no padrão da referência TracePlatform.
export function manifestStatusBadge(row) {
  if (row.status === "EM_ANDAMENTO") {
    if (String(row.active_state || "").toUpperCase() === "PAUSADO")
      return '<span class="badge info">Aguardando continuação</span>';
    return `<span class="badge blue">Em andamento${row.active_equipment ? " - " + esc(row.active_equipment) : ""}</span>`;
  }
  if (row.status === "FINALIZADO") {
    return Number(row.has_divergence)
      ? '<span class="badge orange">Finalizado c/ divergência</span>'
      : '<span class="badge green">Finalizado</span>';
  }
  if (row.status === "CANCELADO")
    return '<span class="badge red">Cancelado</span>';
  return badge(row.status);
}

export function manifestsTable(rows, userRole = "") {
  const list = Array.isArray(rows) ? rows : [];
  const body = list.length
    ? list
        .map((r) => {
          const operating = r.status === "EM_ANDAMENTO";
          const canManage = ["ADMIN_EMPRESA", "SUPERVISOR"].includes(userRole);
          const canEdit = canManage && ["IMPORTADO", "AGUARDANDO"].includes(r.status);
          const canCancel = canManage && ["IMPORTADO", "AGUARDANDO"].includes(r.status);
          const viewAction = button("Visualizar", "view-manifest", "secondary", `data-id="${r.id}"`);
          const operation = r.status === "AGUARDANDO"
            ? button("Iniciar", "prepare-manifest", "primary", `data-id="${r.id}"`)
            : operating
              ? button(r.active_state === "PAUSADO" ? "Retomar" : "Abrir operação", "resume-loading", "primary", `data-id="${r.id}" data-loading-id="${r.active_loading_id || ""}"`)
              : viewAction;
          const extraActions = [
            operation === viewAction ? "" : viewAction,
            canEdit ? `<button class="button secondary small" data-action="edit-manifest" data-id="${r.id}" type="button">Editar</button>` : "",
            canCancel ? `<button class="button danger small" data-action="cancel-manifest" data-id="${r.id}" type="button">Cancelar</button>` : "",
          ].join("");
          const actions = `<div class="manifest-row-actions">${operation}${extraActions ? `<details class="table-action-menu"><summary class="button secondary small">Mais ações</summary><div class="table-action-menu-list">${extraActions}</div></details>` : ""}</div>`;
          return `<tr>
          <td data-label="Data do carregamento">${data(r.scheduled_date)}</td>
          <td data-label="Código do romaneio"><strong>${esc(r.number)}</strong></td>
          <td data-label="Expedidor">${esc(r.expedidor || "—")}</td>
          <td data-label="Status">${manifestStatusBadge(r)}</td>
          <td data-label="Ações">${actions}</td>
        </tr>`;
        })
        .join("")
    : '<tr><td colspan="5" class="empty-cell">Nenhum romaneio encontrado.</td></tr>';
  return `<div class="panel table-wrap"><table class="manifests-table"><thead><tr><th>Data do carregamento</th><th>Código do romaneio</th><th>Expedidor</th><th>Status</th><th>Ações</th></tr></thead><tbody>${body}</tbody></table></div>`;
}

export function deviceBadge(value) {
  const normalized = String(value || "DESCONHECIDO").toUpperCase();
  const labels = {
    ONLINE: "Online",
    OFFLINE: "Offline",
    ERRO: "Erro",
    DESCONHECIDO: "Sem sinal",
    LOCAL: "Local",
  };
  const tone =
    normalized === "ONLINE" || normalized === "LOCAL"
      ? "green"
      : normalized === "ERRO"
        ? "red"
        : "yellow";
  return `<span class="badge ${tone}">${esc(labels[normalized] || normalized)}</span>`;
}

function machineCard(machine) {
  const loaded = Number(machine.valid_readings || 0);
  const planned = Number(machine.planned_quantity || 0);
  const pct =
    planned > 0 ? Math.min(100, Math.round((loaded / planned) * 100)) : 0;
  const state = rotuloEstado(machine.carregamento_state, "Ociosa");
  return `<article class="machine-card ${String(machine.clp_status || "").toUpperCase() === "ONLINE" ? "" : "is-offline"}">
    <header><div><strong>${esc(machine.name)}</strong><small>${esc(machine.equipment_code)}</small></div>${deviceBadge(machine.clp_status)}</header>
    <dl>
      <div><dt>Romaneio</dt><dd>${machine.romaneio_number ? "#" + esc(machine.romaneio_number) : "—"}</dd></div>
      <div><dt>Caminhão</dt><dd>${machine.plate ? esc(machine.plate) : "—"}</dd></div>
      <div><dt>Estado</dt><dd>${esc(state)}</dd></div>
      <div><dt>Último sinal</dt><dd>${esc(relativo(machine.last_seen_at))}</dd></div>
    </dl>
    <div class="progress"><i style="width:${pct}%"></i></div>
    <div class="progress-label"><span>${pct}% concluído</span><span>${numero(loaded)} / ${numero(planned)} sacas</span></div>
  </article>`;
}

export function machineGrid(
  machines,
  emptyMessage = "Nenhuma máquina cadastrada.",
) {
  const list = Array.isArray(machines) ? machines : [];
  return `<div class="card-grid machine-grid">${list.length ? list.map(machineCard).join("") : `<p class="empty-cell">${emptyMessage}</p>`}</div>`;
}

export function companyCard(company, { compact = false } = {}) {
  const total = Number(company.total_machines || 0);
  const online = Number(company.machines_online || 0);
  const offline = Number(
    company.machines_offline ?? Math.max(0, total - online),
  );
  const users = Number(company.total_users || 0);
  const activeUsers = Number(company.active_users || 0);
  const occurrences = Number(company.ocorrencias_24h || 0);
  const connection =
    online > 0
      ? '<span class="badge green">Online</span>'
      : '<span class="badge red">Sem conexão</span>';
  const licenseStatus = String(company.license_status || "SEM_LICENCA");
  const licenseLabel = {
    ATIVA: "Licença ativa",
    BLOQUEADA: "Bloqueada",
    SEM_LICENCA: "Sem licença",
  }[licenseStatus] || licenseStatus;
  const licenseTone = licenseStatus === "ATIVA" ? "green" : "red";
  const licenseAction = licenseStatus === "ATIVA" ? "Bloquear licença" : "Ativar licença";
  return `<article class="company-card">
    <header><div><strong>${esc(company.name)}</strong><small>${total} máquina(s) • último sinal ${company.last_signal_at ? esc(company.last_signal_at) : "—"}</small></div><div>${connection}<span class="badge ${licenseTone}">${esc(licenseLabel)}</span></div></header>
    <div class="company-card-metrics">
      <div><b>${total}</b><small>Máquinas</small></div>
      <div><b class="metric-green">${online}</b><small>Online</small></div>
      <div><b>${activeUsers}/${users}</b><small>Acessos ativos</small></div>
    </div>
    <footer><span class="kicker">${offline > 0 ? `${offline} máquina(s) sem sinal · ` : ""}${occurrences} ocorrência(s) em 24h</span><div class="actions"><button class="button secondary" data-action="open-company" data-id="${company.id}" type="button">Gerenciar</button>${compact ? "" : `<button class="button ${licenseStatus === "ATIVA" ? "danger" : "primary"} small" data-action="toggle-license" data-id="${company.id}" data-status="${esc(licenseStatus)}" type="button">${licenseAction}</button><button class="button ghost danger-link small" data-action="delete-company" data-id="${company.id}" data-name="${esc(company.name)}" type="button">Remover</button>`}</div></footer>
  </article>`;
}

export function companyGrid(
  companies,
  emptyMessage = "Nenhuma empresa cadastrada.",
  options = {},
) {
  const list = Array.isArray(companies) ? companies : [];
  return `<div class="card-grid company-grid">${list.length ? list.map((company) => companyCard(company, options)).join("") : `<p class="empty-cell">${emptyMessage}</p>`}</div>`;
}
