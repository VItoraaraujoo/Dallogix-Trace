import { esc, button } from "../funcoes/html.js";
import { pageHeader } from "../funcoes/view.js?v=202609150020";

export function errorLogs(store) {
  const rows = store.state.errorLogs || [];
  const format = (value) => value ? String(value).replace(" ", " • ").split(".")[0] : "—";
  return `${pageHeader("Sistema / diagnóstico", "Logs de erros", "Falhas inesperadas registradas pelo sistema para apoiar testes e suporte.", button("Atualizar", "reload-error-logs", "secondary"))}
  <section class="panel compact-help"><strong>Proteção de dados</strong><p>Senhas, tokens, sessões e conteúdo de formulários não são armazenados neste histórico.</p></section><br>
  <section class="panel reference-table-panel"><div class="table-wrap"><table class="error-logs-table"><thead><tr><th>Data</th><th>Origem</th><th>Mensagem</th><th>Empresa</th><th>Usuário</th></tr></thead><tbody>${rows.length ? rows.map((item) => `<tr><td>${esc(format(item.criado_em))}</td><td><code>${esc(item.origem)}</code></td><td class="error-message-cell">${esc(item.mensagem)}</td><td>${esc(item.empresa || "Sistema")}</td><td>${esc(item.usuario || "—")}</td></tr>`).join("") : '<tr><td colspan="5" class="empty-cell">Nenhum erro inesperado registrado.</td></tr>'}</tbody></table></div></section>`;
}
