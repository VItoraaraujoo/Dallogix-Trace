import { button, esc } from "../funcoes/html.js";
import { dataHora, numero } from "../funcoes/formato.js";
import { pageHeader } from "../funcoes/view.js?v=202609150020";
import { rotuloComando, rotuloEstado, rotuloEvento, rotuloStatusComando } from "../funcoes/rotulos.js";

function dalaStatusCell(equipment) {
  return `<div class="dala-status" data-equipment-id="${equipment.id}"><span class="status-dot"></span>Verificando…</div>`;
}

const DALA_MOBILE_QUERY = "(max-width: 900px)";

function isMobileDalaViewport() {
  return typeof window !== "undefined" && typeof window.matchMedia === "function"
    ? window.matchMedia(DALA_MOBILE_QUERY).matches
    : false;
}

function desktopOnlyMessage() {
  return `<section class="panel dala-desktop-only-notice" role="status"><h2>Dalas</h2><p>Esta tela está disponível apenas em computadores.</p><p>Abra o Trace em uma tela com largura maior para cadastrar e gerenciar Dalas.</p></section>`;
}

function dalaReferenceActions(equipment, canManage, canDelete) {
  const actions = [
    `<button data-action="view-dala" data-id="${equipment.id}" type="button">Visualizar</button>`,
    canManage ? `<button data-action="edit-dala" data-id="${equipment.id}" type="button">Editar</button>` : "",
    canManage ? `<button data-action="dala-actions" data-id="${equipment.id}" type="button">Ações</button>` : "",
    canDelete ? `<button class="danger" data-action="delete-dala" data-id="${equipment.id}" data-name="${esc(equipment.name)}" type="button">Excluir</button>` : "",
  ].filter(Boolean);
  const withClass = (item) => item.includes('class="danger"')
    ? item.replace('class="danger"', 'class="dala-action-v2 danger"')
    : item.replace("<button ", '<button class="dala-action-v2" ');
  return `<div class="dala-actions-v2">${actions.map(withClass).join("")}</div>`;
}

export function dalas(store) {
  if (isMobileDalaViewport()) return desktopOnlyMessage();
  const rows = store.state.equipments || [];
  const open = store.state.dalaFormOpen;
  const canDelete = ["ADMIN_DALLOGIX", "ADMIN_EMPRESA"].includes(
    store.state.userRole,
  );
  const canManage = ["ADMIN_DALLOGIX", "ADMIN_EMPRESA"].includes(
    store.state.userRole,
  );
  if (open) {
    return `<div class="dala-create-screen"><div class="title-row has-back"><button class="button secondary page-back" data-action="toggle-dala-form" type="button">← Voltar</button><div><h2>Nova Dala</h2><p>Cadastre a comunicação da Dala com o CLP e o gateway.</p></div></div>
<section class="panel dala-create-panel"><form id="dala-create-form"><div class="grid one">
<label>Nome da Dala<input name="name" autocomplete="off" required /></label>
<label>Identificador<input name="equipment_code" autocomplete="off" required pattern="[a-z0-9_]{1,30}" title="Letras minúsculas, números e underscores (máx. 30)" /><small>Use letras minúsculas, números e underscore. Máximo de 30 caracteres.</small></label>
<label>IP do CLP<input name="plc_ip" inputmode="decimal" autocomplete="off" required /></label>
<label>Porta do CLP<input name="plc_port" type="number" value="502" min="1" max="65535" required /></label>
<label>Porta externa no gateway<input name="external_port" type="number" min="1" max="65535" /><small>Porta TCP pública do gateway que encaminha a comunicação para esta Dala.</small></label>
</div><div class="actions">${button("Cadastrar Dala", "submit-dala")}</div></form></section></div>`;
  }
  const cells = (equipment) => `<div class="dala-grid-row-v2" role="row">
<div class="dala-grid-cell-v2" data-label="Nome" role="cell"><strong>${esc(equipment.name)}</strong></div>
<div class="dala-grid-cell-v2" data-label="Identificador" role="cell"><code>${esc(equipment.equipment_code)}</code></div>
<div class="dala-grid-cell-v2" data-label="IP do CLP" role="cell">${esc(equipment.plc_ip || "—")}</div>
<div class="dala-grid-cell-v2" data-label="Porta do CLP" role="cell">${esc(equipment.plc_port || "—")}</div>
<div class="dala-grid-cell-v2" data-label="Porta Externa" role="cell">${esc(equipment.external_port || "—")}</div>
<div class="dala-grid-cell-v2" data-label="Status" role="cell">${dalaStatusCell(equipment)}</div>
<div class="dala-grid-cell-v2" data-label="Ações" role="cell">${dalaReferenceActions(equipment, canManage, canDelete)}</div>
</div>`;
  return `<section class="dalas-screen-v2" aria-labelledby="dalas-title"><div class="dalas-header-v2"><h2 id="dalas-title">Dalas</h2>${canManage ? button("Nova dala", "toggle-dala-form", "primary") : ""}</div>
<div class="dalas-grid-v2" role="table"><div class="dala-grid-head-v2" role="row">${["Nome", "Identificador", "IP do CLP", "Porta do CLP", "Porta Externa", "Status", "Ações"].map((label) => `<div role="columnheader">${label}</div>`).join("")}</div>${rows.length ? rows.map(cells).join("") : '<div class="dala-empty-v2" role="row"><div role="cell">Nenhuma Dala cadastrada.</div></div>'}</div></section>`;
}

// Tela Visualizar Dala: cartões com dados cadastrais e status de comunicação.
export function dalaView(store) {
  if (isMobileDalaViewport()) return desktopOnlyMessage();
  const dala = store.state.equipmentDetail;
  if (!dala) return `${pageHeader("Cadastros / Dalas", "Dala", "Carregando…")}`;
  const operation = (store.state.monitoring?.maquinas || []).find(
    (item) => Number(item.id) === Number(dala.id),
  ) || {};
  const planned = Number(operation.planned_quantity || 0);
  const loaded = Number(operation.valid_readings || 0);
  const percentage =
    planned > 0 ? Math.min(100, Math.round((loaded / planned) * 100)) : 0;
  const commands = store.state.dalaCommands || [];
  const visibleCommands = commands.slice(0, 50);
  const commandRows = visibleCommands.length ? visibleCommands.map((command) => `<tr><td>#${command.id}</td><td>${esc(rotuloComando(command.command))}<small><code>${esc(command.command)}</code></small></td><td>${esc(rotuloStatusComando(command.status))}</td><td>${esc(dataHora(command.requested_at))}</td><td>${esc(command.response_message || "Aguardando resposta")}</td></tr>`).join("") : '<tr><td colspan="5" class="empty-cell">Nenhum comando registrado para esta Dala.</td></tr>';
  const commandSummary = commands.length > 50 ? `<p class="muted dala-diagnostics-summary">Exibindo os 50 comandos mais recentes de ${commands.length} registros.</p>` : "";
  return `<div class="title-row with-actions dala-page-header"><div><span class="dala-page-kicker">Cadastros / Dalas</span><h2>${esc(dala.name)}</h2><p class="muted">Identificador <code>${esc(dala.equipment_code)}</code></p></div><div class="actions"><button class="button secondary page-back" data-action="back-dala" type="button">← Voltar</button></div></div>
<section class="panel dala-overview-panel"><div class="dala-overview-heading"><div><span class="dala-page-kicker">Configuração da Dala</span><h3>Comunicação e cadastro</h3></div><p id="dala-view-status" class="dala-status-line" data-equipment-id="${dala.id}"><span class="status-dot"></span>Verificando comunicação…</p></div>
<div class="dala-info-grid">
<div class="dala-info-item"><small>Nome da Dala</small><strong>${esc(dala.name)}</strong></div>
<div class="dala-info-item"><small>Identificador</small><strong><code>${esc(dala.equipment_code)}</code></strong></div>
<div class="dala-info-item"><small>IP do CLP</small><strong>${esc(dala.plc_ip || "—")}</strong></div>
<div class="dala-info-item"><small>Porta do CLP</small><strong>${dala.plc_port || "—"}</strong></div>
<div class="dala-info-item"><small>Porta externa</small><strong>${dala.external_port || "—"}</strong></div>
<div class="dala-info-item"><small>Criada em</small><strong>${dataHora(dala.created_at)}</strong></div>
<div class="dala-info-item"><small>Atualizada em</small><strong>${dataHora(dala.updated_at)}</strong></div>
</div></section>
<section class="panel dala-stats-panel"><div class="panel-heading"><div><span class="dala-page-kicker">Operação</span><h3>Estatísticas da Dala</h3></div><span class="dala-last-signal">Último sinal: ${esc(operation.last_seen_at || "Sem sinal registrado")}</span></div><div class="grid four dala-stats-grid">
  <div class="metric"><small>Estado da operação</small><strong>${esc(rotuloEstado(operation.carregamento_state))}</strong></div>
  <div class="metric"><small>Romaneio atual</small><strong>${esc(operation.romaneio_number ? `#${operation.romaneio_number}` : "—")}</strong></div>
  <div class="metric"><small>Carregado</small><strong>${numero(loaded)} / ${numero(planned)}</strong></div>
  <div class="metric"><small>Progresso</small><strong>${percentage}%</strong></div>
  </div></section>
<section class="panel dala-diagnostics-panel"><div class="panel-heading"><div><span class="dala-page-kicker">Monitoramento</span><h3>Diagnóstico do CLP</h3><p class="muted">Comandos enviados, status do gateway e retorno registrado.</p></div><button class="button secondary" data-action="reload-dala-diagnostics" data-id="${dala.id}" type="button">Atualizar</button></div>${commandSummary}<div class="table-wrap"><table><thead><tr><th>ID</th><th>Comando</th><th>Status</th><th>Solicitado em</th><th>Resposta</th></tr></thead><tbody>${commandRows}</tbody></table></div></section>`;
}

export function dalaActions(store) {
  if (isMobileDalaViewport()) return desktopOnlyMessage();
  const dala = store.state.equipmentDetail;
  if (!dala) return `${pageHeader("Cadastros / Dalas", "Ações", "Carregando…")}`;
  const config = store.state.dalaActionConfig || { acoes: [], gatilhos: [] };
  const canManage = Boolean(config.can_manage);
  const actions = config.acoes || [];
  const triggers = config.gatilhos || [];
  const actionOptions = actions.map((item) => `<option value="${item.id}">${esc(item.rotulo)} (${esc(item.comando)})</option>`).join("");
  const actionRows = actions.length ? actions.map((item, index) => `<tr>
    <td><div class="action-order"><span>${item.ordem}</span>${canManage ? `<button class="icon-button" data-action="move-dala-action" data-id="${item.id}" data-direction="up" type="button"${index === 0 ? " disabled" : ""} aria-label="Mover para cima">↑</button><button class="icon-button" data-action="move-dala-action" data-id="${item.id}" data-direction="down" type="button"${index === actions.length - 1 ? " disabled" : ""} aria-label="Mover para baixo">↓</button>` : ""}</div></td>
    <td><strong>${esc(rotuloComando(item.comando))}</strong><small><code>${esc(item.comando)}</code></small></td><td><strong>${esc(item.rotulo)}</strong></td><td><span class="dala-color-swatch" data-color="${esc(item.cor)}"></span>${esc(item.cor[0] + item.cor.slice(1).toLowerCase())}</td><td>${Number(item.visivel) ? "Sim" : "Não"}</td><td>${esc(item.modo[0] + item.modo.slice(1).toLowerCase())}</td>
    ${canManage ? `<td><div class="table-actions"><button class="text-link" data-action="edit-dala-action" data-id="${item.id}" type="button">Editar</button><button class="text-link danger-link" data-action="delete-dala-action" data-id="${item.id}" data-name="${esc(item.rotulo)}" type="button">Excluir</button></div></td>` : ""}</tr>`).join("") : `<tr><td colspan="${canManage ? 7 : 6}" class="empty-cell">Nenhuma ação configurada.</td></tr>`;
  const triggerRows = triggers.length ? triggers.map((trigger) => `<tr><td>${esc(rotuloEvento(trigger.evento))}</td><td>${esc(trigger.acao_rotulo || "Nenhuma ação")}</td>${canManage ? `<td><div class="table-actions"><button class="text-link" data-action="edit-dala-trigger" data-id="${trigger.id}" type="button">Editar</button></div></td>` : ""}</tr>`).join("") : `<tr><td colspan="${canManage ? 3 : 2}" class="empty-cell">Nenhum gatilho configurado.</td></tr>`;
  const dalaLabel = dala.equipment_code || dala.name || "Dala";
  return `<div class="title-row with-actions dala-page-header"><div><h2>Ações — ${esc(dalaLabel)}</h2></div><div class="actions"><button class="button secondary page-back" data-action="back-dala" type="button">← Voltar</button>${canManage ? `<button class="button secondary" data-action="new-dala-trigger" type="button">Novo gatilho</button><button class="button primary" data-action="new-dala-action" type="button">Nova ação</button>` : ""}</div></div>
  <section class="panel reference-table-panel"><div class="table-wrap"><table class="dala-actions-table"><thead><tr><th>Ordem</th><th>Comando</th><th>Rótulo</th><th>Cor</th><th>Visível</th><th>Modo</th>${canManage ? "<th>Ações</th>" : ""}</tr></thead><tbody>${actionRows}</tbody></table></div></section>
  <section class="dala-action-editor" hidden><form id="dala-action-form"><input name="id" type="hidden" /><div class="grid three"><label>Comando<select name="comando" required><option value="INICIAR_CARREGAMENTO">Iniciar carregamento</option><option value="PAUSAR_CARREGAMENTO">Pausar carregamento</option><option value="REVERSAO_ATIVAR">Ativar reversão</option><option value="REVERSAO_DESATIVAR">Desativar reversão</option><option value="EMERGENCIA">Emergência</option></select></label><label>Rótulo<input name="rotulo" maxlength="80" required /></label><label>Cor<select name="cor"><option value="VERDE">Verde</option><option value="VERMELHO">Vermelho</option><option value="CINZA">Cinza</option><option value="AMBAR">Âmbar</option><option value="AZUL">Azul</option></select></label><label>Modo<select name="modo"><option value="INCREMENTAL">Incremental</option><option value="DECREMENTAL">Decremental</option><option value="DIRETO">Direto</option></select></label><label class="checkbox-label"><input name="visivel" type="checkbox" checked /> Visível na operação</label></div><div class="actions"><button class="button primary" type="submit">Salvar ação</button><button class="button secondary" data-action="cancel-dala-action" type="button">Cancelar</button></div></form></section>
  <br><h3 class="section-title">Gatilhos</h3><section class="panel reference-table-panel"><div class="table-wrap"><table><thead><tr><th>Evento</th><th>Ação</th>${canManage ? "<th>Ações</th>" : ""}</tr></thead><tbody>${triggerRows}</tbody></table></div></section>
  <section class="dala-trigger-editor" hidden><form id="dala-trigger-form"><input name="trigger_id" type="hidden" /><div class="grid two"><label>Evento<input value="Operação atingir 100%" disabled /></label><label>Ação<select name="acao_id"><option value="">Nenhuma ação</option>${actionOptions}</select></label></div><div class="actions"><button class="button primary" type="submit">Salvar gatilho</button><button class="button secondary" data-action="cancel-dala-trigger" type="button">Cancelar</button></div></form></section>
  <section class="panel compact-help"><strong>Segurança operacional</strong><p>Esta tela configura as ações e gatilhos. A execução passa pela operação e pelo gateway industrial; o CLP mantém os intertravamentos físicos e registra a resposta no diagnóstico da Dala.</p></section>`;
}

// Tela Editar Dala: mesmo formulário da criação, com dados preenchidos.
export function dalaEdit(store) {
  if (isMobileDalaViewport()) return desktopOnlyMessage();
  const dala = store.state.equipmentDetail;
  if (!dala)
    return `${pageHeader("Cadastros / Dalas", "Editar Dala", "Carregando…")}`;
  return `<div class="title-row with-actions dala-page-header"><div><h2>Editar Dala ${esc(dala.equipment_code)}</h2></div><div class="actions"><button class="button secondary page-back" data-action="back-dala" type="button">← Voltar</button></div></div>
<form id="dala-edit-form" data-id="${dala.id}">
<section class="panel"><div class="grid one">
<label>Nome da Dala<input name="name" required value="${esc(dala.name)}" /></label>
<label>Identificador<input name="equipment_code" required readonly pattern="[a-z0-9_]{1,30}" title="O identificador não pode ser alterado após o cadastro" value="${esc(dala.equipment_code)}" /><small>Este identificador é usado pelo serviço da Dala e não pode ser alterado após o cadastro.</small></label>
<label>IP do CLP<input name="plc_ip" required value="${esc(dala.plc_ip || "")}" /></label>
<label>Porta do CLP<input name="plc_port" type="number" min="1" max="65535" required value="${dala.plc_port || 502}" /></label>
<label>Porta externa no gateway<input name="external_port" type="number" min="1" max="65535" value="${dala.external_port || ""}" /><small>Porta TCP no gateway público do cliente, redirecionada para o serviço dala-modbus na edge.</small></label>
</div><div class="actions">${button("Salvar", "save-dala-edit")}</div></section>
</form>`;
}
