import { button, esc } from "../funcoes/html.js";
import { pageHeader } from "../funcoes/view.js?v=202609150020";

const roleLabel = {
  ADMIN_EMPRESA: "Administrador",
  SUPERVISOR: "Supervisor",
  USUARIO: "Usuário",
};

export function users(store) {
  const platform = store.state.userRole === "ADMIN_DALLOGIX";
  const companyId = store.state.selectedCompanyId || "";
  const users = store.state.users || [];
  const companyOptions = (store.state.companies || [])
    .map(
      (company) =>
        `<option value="${company.id}"${String(company.id) === String(companyId) ? " selected" : ""}>${esc(company.name)}</option>`,
    )
    .join("");
  const roleOptions = platform
    ? ["ADMIN_EMPRESA", "SUPERVISOR", "USUARIO"]
    : ["SUPERVISOR", "USUARIO"];
  const emailDomain = "dallogix.local";
  const setup =
    platform && !companyId
      ? `<section class="panel empty-cell"><p>Selecione uma empresa para visualizar ou criar logins.</p></section>`
      : `<section class="panel users-create-panel"><div class="panel-heading"><div><span class="kicker">Novo acesso</span><h3>Criar login</h3><p>Digite apenas o identificador. O domínio é fixo para toda a plataforma.</p></div><span class="badge blue">Domínio padronizado</span></div><form id="user-create-form"><div class="grid four">${platform ? `<label>Empresa<select name="company_id" required>${companyOptions}</select></label>` : ""}<label>Nome<input name="name" required maxlength="160" autocomplete="name" /></label><label class="login-address-label">E-mail de acesso<div class="login-address"><input name="email_prefix" required pattern="[a-z0-9][a-z0-9._-]{2,63}" autocomplete="username" aria-label="Início do e-mail" /><span>@${emailDomain}</span></div><small>Use letras minúsculas, números, ponto, hífen ou sublinhado.</small></label><label>Perfil<select name="role">${roleOptions.map((role) => `<option value="${role}">${roleLabel[role]}</option>`).join("")}</select></label><label>Senha inicial<input name="password" type="password" minlength="10" required autocomplete="new-password" /></label></div><div class="actions">${button("Criar login", "create-user")}</div></form></section>`;
  const description = platform
    ? "Crie administradores, supervisores e usuários para cada empresa."
    : "Crie supervisores e usuários para sua empresa.";
  return `<div class="title-row with-actions has-back"><button class="button secondary page-back" data-action="back-settings" type="button">← Voltar</button><div><span class="kicker">Sistema / acessos</span><h2>Usuários</h2><p>${description}</p></div></div> ${platform ? `<section class="panel compact-help"><label>Empresa para gerenciar<select id="users-company-selector"><option value="">Selecionar empresa…</option>${companyOptions}</select></label></section><br>` : ""}${setup}<br><section class="panel table-wrap"><h3>Logins ativos</h3><table><thead><tr><th>Nome</th><th>E-mail</th><th>Perfil</th><th>Status</th><th>Criado em</th><th>Ações</th></tr></thead><tbody>${users.length ? users.map((user) => `<tr><td><strong>${esc(user.name)}</strong></td><td>${esc(user.email)}</td><td>${esc(roleLabel[user.role] || user.role)}</td><td><span class="badge ${Number(user.active) ? "green" : "red"}">${Number(user.active) ? "Ativo" : "Inativo"}</span></td><td>${esc(user.created_at)}</td><td><div class="table-actions"><button class="text-link" data-action="toggle-user" data-id="${user.id}" data-name="${esc(user.name)}" data-role="${user.role}" data-active="${Number(user.active)}" type="button" ${Number(user.id) === Number(store.state.currentUserId) ? 'disabled title="Não é possível desativar o próprio acesso"' : ""}>${Number(user.active) ? "Desativar" : "Ativar"}</button><button class="text-link" data-action="reset-user-password" data-id="${user.id}" data-name="${esc(user.name)}" data-role="${user.role}" data-active="${Number(user.active)}" type="button">Redefinir senha</button></div></td></tr>`).join("") : '<tr><td colspan="6" class="empty-cell">Nenhum login encontrado.</td></tr>'}</tbody></table></section>`;
}
