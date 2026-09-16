# Servidor existente — referência recuperada do histórico

- Provedor: Oracle Cloud, região São Paulo.
- IP atual confirmado na console Oracle: `136.248.115.155`.
- IP histórico anterior: `147.15.3.80` (não usar como destino atual).
- Usuário SSH atual informado pela Oracle: `opc`.
- Usuário histórico da instância Ubuntu anterior: `ubuntu`.
- Site observado no navegador: `https://trace.santocloud.com.br`.
- Repositório de implantação: `VItoraaraujoo/Dallogix-Trace-System`.

A conversa arquivada **Summarize software project structure**
(`01a08324-f811-7db0-9f34-cd9824aa9446`) registra acesso SSH bem-sucedido,
instalação do htop e deploy concluído após o PR #14. Também registra uma
instância Ubuntu 24.04, shape VM.Standard.E2.1.Micro, aproximadamente 1 GB de
RAM e disco de 45 GB. Esses recursos são históricos, não uma medição atual.

Em 11/09/2026, a verificação desta tarefa encontrou timeout no SSH e HTTP
para esse IP. A aba do site mostrava erro 524 e a console Oracle mostrava
sessão desconectada. Isso não permite determinar se a causa é a VM, rede,
firewall ou serviço da aplicação; exige recuperar acesso administrativo.

O ambiente `https://localhost:8443` é uma homologação separada neste Mac.
Seus testes aprovados não comprovam disponibilidade nem atualizam o servidor
Oracle. Não confundir o domínio raiz com o subdomínio `trace`.

Nova consulta em 11/09/2026: sessão Oracle restabelecida. Instância
`dallogix-trace-prod-temp` em estado Executando, 1 OCPU e 1 GB de RAM.
O registro DNS A de `trace.santocloud.com.br` já aponta para
`136.248.115.155`, com proxy Cloudflare ativo. HTTP e SSH no IP atual
também expiraram; SSH alcançou a fase de troca de banner, sem completá-la.

A instância atual foi criada em 11/09/2026 e usa Oracle Linux 9.8.
Não confundir com a instância Ubuntu descrita no histórico.

Após a reinicialização autorizada, o servidor voltou a responder. Em
11/09/2026, foram conferidos os serviços MySQL, PHP, Nginx, Node-RED e
Modbus virtual; o healthcheck local e o público retornaram HTTP 200. O banco
recebeu backup antes da sincronização e as credenciais do banco e tokens
internos foram trocados por valores aleatórios. A aplicação está em
`APP_ENV=production`, com `APP_URL=https://trace.santocloud.com.br` e sessões
seguras.

O favicon SVG, o manifesto e os links de identidade visual foram publicados
em `/opt/dallogix-trace/interface`. A validação pública confirmou o arquivo
`/favicon.svg`, o manifesto com `application/manifest+json` e o HTML com os
links de favicon. A credencial inicial do administrador fica no servidor em
`/opt/dallogix-trace/armazenamento/credenciais-admin-temporarias.txt`, com
permissão restrita; deve ser trocada após o primeiro acesso.

O usuário Master global `master@dallogix.local` também está criado com
`company_id = NULL` e perfil `ADMIN_DALLOGIX`. A senha temporária fica em
`/opt/dallogix-trace/armazenamento/credenciais-master-temporarias.txt`, também
com permissão restrita; deve ser trocada após o primeiro acesso.

O login usa `POST` como proteção de fallback e a tela pública não consulta a
sessão antes da autenticação, evitando um `401` esperado no console. Os módulos
JavaScript recebem `data-cfasync="false"`, evitando que o Rocket Loader os
envolva. A CSP permite o beacon e o script de detecção injetado pelo Cloudflare
somente junto às origens necessárias; os PNGs da identidade visual estão com
permissão de leitura para o Nginx.

O teste de CLP e câmera físicos ainda depende de informar o endereço do CLP,
o protocolo e as credenciais/endereço da câmera na estação da esteira. O
container Modbus virtual está saudável, mas não substitui essa validação.
