# Compatibilidade AbaRouter / WABA Router

Este plugin agora possui uma camada Router para substituir o AbaRouter/WABA Router dentro do WordPress.

O objetivo da camada `WAS\Router` e permitir que sistemas como o Agenda Master falem diretamente com o WordPress usando IDs internos operacionais para WABAs, numeros, rotas, templates e mensagens.

## Endpoints Principais

Saude:

- `GET /health`
- `GET /healthz`
- `GET /healthcheck`
- `GET /internal/health`

Autenticacao:

- `POST /auth/login`
- `GET /auth/me`

Cadastro incorporado:

- `POST /v1/onboarding/meta/registrations`
- `POST /v1/onboarding/meta/embedded-signup`
- `POST /v1/onboarding/bundle`

WhatsApp:

- `POST /v1/whatsapp/send`
- `GET /v1/whatsapp/phone-numbers/{phone_number_id}/status`
- `GET /v1/whatsapp/templates`
- `POST /v1/whatsapp/templates`

Webhooks Meta:

- `GET /webhooks/meta`
- `GET /webhooks/meta/verify`
- `POST /webhooks/meta`

Admin API:

- `GET /admin-api/dashboard`
- `GET|POST /admin-api/tenants`
- `DELETE /admin-api/tenants/{tenant_id}`
- `POST /admin-api/tenants/{tenant_id}/status`
- `GET|POST /admin-api/meta-apps`
- `DELETE /admin-api/meta-apps/{meta_app_id}`
- `POST /admin-api/meta-apps/{meta_app_id}/rotate-secret`
- `GET|POST /admin-api/wabas`
- `POST /admin-api/wabas/{waba_id}/sync`
- `POST /admin-api/wabas/{waba_id}/templates/sync`
- `POST /admin-api/wabas/{waba_id}/rotate-token`
- `DELETE /admin-api/wabas/{waba_id}`
- `GET|POST /admin-api/phone-numbers`
- `DELETE /admin-api/phone-numbers/{phone_number_id}`
- `GET /admin-api/phone-numbers/{phone_number_id}/templates`
- `POST /admin-api/phone-numbers/{phone_number_id}/templates/sync`
- `POST /admin-api/phone-numbers/{phone_number_id}/pause`
- `POST /admin-api/phone-numbers/{phone_number_id}/resume`
- `POST /admin-api/phone-numbers/{phone_number_id}/sync-status`
- `GET|POST /admin-api/routes`
- `PATCH|PUT /admin-api/routes/{route_id}`
- `DELETE /admin-api/routes/{route_id}`
- `POST /admin-api/routes/{route_id}/test`
- `POST /admin-api/routes/{route_id}/activate`
- `POST /admin-api/routes/{route_id}/deactivate`
- `POST /admin-api/routes/{route_id}/duplicate`
- `GET /admin-api/events`
- `POST /admin-api/events/{event_id}/replay`
- `GET /admin-api/deliveries`
- `POST /admin-api/deliveries/{delivery_id}/retry`
- `POST /admin-api/deliveries/{delivery_id}/cancel`
- `GET /admin-api/templates`
- `POST /admin-api/templates`
- `POST /admin-api/templates/sync-all`
- `GET /admin-api/templates/{template_id}`
- `PATCH|PUT /admin-api/templates/{template_id}`
- `DELETE /admin-api/templates/{template_id}`
- `GET /admin-api/outbound-messages`
- `GET /admin-api/audit`

## IDs Operacionais

O consumidor externo deve salvar os IDs internos retornados pelo plugin:

- `router_waba_id`
- `router_phone_number_id`
- `router_route_id` ou `route_id`

Esses IDs sao diferentes dos IDs Meta (`meta_waba_id`, `meta_phone_number_id`) e devem ser usados para envio, status, templates e roteamento.

`POST /admin-api/wabas/{waba_id}/sync` retorna `synced` e `phone_numbers` com os numeros sincronizados. Cada item traz `id`, `router_phone_number_id` e `phone_number_id` como ID interno do plugin, alem de `meta_phone_number_id` e `whatsapp_phone_number_id` como ID Meta. Esse formato preserva o contrato lido pelo onboarding do Agenda Master quando o Embedded Signup nao devolve o numero local imediatamente.

Nas respostas de conclusao de cadastro incorporado, `waba_id`, `phone_number_id` e `route_id` preservam o contrato antigo do WABA Router e representam IDs internos do plugin. As mesmas referencias tambem sao retornadas como `router_waba_id`, `router_phone_number_id` e `router_route_id`. Os IDs reais da Meta aparecem em `meta_waba_id`, `meta_phone_number_id` e `whatsapp_phone_number_id`.

## Autenticacao

`POST /auth/login` aceita usuario e senha de um usuario WordPress com permissao administrativa da plataforma e retorna um Bearer token.

Tambem e possivel configurar um segredo de servico via constante `WAS_ROUTER_SERVICE_SECRET` ou opcao `was_router_service_secret`. Esse segredo pode ser usado como `Authorization: Bearer <secret>`.

Erros REST mantem `error` e `message` no topo e tambem retornam `detail.error`/`detail.message` para compatibilidade com clientes que consumiam o formato do WABA Router antigo. Rejeicoes de template da Meta incluem `detail.meta_response.error`.

## Cadastro Incorporado

Fluxo esperado:

1. Sistema externo cria uma tentativa em `/v1/onboarding/meta/registrations`.
2. O usuario conclui o embedded signup da Meta.
3. Sistema externo chama `/v1/onboarding/meta/embedded-signup` com `attempt_id`, `authorization_code`, `waba_id`, `phone_number_id` e `business_id`.
4. O plugin troca o code por token, cria/atualiza WABA e numero, cria rota e notifica o `callback_url`.

Payload de conclusao enviado ao callback:

- `event_type: onboarding_completed`
- `registration_id`
- `attempt_id`
- `tenant_id`
- `company_id`
- `router_phone_number_id`
- `router_waba_id`
- `router_route_id`
- `meta_phone_number_id`
- `whatsapp_phone_number_id`
- `meta_waba_id`
- `display_phone_number`
- `route_id`
- `business_id`

Quando existir segredo de rota, o callback recebe o header `x-waba-router-secret`.

## Roteamento De Webhooks

O plugin recebe webhooks da Meta, valida a assinatura pelo Meta App associado a WABA, normaliza o evento, gera idempotencia, salva em `was_webhook_events`, aplica rotas ativas e cria entregas em `was_outbox_deliveries`.

Ao assinar a WABA na Meta, o plugin solicita explicitamente os campos `messages`, `smb_message_echoes`, `account_update`, `phone_number_quality_update`, `phone_number_name_update`, `message_template_status_update` e `template_category_update`.

Eventos `statuses` da Meta sao entregues como `event_type: message_status`, com `message_type` e `payload.status` contendo o status textual (`sent`, `delivered`, `read`, `failed` etc.). O objeto bruto do status permanece em `payload.payload`/`payload.status_payload`, incluindo `errors`, para o Agenda Master atualizar mensagens e armazenar falhas oficiais.

Cada rota pode ter:

- URL alvo
- segredo proprio
- filtros de evento
- status ativo/inativo
- timeout
- tentativas maximas
- prioridade

Entregas falhas ficam pendentes para retry ou entram em `dead_letter`.

## Templates E Status

Templates enviados pelo plugin ficam registrados em `was_message_templates` com o ID interno da WABA e, quando houver, do numero. Alem do sync manual em `GET /v1/whatsapp/templates?sync=true`, a Admin API permite importar templates existentes por WABA (`POST /admin-api/wabas/{waba_id}/templates/sync`), a partir de um numero (`POST /admin-api/phone-numbers/{phone_number_id}/templates/sync`) ou globalmente (`POST /admin-api/templates/sync-all`). O sync iniciado por numero importa o acervo da WABA sem prender cada template ao numero, preservando o comportamento do WABA Router.

Na resposta de submissao, `template_id` e `router_template_id` sao IDs internos do plugin; `id` e `provider_template_id` apontam para o ID retornado pela Meta quando disponivel.

O plugin agenda o WP-Cron `was_router_sync_template_statuses` para consultar templates `PENDING`, `IN_REVIEW` e `SUBMITTED` na Meta.

Quando um template muda para `APPROVED`, `REJECTED` ou `FAILED`, o plugin cria um evento sintético `template_status_updated`, atualiza o status local e entrega o webhook nas rotas ativas do numero/template. A idempotencia usa o template, status e ID Meta para evitar duplicidade.

## Jobs Autonomos

O plugin agenda tres rotinas via WP-Cron:

- `was_router_process_outbox`: processa entregas pendentes de webhook para destinos externos.
- `was_router_sync_template_statuses`: consulta templates pendentes na Meta e gera eventos quando mudam de status.
- `was_router_process_onboarding_reconciliation`: reprocessa cadastros incorporados pendentes, incluindo callback que falhou em 5xx, code ja usado sem token salvo, jobs `processing` antigos/travados e estados inconsistentes de notificacao.

## Testes

A camada Router possui uma suite PHP leve, portada de casos relevantes do `waba-router` e do Agenda Master:

- reutilizacao/isolamento de rotas por numero
- endpoints de saude `/health`, `/healthz`, `/healthcheck` e `/internal/health`
- variantes brasileiras de telefone no cadastro incorporado
- idempotencia de registro de onboarding
- token de embedded signup salvo antes da Meta informar WABA/numero e reutilizado no retry
- token de embedded signup persistido criptografado, sem ficar em claro nas tabelas do Router
- `authorization_code` ja usado retorna status controlado quando nao existe token salvo
- re-registro com code ja usado reutiliza token ativo da WABA existente sem nova troca OAuth
- callbacks de onboarding com sucesso, retry em 5xx, falha final em 409 e sem renotificacao duplicada
- callback de onboarding com falha 5xx e reenviado na proxima conclusao/reconciliacao
- jobs pendentes de onboarding reprocessados pelo servico de reconciliacao
- bloqueio de execucao paralela para job ja em `processing`
- retomada conservadora de job `processing` antigo para evitar cadastro travado
- reset de estado inconsistente de notificacao antes de reenviar callback
- webhook Meta `account_update` atualiza WABA/business no registro de onboarding e enfileira reconciliacao
- reaproveitamento de numero ja conectado quando a listagem de telefones da Meta falha
- numero ja conectado e movido para a WABA real antes do callback quando a listagem de telefones da Meta volta vazia
- repeticao idempotente do cadastro de numero ja conectado sem renotificar callback ja entregue
- cadastro reaberto para numero ja conectado notifica novamente sem herdar estado de notificacao anterior
- re-registro com mesmo callback reaproveita WABA, numero e rota existentes, atualizando o segredo da rota sem nova troca OAuth
- rota de onboarding existente tem segredo atualizado em repeticoes do fluxo
- troca de callback padrao desativa a rota antiga e ativa a nova rota do numero
- webhook multi-numero com entrega para a rota correta
- payload `message_received` entregue com `phone_number_id`, `route_id`, texto e `raw_payload` no contrato consumido pelo Debounce/WABA Router webhook
- idempotencia de webhook
- webhook `message_status` com status textual e erro bruto da Meta para atualizacao de outbound no Agenda Master
- `message_echo` / `smb_message_echoes` roteado para o numero interno correto
- status/categoria de template roteados por WABA/template mesmo sem `phone_number_id` na Meta
- submissao de template usando WABA interna, persistindo categoria/status/ID Meta e `phone_number_id` operacional
- payload oficial de template aninhado
- rejeicao controlada da Meta ao submeter template
- exclusao de template na Meta por `name` e `hsm_id`
- erro controlado quando a WABA nao possui token
- envio outbound de texto, midia por link, interativo e template oficial no contrato usado pelo Agenda Master/Debounce, incluindo endpoint bruto `/v1/whatsapp/send` com Bearer e bloqueio quando falta o ID interno do numero
- envio operacional de `read` e `typing_indicator` sem `to_number`, no formato oficial da Meta
- entrega de webhook externo com retry em 5xx, `dead_letter` ao atingir `max_retries`, retry administrativo imediato e cancelamento sem chamar o destino
- alias `wa_message_id` e idempotencia de envio
- sync de numeros da WABA com lista `synced`/`phone_numbers` e aliases de ID interno/Meta para o onboarding do Agenda Master
- status de numero com `connection_label`, `quality_label`, `quality_color`, `quality_score`, `checked_at` e estado `check_error` quando a consulta Meta falha
- autenticacao `/auth/login`, `/auth/me`, service secret, token invalido e retry com token fresco no contrato do Agenda Master/Cerebro
- mascaramento de `access_token`/`verify_token` em logs Meta, incluindo resposta de troca de token e URLs aninhadas
- variaveis de template posicionais/nomeadas, `parameter_format` e `quality_score`
- sync de templates com filtros oficiais da Meta e `meta_summary`
- importacao de templates ja existentes na Meta sem vinculo obrigatorio com telefone
- atalhos administrativos para sync de templates por numero e global
- polling de status de templates pendentes contra a Meta
- webhook unico quando um template pendente passa para aprovado/rejeitado/falha
- webhook `phone_number_status_changed` com payload operacional longo para o Agenda Master
- `account_update` roteado como evento operacional de onboarding, com idempotencia e sem entrega indevida para rotas de telefone
- `phone_number_quality_update` e `phone_number_name_update` roteados pelo `phone_number_id` enviado no `value` da Meta
- assinatura de WABA solicita campos oficiais de mensagens, ecos SMB, onboarding/account update, qualidade/nome de numero e templates
- sufixos de arquivos de midia por MIME oficial da Meta
- midia inbound com `download_url`, metadados Meta e armazenamento opcional em uploads do WordPress
- audio em `smb_message_echoes` enriquecido no resumo `media` e no payload aninhado `message_echoes`
- CRUD administrativo de template com menu por numero
- conflito de rota ativa duplicada e bloqueio de reativacao duplicada
- bundle completo de onboarding com tenant, Meta App, WABA, numero e rota

Execute:

```bash
php tests/router/run.php
```
