# Status dos testes — Etapa 23

- Arquitetura interface migrada de SPA (index único) para MPA: cada tela possui seu próprio arquivo HTML na raiz de `interface/`.
- **HTML real em todas as páginas**: menu lateral, barra superior, rodapé do usuário e área de conteúdo estão escritos diretamente no HTML de cada página; o JavaScript apenas hidrata dados e ajusta o menu por perfil (`hydrateChrome`). Nenhuma página é uma casca vazia.
- 16 páginas: `index.html` (login estático com formulário) + `dashboard`, `manifests`, `import`, `division`, `work`, `occurrences`, `summary`, `history`, `products`, `alerts`, `emergency`, `settings`, `dalas`, `companies` e `company`.
- Núcleo compartilhado único em `js/aplicacao.js` (versionado com `?v=2` para quebrar cache): identifica a tela pelo atributo `data-page` da tag `<script>`.
- Navegação por arquivos reais (`manifests.html`, `companies.html`, …); rota SPA `?page=` removida junto com `TraceRouter.js`.
- Guardas por página: sem sessão → redireciona para `index.html`; `ADMIN_DALLOGIX` restrito a empresas/company/configurações (menu podado dinamicamente); demais perfis bloqueados em companies/company; login com sessão ativa segue direto para a página padrão do perfil.
- Detalhe de empresa recebe o id via query string (`company.html?id=N`) — estado não depende mais de memória entre páginas.
- Teste reproduzível: `bash testes/etapa23.sh` (valida sidebar, topbar, screen-root, botões, formulário de login e versionamento em todas as páginas). Regressão completa 1→23 sem falhas.

ETAPA 23 concluída.
