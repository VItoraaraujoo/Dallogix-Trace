import { button, esc } from "../funcoes/html.js";
import { data, dataHora, numero, relativo } from "../funcoes/formato.js";
import { emergencyPanel, manifestStatusBadge, pageHeader, manifestsTable, progress } from "../funcoes/view.js?v=202609150400";
import { rotuloOcorrencia, rotuloStatusRomaneio, rotuloStatusSincronizacao } from "../funcoes/rotulos.js";
export function occurrences(store) {
  const recent = store.state.monitoring?.ocorrencias || [];
  return `${pageHeader("Acompanhamento / qualidade", "Ocorrências", "Registre desvios e paradas relacionadas à carga atual.")}<div class="grid two"><section class="panel"><h3>Ocorrência do lote</h3><form id="occurrence-form"><label>Tipo<select name="type"><option value="SACA_RASGADA">Saca rasgada</option><option value="SACA_AVARIADA">Saca avariada</option><option value="PARADA_MAQUINA">Parada de máquina</option><option value="LIMPEZA_LINHA">Limpeza de linha</option><option value="QUEDA_ENERGIA">Queda de energia</option><option value="AJUSTE_EQUIPAMENTO">Ajuste de equipamento</option><option value="FALHA_ELETRICA">Falha elétrica</option></select></label><label>Quantidade<input name="quantity" type="number" min="1" max="9999" step="1" value="1" /></label><label>Observação<textarea name="description" placeholder="Descreva o que aconteceu..."></textarea></label>${button("Salvar ocorrência", "save-occurrence")}</form></section><section class="panel"><h3>Registros recentes</h3><ul>${recent.length ? recent.map((item) => `<li>${esc(rotuloOcorrencia(item.type))} — ${numero(item.quantity)} unidade(s)<small>${esc(dataHora(item.created_at))} · ${esc(item.description || "Sem observação")}</small></li>`).join("") : "<li>Nenhuma ocorrência registrada.</li>"}</ul></section></div>`;
}
export function summary(store) {
  const data = store.state.monitoring || {
    leituras: {},
    ocorrencias: [],
    sync_pendente: 0,
  };
  const canFinish = ["FINALIZANDO", "CARREGANDO"].includes(store.state.operationalState);
  return `<div class="title-row with-actions has-back"><button class="button secondary page-back" data-action="back-work" type="button">← Voltar</button><div><span class="kicker">Acompanhamento / encerramento</span><h2>Resumo final</h2><p>${canFinish ? "Confira o balanço antes de finalizar a carga." : "Carregamento já finalizado."}</p></div><div class="actions">${button("Exportar CSV", "export")}</div></div><section class="panel"><h3>Conferência da carga</h3><p>Romaneio #${store.state.romaneio} • caminhão ${store.state.truck}</p>${progress(store)}<div class="grid four"><div class="metric"><small>Leituras válidas</small><strong>${numero(data.leituras.VALIDO)}</strong></div><div class="metric"><small>Sem leitura</small><strong>${numero(data.leituras.SEM_LEITURA)}</strong></div><div class="metric"><small>Ocorrências</small><strong>${numero(data.ocorrencias.length)}</strong></div><div class="metric"><small>Envio ao servidor</small><strong>${numero(data.sync_pendente)}</strong></div></div>${canFinish ? button("Finalizar carregamento", "finish", "primary") : ""}</section>`;
}
export function history(store) {
  const data = store.state.monitoring || { auditoria: [] };
  const report = store.state.report || { summary: {}, rows: [] };
  const total = report.summary || {};
  const filters = store.state.reportFilters || {};
  const rows = report.rows || [];
  store.state.reportCsvRows = [
    [
      "Data",
      "Romaneio",
      "Status",
      "Caminhão",
      "Planejado",
      "Carregado",
      "Sem leitura",
      "Produto incorreto",
      "Ocorrências",
      "Duração (min)",
      "Divergência",
    ],
    ...rows.map((row) => [
      row.scheduled_date,
      row.number,
      row.status,
      row.plate || "",
      row.planned_quantity,
      row.loaded_quantity,
      row.no_readings,
      row.wrong_products,
      row.occurrences,
      row.duration_minutes ?? "",
      row.has_divergence ? "Sim" : "Não",
    ]),
  ];
  return `${pageHeader("Acompanhamento / supervisão", "Histórico operacional", "Filtre cargas concluídas, divergências e ocorrências para auditoria.", button("Exportar CSV", "export-report"))}
  <form id="report-filters" class="panel filters"><div class="filter-grid"><label>Data inicial<input type="date" name="date_from" value="${esc(filters.date_from || "")}"></label><label>Data final<input type="date" name="date_to" value="${esc(filters.date_to || "")}"></label><label>Status<select name="status"><option value="">Todos</option>${["AGUARDANDO", "EM_ANDAMENTO", "FINALIZADO", "CANCELADO"].map((value) => `<option value="${value}"${filters.status === value ? " selected" : ""}>${rotuloStatusRomaneio(value)}</option>`).join("")}</select></label><button class="button primary" type="submit">Aplicar filtros</button></div></form><br>
  <div class="grid five"><div class="panel metric"><small>Romaneios</small><strong>${numero(total.romaneios)}</strong></div><div class="panel metric"><small>Planejado</small><strong>${numero(total.planejado)}</strong></div><div class="panel metric"><small>Carregado</small><strong>${numero(total.carregado)}</strong></div><div class="panel metric"><small>Ocorrências</small><strong>${numero(total.ocorrencias)}</strong></div><div class="panel metric"><small>Divergentes</small><strong class="${total.divergentes ? "metric-red" : "metric-green"}">${numero(total.divergentes)}</strong></div></div><br>
  <section class="panel table-wrap"><table><thead><tr><th>Data</th><th>Romaneio</th><th>Status</th><th>Caminhão</th><th>Planejado</th><th>Carregado</th><th>Alertas</th><th>Duração</th></tr></thead><tbody>${rows.length ? rows.map((row) => `<tr><td>${esc(data(row.scheduled_date))}</td><td><strong>${esc(row.number)}</strong></td><td>${manifestStatusBadge(row)}</td><td>${esc(row.plate || "—")}</td><td>${numero(row.planned_quantity)}</td><td>${numero(row.loaded_quantity)}</td><td>${numero(Number(row.no_readings) + Number(row.wrong_products) + Number(row.occurrences))}${row.has_divergence ? " · divergência" : ""}</td><td>${row.duration_minutes === null ? "—" : `${numero(row.duration_minutes)} min`}</td></tr>`).join("") : '<tr><td colspan="8" class="empty-cell">Nenhum registro para os filtros informados.</td></tr>'}</tbody></table></section><br><section class="panel"><h3>Auditoria recente</h3>${data.auditoria.length ? `<ul>${data.auditoria.map((item) => `<li>${esc(item.action)} — ${esc(item.entity_type || "")} #${esc(item.entity_id || "")}<small>${esc(dataHora(item.created_at))}</small></li>`).join("")}</ul>` : "<p>Nenhum evento auditado.</p>"}</section>`;
}
export function products(store) {
  const search = (store.state.productSearch || "").toLowerCase();
  const all = store.state.products || [];
const products = search
    ? all.filter((product) =>
        `${product.name} ${product.code} ${product.category || ""} ${product.barcodes || ""}`
          .toLowerCase()
          .includes(search),
      )
    : all;
  const open = store.state.productFormOpen;
  const editingId = store.state.editingProductId;
  const editing = editingId
    ? all.find((product) => Number(product.id) === Number(editingId))
    : null;
  const form = open
    ? `<section class="panel"><div class="panel-heading"><h3>${editing ? "Editar produto" : "Cadastrar produto"}</h3></div>
<form id="product-form" data-editing="${editing ? editing.id : ""}"><div class="grid four">
<label>Nome <b class="required">*</b><input name="name" required value="${editing ? esc(editing.name) : ""}" /><small>Nome comercial do produto.</small></label>
<label>Código de Barras <b class="required">*</b><input name="barcode" required value="${editing ? esc((editing.barcodes || "").split(",")[0]) : ""}" /><small>Exemplo: 7898250782592.</small></label>
<label>SKU<input name="code" value="${editing ? esc(editing.code || "") : ""}" /><small>Opcional; gerado automaticamente se ficar vazio.</small></label>
<label>Categoria<input name="category" value="${editing ? esc(editing.category || "") : ""}" /><small>Opcional.</small></label>
</div><div class="actions${editing ? " product-edit-actions" : ""}">${button("Salvar", "submit-product", editing ? "secondary" : "primary")}${button("Cancelar", "cancel-product", "ghost")}</div></form></section><br>`
    : "";
  return `<div class="title-row with-actions"><div><h2>Produtos</h2></div>${button(open ? "Fechar formulário" : "+ Novo produto", "toggle-product-form")}</div>
${form}
<section class="panel product-catalog-panel"><div class="search-row"><label class="product-search-label" for="product-search">Buscar produto<input id="product-search" placeholder="Nome, código ou categoria" aria-label="Buscar por nome, código ou categoria" value="${esc(store.state.productSearch || "")}" /></label></div>
<div class="table-wrap"><table><thead><tr><th>Nome</th><th>Código de Barras</th><th>SKU</th><th>Categoria</th><th>Ativo</th><th>Ações</th></tr></thead><tbody>${
    !store.state.productsLoaded
      ? '<tr><td colspan="6" class="empty-cell">Carregando produtos…</td></tr>'
      : products.length
      ? products
          .map(
            (product) => `<tr>
<td><strong>${esc(product.name)}</strong></td>
<td>${esc(product.barcodes || "—")}</td>
<td>${esc(product.code || "—")}</td>
<td>${esc(product.category || "—")}</td>
<td><span class="badge ${Number(product.active) ? "green" : "red"}">${Number(product.active) ? "Sim" : "Não"}</span></td>
<td><div class="table-actions"><button class="text-link" data-action="edit-product" data-id="${product.id}" type="button">Editar</button><button class="text-link danger-link" data-action="delete-product" data-id="${product.id}" data-name="${esc(product.name)}" type="button">Excluir</button></div></td>
</tr>`,
          )
          .join("")
      : '<tr><td colspan="6" class="empty-cell">Nenhum produto cadastrado.</td></tr>'
  }</tbody></table></div></section>`;
}
export function alerts(store) {
  const data = store.state.monitoring || {
    leituras: {},
    sync_pendente: 0,
    dispositivos: [],
  };
  const devices = data.dispositivos || [];
  const sync = store.state.syncStatus || {
    summary: {},
    recent: [],
    remote_configured: false,
  };
  const summary = sync.summary || {};
  const syncRows = sync.recent?.length
    ? sync.recent
        .map((item) => {
          const kind = {
            ROMANEIO: "Romaneio",
            LEITURA: "Leitura",
            OCORRENCIA: "Ocorrência",
            COMANDO_CLP: "Comando da máquina",
          }[String(item.aggregate_type || "").toUpperCase()] || "Registro operacional";
          const status = rotuloStatusSincronizacao(item.status);
          const detail = item.status === "ERRO" ? "Falha no envio; tente novamente." : "Aguardando processamento seguro.";
          return `<tr><td><strong>${esc(kind)}</strong><small>Registro #${esc(item.aggregate_id || item.id)}</small></td><td>${esc(status)}<small>${detail}</small></td><td>${esc(dataHora(item.available_at, "agora"))}</td><td><button class="button secondary small" data-action="retry-sync" data-id="${item.id}" type="button" ${sync.remote_configured ? "" : "disabled"}>Tentar novamente</button></td></tr>`;
        })
        .join("")
    : '<tr><td colspan="4" class="empty-cell">Nenhum envio aguardando processamento.</td></tr>';
  return `${pageHeader("Acompanhamento / máquina", "Alertas e sincronização", "Avisos dos periféricos e acompanhamento seguro da fila local-first.")}
  <section class="panel"><ul><li>Leituras válidas registradas: ${numero(data.leituras.VALIDO)}</li><li>Produtos incorretos: ${numero(data.leituras.PRODUTO_INCORRETO)}</li>${devices.map((device) => `<li>${esc(device.equipment_code)} · ${esc(device.device_type)}: <strong>${esc(device.status)}</strong><small>Último sinal: ${esc(relativo(device.last_seen_at))}${device.segundos_sem_sinal === null ? "" : ` · sem sinal há ${esc(device.segundos_sem_sinal)} s`}</small></li>`).join("")}</ul></section><br>
  <section class="panel"><div class="panel-heading"><div><h3>Fila de sincronização</h3><p>${sync.remote_configured ? "Endpoint remoto configurado." : "Modo local: configure o endpoint remoto para enviar os eventos."}</p></div><span class="badge ${sync.remote_configured ? "green" : "yellow"}">${sync.remote_configured ? "Remota disponível" : "Somente local"}</span></div><div class="grid four"><div class="metric"><small>Pendentes</small><strong>${numero(summary.PENDENTE)}</strong></div><div class="metric"><small>Com erro</small><strong class="${summary.ERRO ? "metric-red" : "metric-green"}">${numero(summary.ERRO)}</strong></div><div class="metric"><small>Processando</small><strong>${numero(summary.PROCESSANDO)}</strong></div><div class="metric"><small>Enviados</small><strong class="metric-green">${numero(summary.ENVIADO)}</strong></div></div></section><br>
  <section class="panel table-wrap"><h3>Envios pendentes</h3><p>Os registros ficam na fila local até a conexão com o servidor estar disponível.</p><table><thead><tr><th>Registro</th><th>Situação</th><th>Próxima verificação</th><th>Ação</th></tr></thead><tbody>${syncRows}</tbody></table></section>`;
}
export function emergency(store) {
  const canUnlock =
    ["ADMIN_EMPRESA", "SUPERVISOR"].includes(store.state.userRole) &&
    Boolean(store.state.loadingId);
  const loading = store.state.loadingId
    ? `Carregamento #${store.state.loadingId} • romaneio ${esc(store.state.romaneio)} • caminhão ${esc(store.state.truck)}`
    : "Nenhum carregamento ativo identificado.";
  return emergencyPanel({ loading, canUnlock });
}
