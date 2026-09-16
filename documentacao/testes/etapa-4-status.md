# Status dos testes — Etapa 4

## Implementação

- API protegida para listar produtos: `/api/produtos.php`.
- API para listar e criar romaneios com caminhão e item: `/api/romaneios.php`.
- API para iniciar carregamento validando empresa, romaneio, caminhão e esteira: `/api/carregamentos.php`.
- Tela de romaneios conectada ao banco local após autenticação.
- Consultas isoladas por `company_id`.
- Charset UTF-8 normalizado no PDO e no seed.

## Validação

- Endpoints sem sessão retornam HTTP 401.
- Criação válida de romaneio retorna HTTP 201.
- Romaneio duplicado retorna HTTP 409.
- Produto acentuado retorna corretamente em UTF-8.
- Início de carregamento válido retorna HTTP 201.
- Métodos não permitidos retornam HTTP 405.
- Teste reproduzível: `bash testes/etapa4.sh`.

## Resultado

ETAPA 4 concluída. O próximo passo é evoluir o fluxo de carregamento com leituras, eventos de sensor e estados operacionais.
