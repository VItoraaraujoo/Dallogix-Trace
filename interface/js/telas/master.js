import { esc } from "../funcoes/html.js";
import { dataHora, numero } from "../funcoes/formato.js";
import { companyGrid, pageHeader } from "../funcoes/view.js?v=202609150200";

export function masterHome(store) {
  const companies = Array.isArray(store.state.companies) ? store.state.companies : [];
  const logs = Array.isArray(store.state.errorLogs) ? store.state.errorLogs : [];
  const totalMachines = companies.reduce(
    (total, company) => total + Number(company.total_machines || 0),
    0,
  );
  const onlineMachines = companies.reduce(
    (total, company) => total + Number(company.machines_online || 0),
    0,
  );
  const blockedLicenses = companies.filter(
    (company) => String(company.license_status || "SEM_LICENCA") !== "ATIVA",
  ).length;
  const affectedCompanies = companies.filter((company) => {
    const total = Number(company.total_machines || 0);
    const online = Number(company.machines_online || 0);
    return total > online || String(company.license_status || "SEM_LICENCA") !== "ATIVA";
  });
  const visibleCompanies = affectedCompanies.length ? affectedCompanies : companies;
  return `<div class="master-home">${pageHeader(
    "Dallogix / administração global",
    "Central Master",
    "Acompanhe as empresas e priorize pendências sem acessar a operação local.",
  )}
  <section class="grid four dashboard-metrics master-metrics" aria-label="Resumo das empresas">
    <div class="panel metric"><small>Empresas cadastradas</small><strong>${numero(companies.length)}</strong></div>
    <div class="panel metric"><small>Máquinas conectadas</small><strong class="metric-green">${numero(onlineMachines)} / ${numero(totalMachines)}</strong></div>
    <div class="panel metric"><small>Empresas em atenção</small><strong class="${affectedCompanies.length ? "metric-red" : "metric-green"}">${numero(affectedCompanies.length)}</strong></div>
    <div class="panel metric"><small>Licenças bloqueadas</small><strong class="${blockedLicenses ? "metric-red" : "metric-green"}">${numero(blockedLicenses)}</strong></div>
  </section>
  <section class="master-section-heading"><div><span class="kicker">Empresas</span><h3>${affectedCompanies.length ? "Empresas que precisam de verificação" : "Empresas cadastradas"}</h3><p>${affectedCompanies.length ? "Máquina sem sinal ou licença bloqueada: estas empresas aparecem primeiro." : "Abra uma empresa para gerenciar máquinas, usuários e licenças."}</p></div></section>
  ${companyGrid(visibleCompanies, "Nenhuma empresa cadastrada.", { compact: true })}
  <section class="master-section-heading master-log-heading"><div><span class="kicker">Diagnóstico</span><h3>Últimos erros registrados</h3><p>O histórico completo fica disponível em Logs de erros, no menu lateral.</p></div></section>
  <section class="panel reference-table-panel master-log-preview"><div class="table-wrap"><table><thead><tr><th>Data</th><th>Origem</th><th>Mensagem</th><th>Empresa</th></tr></thead><tbody>${logs.length ? logs.slice(0, 5).map((item) => `<tr><td>${esc(dataHora(item.criado_em))}</td><td><code>${esc(item.origem || "Sistema")}</code></td><td class="error-message-cell">${esc(item.mensagem || "—")}</td><td>${esc(item.empresa || "Sistema")}</td></tr>`).join("") : '<tr><td colspan="4" class="empty-cell">Nenhum erro inesperado registrado.</td></tr>'}</tbody></table></div></section></div>`;
}
