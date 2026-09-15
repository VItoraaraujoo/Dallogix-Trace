import { button, esc } from "../funcoes/html.js";
import { numero, relativo } from "../funcoes/formato.js";
import { rotuloEstado } from "../funcoes/rotulos.js";
import { deviceBadge, pageHeader } from "../funcoes/view.js?v=202609150020";

function dalaAlbumCard(equipment, machines, role) {
  const machine =
    machines.find((item) => Number(item.id) === Number(equipment.id)) || {};
  const status = machine.clp_status || "DESCONHECIDO";
  const lastSignal = relativo(machine.last_seen_at);
  const loadingId = machine.carregamento_id || "";
  const state = String(machine.carregamento_state || "AGUARDANDO").toUpperCase();
  const planned = Number(machine.planned_quantity || 0);
  const loaded = Number(machine.valid_readings || 0);
  const percentage =
    planned > 0 ? Math.min(100, Math.round((loaded / planned) * 100)) : 0;
  return `<article class="dala-album-card">
    <header>
      <div>
        <span class="album-label">Dala</span>
        <h3>${esc(equipment.name)}</h3>
        <code>${esc(equipment.equipment_code)}</code>
      </div>
      ${deviceBadge(status)}
    </header>
    <div class="dala-album-info dala-romaneio-info">
      <div><small>Romaneio</small><strong>${machine.romaneio_number ? `#${esc(machine.romaneio_number)}` : "Sem romaneio"}</strong></div>
      <div><small>Caminhão</small><strong>${esc(machine.plate || "—")}</strong></div>
      <div><small>Estado</small><strong>${esc(rotuloEstado(state))}</strong></div>
      <div><small>Carregado</small><strong>${numero(loaded)} / ${numero(planned)}</strong></div>
    </div>
    <div class="album-progress"><div class="progress"><i style="width:${percentage}%"></i></div><small>${percentage}% do romaneio</small></div>
    <footer>
      <span><i class="status-dot ${String(status).toUpperCase() === "ONLINE" ? "online" : "offline"}"></i>${esc(lastSignal)}</span>
      <div class="dashboard-card-links"><button class="button secondary small" data-action="view-dala" data-id="${equipment.id}" type="button">Visualizar Dala e estatísticas</button>${machine.romaneio_id ? `<button class="button secondary small" data-action="view-manifest" data-id="${machine.romaneio_id}" type="button">Romaneio</button>` : ""}</div>
    </footer>
  </article>`;
}

// Dashboard no padrão da referência TracePlatform: somente informações das Dalas.
export function dashboard(store) {
  const equipments = store.state.equipments || [];
  const machines = store.state.monitoring?.maquinas || [];
  const canManageDalas = store.state.userRole === "ADMIN_EMPRESA";
  return `${pageHeader("Esteiras e romaneios", "Operação por Dala", "Estado e progresso de cada operação em andamento.", canManageDalas ? button("Gerenciar Dalas", "goto-dalas", "secondary") : "")}
  <section class="dashboard-dalas">
    <div class="dala-album-grid">${equipments.length ? equipments.map((equipment) => dalaAlbumCard(equipment, machines, store.state.userRole)).join("") : '<div class="panel placeholder-panel"><p>Nenhuma Dala cadastrada para esta empresa.</p></div>'}</div>
  </section>`;
}
