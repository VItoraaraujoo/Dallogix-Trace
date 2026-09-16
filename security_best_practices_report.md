# Relatório de revisão de segurança

## Resumo executivo

A autenticação, autorização por empresa, proteção de sessão, CSRF em produção,
limite de login e consultas parametrizadas estão implementados de forma sólida.
Foram identificados quatro pontos para correção: uma condição de corrida na
conclusão de capturas de câmera, sessão possivelmente desatualizada na rota
`me.php`, validação incompleta de uploads e uma transição de emergência que
altera apenas o estado lógico sem enfileirar confirmação para o gateway físico.

## Alta prioridade

### SEC-001 — Conclusão de captura de câmera não é atômica

**Impacto:** duas chamadas `COMPLETE` concorrentes para o mesmo pedido podem
ser aceitas, inserir duas imagens e substituir o caminho registrado.

Em `camera_worker.php`, o pedido é consultado como `CAPTURANDO` e depois
atualizado em outra operação sem transação nem condição `status = 'CAPTURANDO'`.
O intervalo entre as linhas 56 e 80 permite a corrida.

**Recomendação:** executar a conclusão em transação e atualizar com uma
condição atômica (`WHERE id = :id AND status = 'CAPTURANDO'`), verificando
`rowCount() === 1` antes de inserir a imagem.

## Média prioridade

### SEC-002 — `me.php` pode devolver uma sessão obsoleta

`me.php` lê diretamente `$_SESSION["user"]` e não revalida o usuário no banco,
enquanto as demais rotas protegidas usam `require_session_user()`.

Se o usuário for desativado ou tiver o papel alterado depois do login, a rota
continua informando `authenticated: true` até outra chamada detectar a mudança.
Isso pode deixar a interface exibindo permissões antigas.

**Referência:** `servidor/api/me.php`, linhas 9–17.

**Recomendação:** usar `require_session_user()` na rota ou centralizar a mesma
revalidação em uma função comum.

### SEC-003 — Uploads não verificam o tipo real do arquivo

O CSV valida tamanho e processa o conteúdo como texto, e o PDF valida extensão
e o prefixo `%PDF`, mas nenhum dos dois usa `finfo`/MIME real nem
`is_uploaded_file()`.

Hoje os arquivos não são persistidos diretamente, então o impacto está
limitado ao processamento e consumo de recursos. Ainda assim, um arquivo
poliglota ou malformado pode passar pela primeira validação.

**Referências:** `servidor/api/importar_csv.php`, linhas 17–24; e
`servidor/api/importar_pdf.php`, linhas 131–145.

**Recomendação:** confirmar upload HTTP, tamanho não negativo, MIME esperado e
limite de leitura antes do parser.

## Risco operacional relacionado à segurança

### SEC-004 — Desbloqueio lógico não confirma ação física

`desbloquear_maquina.php` altera o carregamento de `EMERGENCIA` para
`PREPARANDO` depois de validar a disponibilidade do CLP, mas não cria uma
solicitação na fila industrial nem recebe confirmação do gateway.

Isso pode fazer a aplicação apresentar a máquina como liberada enquanto o CLP
continua em emergência. Em ambiente industrial, essa divergência de estado
deve ser tratada como falha de segurança operacional.

**Referência:** `servidor/api/desbloquear_maquina.php`, linhas 58–80.

**Recomendação:** separar solicitação e confirmação física, ou deixar a
transição lógica pendente até um retorno autenticado do gateway.

## Controles verificados

- Senhas com `password_hash`/`password_verify` e rehash automático.
- Regeneração do ID de sessão após login.
- Cookies `HttpOnly`, `SameSite=Strict` e `Secure` configurável.
- Limite de tentativas de login em produção.
- CSRF exigido para mutações em produção.
- Token interno comparado com `hash_equals` para gateway e câmera.
- Escopo por `company_id` nas rotas revisadas.
- Nenhuma concatenação de entrada livre encontrada em consultas SQL; as
  concatenações observadas usam inteiros previamente validados ou listas
  internas.
- Exceções não tratadas retornam mensagem genérica ao cliente; os logs
  administrativos, contudo, ainda expõem a mensagem original a perfis de
  administração.

## Limitações

Esta revisão foi estática e não incluiu teste com câmera real, CLP real ou
tráfego concorrente em produção. O projeto usa PHP no backend; a skill de
referência disponível cobre JavaScript, Python e Go, então as conclusões de PHP
foram feitas por inspeção direta do código e pelos testes locais existentes.

## Status após correção

SEC-001, SEC-002 e SEC-003 foram corrigidos. SEC-004 agora mantém o estado em
`EMERGENCIA` até o gateway autenticado confirmar `DESBLOQUEAR_MAQUINA`; somente
essa confirmação muda o carregamento para `PREPARANDO`.
