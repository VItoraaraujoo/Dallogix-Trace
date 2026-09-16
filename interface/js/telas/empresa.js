import { button, esc } from "../funcoes/html.js";
import { pageHeader, machineGrid, deviceBadge } from "../funcoes/view.js?v=202609150020";

export function company(store) {
  const detail = store.state.companyDetail;
  if (!detail) {
    return `<div class="title-row has-back"><button class="button secondary page-back" data-action="back-companies" type="button">← Voltar</button><div><span class="kicker">Dallogix / gerenciamento</span><h2>Empresa</h2><p>Selecione uma empresa na visão geral.</p></div></div><section class="panel"><p class="empty-cell">Nenhuma empresa selecionada.</p></section>`;
  }
  const machines = detail.maquinas || [];
  const total = machines.length;
  const online = machines.filter(
    (machine) => String(machine.clp_status || "").toUpperCase() === "ONLINE",
  ).length;
  const offline = total - online;
  const occurrences = detail.ocorrencias_recentes || [];
  const romaneios = detail.romaneios || {};
  const openRomaneios =
    (romaneios.AGUARDANDO || 0) + (romaneios.EM_ANDAMENTO || 0);
  return `<div class="title-row with-actions has-back"><button class="button secondary page-back" data-action="back-companies" type="button">← Voltar</button><div><span class="kicker">Dallogix / gerenciamento</span><h2>${esc(detail.name)}</h2><p>Dashboard operacional da empresa: máquinas, carregamentos e ocorrências.</p></div><div class="actions">${button("Logins", "open-users", "secondary", `data-company-id="${detail.id}"`)}</div></div>
    <div class="grid four dashboard-metrics">
      <div class="panel metric"><small>Máquinas</small><strong>${total}</strong></div>
      <div class="panel metric"><small>Online</small><strong class="metric-green">${online}</strong></div>
      <div class="panel metric"><small>Sem sinal</small><strong class="${offline > 0 ? "metric-red" : ""}">${offline}</strong></div>
      <div class="panel metric"><small>Romaneios abertos</small><strong>${openRomaneios}</strong></div>
    </div><br>
    ${machineGrid(machines, "Nenhuma máquina cadastrada para esta empresa.")}<br>
    <section class="panel"><h3>Periféricos reportados</h3>${machines.length ? `<div class="table-wrap"><table><thead><tr><th>Máquina</th><th>Identificador</th><th>CLP</th><th>Último sinal</th></tr></thead><tbody>${machines.map((machine) => `<tr><td><strong>${esc(machine.name)}</strong></td><td>${esc(machine.equipment_code)}</td><td>${deviceBadge(machine.clp_status)}</td><td>${machine.last_seen_at ? esc(machine.last_seen_at) : "—"}</td></tr>`).join("")}</tbody></table></div>` : '<p class="empty-cell">Sem dispositivos registrados.</p>'}</section><br>
    <section class="panel"><h3>Ocorrências recentes</h3><div class="table-wrap"><table><thead><tr><th>Tipo</th><th>Quantidade</th><th>Descrição</th><th>Data</th></tr></thead><tbody>${occurrences.length ? occurrences.map((occurrence) => `<tr><td><strong>${esc(occurrence.type)}</strong></td><td>${Number(occurrence.quantity)}</td><td>${occurrence.description ? esc(occurrence.description) : "—"}</td><td>${esc(occurrence.created_at)}</td></tr>`).join("") : '<tr><td colspan="4" class="empty-cell">Nenhuma ocorrência registrada.</td></tr>'}</tbody></table></div></section>`;
}
