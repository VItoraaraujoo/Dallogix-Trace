import { button, esc } from "../funcoes/html.js";
import { data, numero } from "../funcoes/formato.js";
import { rotuloEstado, rotuloStatusComando } from "../funcoes/rotulos.js";
import {
  pageHeader,
  manifestsTable,
  progress,
  statuses,
} from "../funcoes/view.js?v=202609150200";

const STATUS_OPTIONS = [
  ["", "Todos os status"],
  ["IMPORTADO", "Importado"],
  ["AGUARDANDO", "Aguardando"],
  ["EM_ANDAMENTO", "Em andamento"],
  ["FINALIZADO", "Finalizado"],
  ["CANCELADO", "Cancelado"],
];

function equipmentLabel(store) {
  return store.state.equipmentCode && store.state.equipmentCode !== "—"
    ? store.state.equipmentCode
    : store.state.equipments?.[0]?.equipment_code || "Esteira";
}

export function manifests(store) {
  const filters = store.state.manifestFilters || {};
  const options = STATUS_OPTIONS.map(
    ([value, label]) =>
      `<option value="${value}"${filters.status === value ? " selected" : ""}>${label}</option>`,
  ).join("");
  const canCreate = ["ADMIN_EMPRESA", "SUPERVISOR"].includes(store.state.userRole);
  return `${pageHeader("Operação", "Romaneios", "Consulte os carregamentos e abra uma operação.", canCreate ? button("Novo romaneio", "new-manifest") : "")}
<section class="panel filters"><form id="manifest-filters"><div class="filter-grid">
<label>Data inicial<input name="date_from" type="date" value="${esc(filters.date_from || "")}" /></label>
<label>Data final<input name="date_to" type="date" value="${esc(filters.date_to || "")}" /></label>
<label>Código do romaneio<input name="number" value="${esc(filters.number || "")}" /></label>
<label>Expedidor<input name="expedidor" value="${esc(filters.expedidor || "")}" /></label>
<label>Status<select name="status">${options}</select></label>
</div></form></section><br>${manifestsTable(store.manifests, store.state.userRole)}`;
}

// Detalhe do romaneio (tela Visualizar).
export function manifestView(store) {
  const manifest = store.state.manifestDetail;
  if (!manifest)
    return `${pageHeader("Operação / romaneios", "Romaneio", "Carregando…")}`;
  const items = manifest.items || [];
  const canPrepare =
    ["ADMIN_EMPRESA", "SUPERVISOR"].includes(store.state.userRole) &&
    !["FINALIZADO", "CANCELADO"].includes(manifest.status);
  const auditReport =
    ["FINALIZADO", "CANCELADO"].includes(manifest.status)
      ? `<a class="button secondary" href="/api/relatorio_auditoria.php?romaneio_id=${encodeURIComponent(manifest.id)}">Baixar relatório de auditoria (PDF)</a>`
      : "";
  const canCancel = ["ADMIN_EMPRESA", "SUPERVISOR"].includes(store.state.userRole) && ["IMPORTADO", "AGUARDANDO"].includes(manifest.status);
  const canCancelInProgress = ["ADMIN_EMPRESA", "SUPERVISOR"].includes(store.state.userRole) && manifest.status === "EM_ANDAMENTO" && manifest.active_loading_id;
  return `<div class="title-row with-actions has-back"><button class="button secondary page-back" data-action="goto-manifests" type="button">← Voltar</button><div><h2>Romaneio ${esc(manifest.number)}</h2></div><div class="actions">${auditReport}${canPrepare ? `<button class="button primary" data-action="prepare-manifest" data-id="${manifest.id}" type="button">Preparar carregamento</button>` : ""}${canCancelInProgress ? `<button class="button danger" data-action="cancel-manifest-progress" data-id="${manifest.id}" type="button">Cancelar operação</button>` : ""}</div></div>
<div class="grid two detail-cards">
<div class="panel detail-card"><small>Data do carregamento</small><strong>${data(manifest.scheduled_date)}</strong></div>
<div class="panel detail-card"><small>Expedidor</small><strong>${esc(manifest.expedidor || "—")}</strong></div>
<div class="panel detail-card"><small>Placa do caminhão</small><strong>${esc(manifest.plate || "—")}</strong></div>
<div class="panel detail-card"><small>Motorista</small><strong>${esc(manifest.driver_name || "—")}</strong></div>
<div class="panel detail-card"><small>Programado</small><strong>${numero(manifest.planned_quantity)}</strong></div>
<div class="panel detail-card"><small>Carregado</small><strong>${numero(manifest.loaded_quantity)}</strong></div>
</div><br>
<section class="panel"><h3>Itens do Romaneio</h3><div class="table-wrap"><table><thead><tr><th>Produto</th><th>Código</th><th>Quantidade</th></tr></thead><tbody>
${items.length ? items.map((item) => `<tr><td><strong>${esc(item.name)}</strong></td><td><code>${esc(item.code || "—")}</code></td><td>${numero(item.planned_quantity)}</td></tr>`).join("") : '<tr><td colspan="3" class="empty-cell">Nenhum item cadastrado.</td></tr>'}
</tbody></table></div></section>${canCancel ? `<section class="panel"><div class="actions"><div><strong>Cancelamento do romaneio</strong><p>Use somente se o carregamento ainda não tiver começado.</p></div>${button("Cancelar romaneio", "cancel-manifest", "danger")}</div></section>` : ""}`;
}

const itemRow = (products, selectedId = "", quantity = 1) => `<tr class="manifest-item">
  <td><select name="item_product"><option value="">Selecionar produto…</option>${products.map((product) => `<option value="${product.id}"${String(product.id) === String(selectedId) ? " selected" : ""}>${esc(product.name)}${product.code ? ` (${esc(product.code)})` : ""}</option>`).join("")}</select></td>
  <td><input name="item_quantity" type="number" min="1" value="${Number(quantity) || 1}" /></td>
  <td>${button("Remover", "remove-item", "ghost")}</td>
</tr>`;

function industrialPcDate() {
  const now = new Date();
  const month = String(now.getMonth() + 1).padStart(2, "0");
  const day = String(now.getDate()).padStart(2, "0");
  return `${now.getFullYear()}-${month}-${day}`;
}

// Tela "Novo romaneio": importação por PDF que preenche os campos + cadastro manual com itens.
export function importScreen(store) {
  const products = store.state.products || [];
  const today = industrialPcDate();
  return `<div class="title-row has-back"><button class="button secondary page-back" data-action="goto-manifests" type="button">← Voltar</button><div><h2>Novo romaneio</h2></div></div>
<section class="panel pdf-import-card"><p>Selecione o arquivo PDF do romaneio para preencher os campos automaticamente. Confira os dados e salve.</p>
<form id="pdf-form"><div class="file-picker"><input id="pdf-file" class="file-input" name="file" type="file" accept=".pdf,application/pdf" required /><label class="button primary file-picker-button" for="pdf-file">Escolher arquivo</label><span class="file-name" data-file-name>Nenhum arquivo escolhido</span></div><div class="actions"><button class="button primary" data-action="import-pdf" type="submit">Importar PDF</button></div><div id="pdf-import-feedback" class="import-feedback" role="status" aria-live="polite"></div></form></section><br>
<div class="divider"><span>ou cadastre manualmente</span></div>
<p>Preencha os dados do romaneio e adicione os itens com produto e quantidade.</p>
<form id="new-manifest-form">
<section class="panel"><div class="grid three">
<label>Código<input name="number" required /></label>
<label>Data do Carregamento<input name="scheduled_date" type="date" min="${today}" value="${today}" required /></label>
<label>Placa do caminhão<input name="plate" required /></label>
<label>Expedidor<input name="expedidor" /></label>
<label>Motorista<input name="driver_name" /></label>
</div></section><br>
<section class="panel"><div class="panel-heading"><h3>Itens do Romaneio</h3>${button("Adicionar item", "add-item", "secondary")}</div>
<div class="table-wrap"><table id="manifest-items"><thead><tr><th>Produto</th><th>Quantidade</th><th></th></tr></thead><tbody>${itemRow(products)}</tbody></table></div>
</section><br>${button("Cadastrar", "submit-manifest")}
</form>
<details class="panel csv-legacy"><summary>Importar romaneios por CSV</summary><p>Use o modelo separado por ponto e vírgula. Campos obrigatórios: <b>romaneio, data, placa, produto e quantidade</b>. Motorista e expedidor são opcionais. A data pode ser <b>DD/MM/AAAA</b> ou <b>AAAA-MM-DD</b>.</p><p><a class="text-link" href="assets/modelo-romaneio.csv" download>Baixar modelo CSV</a></p><form id="csv-form"><div class="file-picker"><input id="csv-file" class="file-input" name="file" type="file" accept=".csv,text/csv" required /><label class="button primary file-picker-button" for="csv-file">Escolher arquivo</label><span class="file-name" data-file-name>Nenhum arquivo escolhido</span></div><small>Máximo: 5 MB ou 10.000 linhas. Linhas repetidas do mesmo produto são somadas automaticamente.</small><div class="actions">${button("Importar e validar", "import-csv")}</div><div id="csv-import-feedback" class="import-feedback" role="status" aria-live="polite"></div></form></details>`;
}

export function manifestEdit(store) {
  const manifest = store.state.manifestDetail;
  if (!manifest) return `${pageHeader("Operação / romaneios", "Editar romaneio", "Carregando…")}`;
  const editable = ["IMPORTADO", "AGUARDANDO"].includes(manifest.status);
  if (!editable) return `${pageHeader("Operação / romaneios", "Romaneio não editável", "Este romaneio já foi iniciado ou encerrado e não pode mais ser alterado.", `<div class="actions">${button("Voltar", "goto-manifests", "secondary")}${button("Visualizar romaneio", "view-manifest", "primary", `data-id="${manifest.id}"`)}</div>`)}`;
  const today = industrialPcDate();
  const rows = (manifest.items || []).map((item) => itemRow(store.state.products || [], item.product_id, item.planned_quantity)).join("") || itemRow(store.state.products || []);
  return `<div class="title-row has-back"><button class="button secondary page-back" data-action="goto-manifests" type="button">← Voltar</button><div><h2>Editar romaneio ${esc(manifest.number)}</h2><p>Alterações permitidas somente antes do início do carregamento.</p></div></div>
  <form id="edit-manifest-form" data-id="${manifest.id}"><section class="panel"><div class="grid three">
  <label>Código<input name="number" required value="${esc(manifest.number)}" /></label>
  <label>Data do carregamento<input name="scheduled_date" type="date" min="${today}" value="${esc(manifest.scheduled_date)}" required /></label>
  <label>Placa do caminhão<input name="plate" required value="${esc(manifest.plate || "")}" /></label>
  <label>Expedidor<input name="expedidor" value="${esc(manifest.expedidor || "")}" /></label>
  <label>Motorista<input name="driver_name" value="${esc(manifest.driver_name || "")}" /></label>
  </div></section><br><section class="panel"><div class="panel-heading"><h3>Itens do romaneio</h3>${button("Adicionar item", "add-item", "secondary")}</div><div class="table-wrap"><table id="manifest-items"><thead><tr><th>Produto</th><th>Quantidade</th><th></th></tr></thead><tbody>${rows}</tbody></table></div></section><br><div class="actions"><button class="button secondary" data-action="goto-manifests" type="button">Cancelar</button>${button("Salvar alterações", "submit-manifest-edit")}</div></form>`;
}
export function division(store) {
  const manifest = store.state.manifestDetail;
  if (!manifest)
    return `${pageHeader("Operação / planejamento", "Preparar carregamento", "Carregando romaneio…")}`;
  const trucks = manifest.trucks || [];
  const equipments = store.state.equipments || [];
  const occupiedEquipmentIds = new Set(
    (store.state.activeLoadings || []).map((loading) =>
      Number(loading.equipment_id),
    ),
  );
  const total = (manifest.items || []).reduce(
    (sum, item) => sum + (Number(item.planned_quantity) || 0),
    0,
  );
  const truckOptions = trucks
    .map(
      (truck) =>
        `<option value="${truck.id}">${esc(truck.plate)}${truck.driver_name ? ` • ${esc(truck.driver_name)}` : ""}</option>`,
    )
    .join("");
  const equipmentOptions = equipments
    .map(
      (equipment) => {
        const occupied = occupiedEquipmentIds.has(Number(equipment.id));
        return `<option value="${equipment.id}"${occupied ? " disabled" : ""}>${esc(equipment.name)} • ${esc(equipment.equipment_code)}${occupied ? " — ocupada" : ""}</option>`;
      },
    )
    .join("");
  const availableEquipment = equipments.some(
    (equipment) => !occupiedEquipmentIds.has(Number(equipment.id)),
  );
  return `${pageHeader("Operação / planejamento", "Preparar carregamento", "Defina o caminhão e a Dala antes de liberar a operação.")}
  <section class="panel compact-help"><strong>Regra de segurança</strong><p>Uma Dala e um caminhão não podem ter dois carregamentos ativos. A confirmação abaixo apenas prepara a operação no banco local; o motor continua sob intertravamento do CLP.</p></section><br>
  <div class="grid three"><div class="panel metric"><small>Romaneio</small><strong>${esc(manifest.number)}</strong></div><div class="panel metric"><small>Total programado</small><strong>${numero(total)}</strong></div><div class="panel metric"><small>Caminhões disponíveis</small><strong>${numero(trucks.length)}</strong></div></div><br>
  <form id="prepare-loading-form" class="panel"><div class="grid two"><label>Caminhão<select name="truck_id" required ${truckOptions ? "" : "disabled"}>${truckOptions || "<option>Nenhum caminhão cadastrado</option>"}</select></label><label>Dala<select name="equipment_id" required ${availableEquipment ? "" : "disabled"}>${equipmentOptions || "<option>Nenhuma Dala cadastrada</option>"}</select></label></div>${availableEquipment ? "" : '<div class="alert-box" role="alert">Todas as Dalas possuem carregamentos ativos. Finalize ou libere uma operação antes de preparar outra.</div>'}<div class="actions"><button class="button secondary" data-action="back-manifest" type="button">Cancelar</button>${button("Preparar operação", "prepare-loading", "primary", availableEquipment && truckOptions ? "" : "disabled")}</div></form>
  <section class="panel"><h3>Produtos previstos</h3><div class="table-wrap"><table><thead><tr><th>Produto</th><th>Quantidade</th></tr></thead><tbody>${(manifest.items || []).map((item) => `<tr><td>${esc(item.name)}</td><td>${numero(item.planned_quantity)}</td></tr>`).join("") || '<tr><td colspan="2">Nenhum item informado.</td></tr>'}</tbody></table></div></section>`;
}
function loadingSelection(store) {
  const cards = (store.state.activeLoadings || [])
    .map(
      (loading) =>
        `<button class="dala-selection-card" data-action="select-loading" data-id="${loading.id}" type="button"><span class="kicker">${esc(loading.equipment_code || "Dala")}</span><strong>${esc(loading.romaneio_number || "Sem romaneio")}</strong><span>Caminhão ${esc(loading.plate || "—")}</span><b>${esc(rotuloEstado(loading.state))}</b><small>Selecionar esta Dala</small></button>`,
    )
    .join("");
  return `${pageHeader("Operação", "Selecionar Dala", "Escolha a Dala que será acompanhada e controlada nesta tela.")}<section class="dala-selection-screen"><div class="dala-selection-heading"><span class="kicker">Operações disponíveis</span><h3>Qual Dala você deseja operar?</h3><p>Cada seleção mantém contagem, comandos e emergência separados.</p></div><div class="dala-selection-grid">${cards || '<p class="empty-cell">Nenhum carregamento disponível.</p>'}</div></section>`;
}
function workControls(store) {
  const left = Math.max(0, store.state.planned - store.state.loaded);
  const canUnlock = ["ADMIN_EMPRESA", "SUPERVISOR"].includes(
    store.state.userRole,
  );
  const canReverse = ["ADMIN_EMPRESA", "SUPERVISOR", "USUARIO"].includes(
    store.state.userRole,
  );
  const clpDisponivel = store.clpDisponivel();
  const bloqueioComando = clpDisponivel && store.state.loadingId
    ? ""
    : `disabled aria-disabled="true" title="${store.state.loadingId ? "CLP sem comunicação" : "Nenhum carregamento selecionado"}"`;
  const loadingPicker = `<div class="work-back-row"><button class="button secondary" data-action="goto-manifests" type="button">← Voltar</button></div>`;
  const pendingReadings = store.state.pendingReadings || [];
  const manualIdentification = pendingReadings.length
    ? `<section class="panel alert-box"><h3>Leituras sem código</h3><p>Identifique manualmente cada saca após conferência física.</p>${pendingReadings.map((reading) => `<form class="manual-reading-form" data-reading-id="${reading.id}"><label>Leitura #${reading.id}<input name="barcode" required maxlength="80" /></label>${button("Identificar leitura", "identify-reading", "secondary")}</form>`).join("")}</section>`
    : "";
  const command = store.state.plcCommand;
  const reverseActive = store.state.plcCommand?.command === "REVERSAO_ATIVAR" &&
    !["ERRO", "REJEITADO", "EXPIRADO"].includes(store.state.plcCommand?.status);
  const reverse = canReverse
    ? button(reverseActive ? "Desativar reversão" : "Ativar reversão", "reverse-toggle", reverseActive ? "warning" : "secondary", bloqueioComando)
    : "";
  const finalized = store.state.operationalState === "FINALIZADO";
  const controls = finalized
    ? ""
    : `<div class="work-controls"><div class="work-routine-controls">${button("Iniciar", "run", "primary", bloqueioComando)}${button("Parar", "stop", "ghost", bloqueioComando)}${reverse}</div><div class="work-emergency-zone">${button("Emergência", "emergency", "danger", bloqueioComando)}</div></div>`;
  const summaryReady = ["FINALIZANDO", "FINALIZADO"].includes(
    store.state.operationalState,
  );
  const summaryAction = summaryReady
    ? `${button("Abrir resumo final", "open-summary", "secondary")}`
    : "";
  const badge =
    store.state.operationalState === "EMERGENCIA"
      ? '<span class="badge red">Emergência ativa</span>'
      : `<span class="badge yellow">${esc(rotuloEstado(store.state.operationalState))}</span>`;
  const commandPanel =
    canReverse && command
      ? `<li>Reversão <small>${esc(rotuloStatusComando(command.status))}${command.response_message ? ` • ${esc(command.response_message)}` : ""}</small></li>`
      : "";
  const emergencyPanel = `<div class="emergency"><h2>EMERGÊNCIA ATIVA</h2><p>CONTAGEM BLOQUEADA</p><strong>Aguardando liberação do CLP</strong>${canUnlock ? button("Desbloquear máquina", "unlock", "secondary", bloqueioComando) : ""}</div>`;
  const avisoClp = clpDisponivel
    ? ""
    : `<div class="alert-box" role="alert"><strong>Comandos bloqueados.</strong> ${esc(store.mensagemClpIndisponivel())}</div>`;
  const workTitle = store.state.romaneio && store.state.romaneio !== "—"
    ? `Romaneio #${store.state.romaneio} · ${equipmentLabel(store)}`
    : "Operação";
  return `${pageHeader(`Operação / ${equipmentLabel(store)}`, workTitle, `Caminhão ${store.state.truck}.`, badge)}${loadingPicker}${statuses(store)}${avisoClp}${manualIdentification}${store.state.emergency ? emergencyPanel : `<div class="grid two"><section class="panel"><span class="kicker">Produto atual</span><h3>Contagem do romaneio</h3><p>Leituras vinculadas ao carregamento atual</p><div class="grid three"><div class="metric"><small>Programado</small><strong data-live="planned">${numero(store.state.planned)}</strong></div><div class="metric work-critical-metric"><small>Carregado</small><strong data-live="loaded">${numero(store.state.loaded)}</strong></div><div class="metric work-critical-metric"><small>Faltam</small><strong data-live="remaining">${numero(left)}</strong></div></div>${progress(store)}${left <= 5 && left > 0 ? '<div class="alert-box">Faltam 5 sacas ou menos. Reduza o envio.</div>' : ""}<div class="actions">${controls}</div></section><aside class="panel"><h3>Estado atual</h3><ul><li>Estado <small data-live="operational-state">${esc(rotuloEstado(store.state.operationalState))}</small></li>${commandPanel}<li>Leituras válidas <small data-live="loaded-secondary">${numero(store.state.loaded)}</small></li><li>Carregamento #${store.state.loadingId || "—"}</li></ul>${summaryAction ? `<div class="actions work-summary-action">${summaryAction}</div>` : ""}</aside></div>`}`;
}
export function work(store) {
  if (
    !store.state.selectedLoadingId &&
    (store.state.activeLoadings || []).length
  )
    return loadingSelection(store);
  return workControls(store);
}
