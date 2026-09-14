# API de integração — versão 1

Esta API permite conectar ERPs, TMS e sistemas de clientes ao Trace. Ela não fornece acesso ao banco de dados.

## Criar uma credencial

Um administrador da empresa cria a credencial em `POST /api/integracoes.php`. O retorno contém o campo `token` uma única vez. Guarde-o em um cofre de segredos do sistema cliente; o Trace guarda apenas um hash e não consegue exibi-lo novamente.

Permissões disponíveis:

- `produtos:read`: consulta o catálogo ativo.
- `romaneios:read`: consulta romaneios da empresa vinculada.
- `romaneios:write`: cria romaneios da empresa vinculada.

Revogue uma credencial em `DELETE /api/integracoes.php?id=ID`.

## Autenticação

Envie a chave no cabeçalho. Use sempre HTTPS.

```http
Authorization: Bearer trc_sua_chave_de_integracao
Accept: application/json
```

Cada chave pertence a uma única empresa. Não é possível informar ou trocar `company_id` pela API externa.

## Consultar produtos

```http
GET /api/v1.php?resource=produtos
```

## Consultar romaneios

```http
GET /api/v1.php?resource=romaneios
GET /api/v1.php?resource=romaneios&number=ROM-2026-001
```

## Criar romaneio

O cabeçalho `Idempotency-Key` é obrigatório. Reenvios com a mesma chave retornam a resposta original e não duplicam o romaneio.

```http
POST /api/v1.php?resource=romaneios
Authorization: Bearer trc_sua_chave_de_integracao
Content-Type: application/json
Idempotency-Key: erp-ordem-9481-2026

{
  "number": "ROM-2026-001",
  "scheduled_date": "2026-09-15",
  "plate": "ABC1D23",
  "driver_name": "João da Silva",
  "expedidor": "ERP Cliente",
  "items": [
    { "product_code": "MILHO-01", "quantity": 27500 }
  ]
}
```

Resposta de sucesso: `201` com o identificador e estado inicial `AGUARDANDO`.

## Respostas

- `401`: chave ausente, revogada ou inválida.
- `403`: chave sem a permissão necessária.
- `409`: número de romaneio já utilizado nesta empresa.
- `422`: dados inválidos.
- `429`: limite temporário de requisições excedido.

Não envie senhas de usuários, credenciais de banco ou dados de CLP pela API. Para novas integrações, habilite somente as permissões necessárias e defina uma validade para a chave.
