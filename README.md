# WordPress Meta App Approval Helper

Este repositório contém um plugin/projeto para WordPress criado para apoiar o processo de aprovação de um aplicativo oficial da Meta para WhatsApp Business.

O objetivo principal é oferecer uma base já estruturada para que a instalação WordPress consiga atender aos fluxos exigidos durante a revisão da Meta, incluindo páginas legais, endpoints de callback, webhooks e telas administrativas necessárias para configurar e validar a integração.

## Objetivo

Ajudar pessoas e empresas a prepararem o ambiente WordPress até a aprovação do aplicativo oficial da Meta.

O projeto já inclui os endpoints necessários para a integração e revisão. Depois da instalação, o foco passa a ser configurar corretamente as variáveis e credenciais no próprio servidor WordPress e seguir o processo de aprovação dentro do painel da Meta.

## Camada Router

Além do apoio à aprovação da Meta, o plugin agora possui uma camada de compatibilidade para substituir o AbaRouter/WABA Router dentro do WordPress. Essa camada expõe endpoints como `/auth/login`, `/v1/onboarding/*`, `/v1/whatsapp/*`, `/admin-api/*` e `/webhooks/meta`, permitindo cadastro incorporado, multi-WABA, envio oficial, roteamento de webhooks, sync de templates, outbox e retry.

Veja [ROUTER_COMPATIBILITY.md](ROUTER_COMPATIBILITY.md) para o contrato de API e o fluxo esperado pelo Agenda Master e outros sistemas externos.

Testes da camada Router:

```bash
php tests/router/run.php
```

## Configuração

1. Instale o projeto no ambiente WordPress.
2. Configure as variáveis, credenciais e URLs exigidas diretamente no servidor ou no painel administrativo do WordPress.
3. Ajuste as páginas legais, domínio, callbacks e webhooks conforme o aplicativo cadastrado na Meta.
4. Valide se os endpoints públicos estão acessíveis via HTTPS.
5. Envie o aplicativo para revisão/aprovação na Meta.

## Observações

- Não inclua credenciais reais, tokens, secrets, CPF, CNPJ ou dados pessoais no repositório.
- Mantenha as configurações sensíveis somente no servidor, banco de dados ou painel administrativo seguro do WordPress.
- Os endpoints já estão estruturados; a aprovação depende da configuração correta do aplicativo, domínio, permissões e informações exigidas pela Meta.
