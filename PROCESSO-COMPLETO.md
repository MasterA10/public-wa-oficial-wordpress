# Cadastro incorporado de WhatsApp Cloud API em um único SaaS

Este documento define como um único aplicativo SaaS deve implementar, ponta a ponta, o cadastro incorporado do WhatsApp pela API oficial da Meta (Embedded Signup).

O mesmo backend do SaaS é responsável por:

- autorizar o usuário e associar a conexão à empresa correta;
- iniciar a jornada de Embedded Signup;
- receber e validar o retorno OAuth;
- trocar o authorization code por access token;
- descobrir, validar e salvar a WABA e o número;
- registrar e validar webhooks da Meta;
- enviar mensagens pela Graph API;
- operar várias empresas, WABAs e números sem mistura de dados.

Não é necessário depender de outro produto para concluir o cadastro. Um serviço interno separado pode ser criado no futuro, mas as responsabilidades descritas aqui continuam as mesmas.

> Este fluxo usa a API oficial WhatsApp Cloud API. Dados privados, como app secret, access token, authorization code e tokens de sessão, nunca podem passar pelo frontend, aparecer em logs ou ser devolvidos por APIs do SaaS.

## Resultado final

Uma conexão concluída precisa produzir, para uma empresa do SaaS:

| Dado | Origem | Finalidade |
| --- | --- | --- |
| tenant_id e company_id | SaaS | Isolamento e autorização do cliente. |
| meta_app_id | SaaS | Qual App Meta realizou o onboarding. |
| business_id | Meta | Business Manager, quando disponível. |
| waba_id | Meta | WhatsApp Business Account vinculada. |
| phone_number_id | Meta | ID usado para enviar mensagens e identificar webhooks. |
| display_phone_number | Meta | Telefone para exibição humana. |
| verified_name | Meta | Nome verificado, quando fornecido. |
| access_token criptografado | Meta | Credencial operacional da WABA. |
| status e timestamps | SaaS | Auditoria, suporte e controle de operação. |

O telefone formatado não é uma chave suficiente. O identificador técnico do número é phone_number_id. Uma WABA pode possuir vários números; cada número conectado precisa ter seu próprio registro e rota lógica dentro do SaaS.

## Papéis

| Papel | Responsabilidade |
| --- | --- |
| Administrador do cliente | Decide conectar o número e conclui as telas da Meta. |
| Frontend SaaS | Inicia a tentativa e abre a URL recebida do backend. Não manipula segredos. |
| Backend SaaS | Controla tentativa, state, OAuth, Graph API, persistência, webhooks e envio. |
| Meta | Hospeda o Embedded Signup, devolve o código OAuth e expõe a Graph API. |

## Visão do fluxo completo

~~~mermaid
sequenceDiagram
    autonumber
    actor U as Administrador
    participant FE as Frontend SaaS
    participant API as Backend SaaS
    participant DB as Banco SaaS
    participant M as Meta / Graph API

    U->>FE: Conectar WhatsApp
    FE->>API: POST /api/whatsapp/onboarding/start
    API->>DB: Cria tentativa, state hash e validade
    API-->>FE: authorization_url e attempt_id
    FE->>M: Abre URL em popup ou redireciona
    U->>M: Conclui Embedded Signup
    M->>API: GET callback com code e state
    API->>DB: Valida/consome state e marca callback_received
    API->>M: Troca code por access token
    API->>M: Consulta WABA e phone numbers
    API->>DB: Upsert WABA, número e conexão
    M->>API: Webhooks futuros
    API-->>FE: Consulta de status: completed
~~~

## IDs e termos

| Nome | Dono | Regra |
| --- | --- | --- |
| tenant_id | SaaS | Cliente/conta isolada dentro do produto. |
| company_id | SaaS | Empresa, filial ou unidade que usará o número. |
| attempt_id | SaaS | UUID público de uma única tentativa de onboarding. |
| state | SaaS | Segredo temporário anti-CSRF, de uso único. |
| App ID / client_id | Meta | Identificador público da aplicação Meta. |
| config_id | Meta | Configuração de Embedded Signup. |
| business_id | Meta | Identificador do Business Manager. |
| waba_id | Meta | Identificador da WhatsApp Business Account. |
| phone_number_id | Meta | Identificador do número na Graph API. |

Não armazene IDs internos e IDs da Meta no mesmo campo. Um erro comum em sistemas multiempresa é tratar waba_id como se fosse phone_number_id; mensagens são enviadas pelo phone_number_id.

## Pré-requisitos na Meta

Antes de liberar o recurso no SaaS, a equipe técnica deve configurar:

1. Uma aplicação Meta com o produto WhatsApp e acesso à Cloud API.
2. Uma configuração de Embedded Signup, cujo config_id será usado na jornada.
3. A URL HTTPS exata de callback OAuth do SaaS.
4. O endpoint de webhook HTTPS do SaaS e a validação de assinatura.
5. A versão da Graph API usada pelo produto.
6. As permissões e o modo de publicação exigidos pela Meta para o ambiente.

A URL de callback é comparada literalmente no OAuth. Protocolo, domínio, porta, caminho e barra final devem ser os mesmos:

~~~text
https://saas.exemplo.com/api/oauth/meta/callback
~~~

Use esse mesmo valor:

- na configuração do App Meta;
- na URL inicial do Embedded Signup;
- na chamada que troca o code por access token;
- no registro persistido da tentativa.

## Configuração do SaaS

Use variáveis de ambiente ou um cofre de segredos. Não deixe valores reais em repositório, seed, documentação pública ou painel administrativo.

~~~env
META_APP_ID=<app-id-publico>
META_APP_SECRET=<segredo-do-app>
META_EMBEDDED_SIGNUP_CONFIGURATION_ID=<config-id>
META_EMBEDDED_SIGNUP_BASE_URL=https://www.facebook.com/v<versao>/dialog/oauth?...
META_ONBOARDING_REDIRECT_URI=https://saas.exemplo.com/api/oauth/meta/callback
META_GRAPH_API_BASE_URL=https://graph.facebook.com
META_GRAPH_API_VERSION=v<versao>

META_WEBHOOK_VERIFY_TOKEN=<segredo-para-verificacao>
META_WEBHOOK_APP_SECRET=<segredo-para-assinatura>
ENCRYPTION_KEY=<chave-para-criptografar-tokens-em-repouso>
~~~

O app secret serve para trocar o code no servidor. Ele nunca pode ser colocado no JavaScript, em aplicativo móvel, em URL, em corpo de resposta ou em chamada do navegador.

## Modelo de dados obrigatório

A jornada deve continuar mesmo se o usuário fechar o popup. Por isso, salve o estado em banco.

~~~text
onboarding_attempts
- id UUID                         # attempt_id
- tenant_id, company_id, meta_app_id
- state_hash
- phone_number_normalized
- phone_number_variants JSON
- redirect_uri
- authorization_url opcional
- status
- expires_at
- callback_received_at, completed_at
- meta_business_id, meta_waba_id, meta_phone_number_id
- authorization_code_encrypted opcional
- authorization_code_hash opcional
- last_error, created_at, updated_at

whatsapp_wabas
- id interno
- tenant_id, meta_app_id
- meta_business_id, meta_waba_id
- access_token_encrypted
- token_exchanged_at, status

whatsapp_phone_numbers
- id interno
- tenant_id, company_id, waba_id interno
- meta_phone_number_id
- display_phone_number, verified_name
- status, connected_at
~~~

Restrições recomendadas:

- unicidade global de (meta_app_id, meta_waba_id);
- unicidade global de (meta_app_id, meta_phone_number_id);
- uma conexão ativa por (tenant_id, company_id, meta_phone_number_id);
- um state hash por tentativa;
- uma tentativa aberta por empresa/número, quando esta for a regra do produto.

Estados sugeridos:

~~~text
created -> redirected -> callback_received -> reconciling -> completed
                                      \-> failed | expired | cancelled
~~~

Completed é terminal. Repetir callback, webhook ou job deve ser seguro e não criar WABA, número ou token duplicados.

## 1. Iniciar o onboarding

O frontend chama o backend autenticado, nunca a Meta diretamente com dados sensíveis.

~~~http
POST /api/whatsapp/onboarding/start
Content-Type: application/json
~~~

~~~json
{
  "company_id": "c2a4f25e-9bbf-4f86-8d7e-d44d4bf5106f",
  "phone_number": "+55 (11) 99999-9999",
  "return_url": "https://saas.exemplo.com/configuracoes/whatsapp"
}
~~~

O backend executa, nesta ordem:

1. Confirma que o usuário possui permissão para administrar a empresa informada.
2. Deriva o tenant do usuário autenticado; nunca aceita um tenant arbitrário do navegador.
3. Normaliza o telefone para busca e aplica as regras locais de duplicidade. No Brasil, pode guardar variantes com e sem o nono dígito para correlacionar dados legados.
4. Verifica se o telefone já está conectado a outra empresa. Transferência de propriedade deve ser fluxo administrativo explícito e auditado.
5. Cria uma tentativa com validade curta, por exemplo 15 minutos.
6. Gera state criptograficamente seguro, com pelo menos 128 bits de entropia.
7. Salva SHA-256(state), e não o state em texto puro.
8. Cria a URL de autorização com o state e redirect_uri desta tentativa.
9. Marca a tentativa como redirected e devolve a URL ao frontend.

Resposta:

~~~json
{
  "attempt_id": "3dd5fcec-762d-4c37-a901-c1b802622b7e",
  "status": "redirected",
  "authorization_url": "https://www.facebook.com/..."
}
~~~

O frontend abre authorization_url somente como consequência direta do clique do usuário. Isso reduz bloqueio de popups. A página principal deve consultar o status da tentativa e não concluir que a operação falhou apenas porque o popup fechou.

## 2. Montar a URL do Embedded Signup

A URL-base contém dados públicos da configuração Meta. O backend deve preservar os parâmetros definidos para a jornada e substituir state e redirect_uri a cada tentativa.

Parâmetros comuns:

| Parâmetro | Uso |
| --- | --- |
| client_id | App ID público da Meta. |
| config_id | Configuration ID do Embedded Signup. |
| display=popup | Solicita apresentação em popup quando suportado. |
| response_type=code | Solicita o authorization code. |
| override_default_response_type=true | Mantém a resposta em code quando requerido pela configuração. |
| extras | JSON codificado com parâmetros da jornada WhatsApp. |
| redirect_uri | Callback do backend SaaS. |
| state | Segredo único ligado à tentativa. |

Pseudocódigo seguro:

~~~python
state = secrets.token_urlsafe(32)
attempt.state_hash = sha256(state.encode()).hexdigest()

url = parse_url(META_EMBEDDED_SIGNUP_BASE_URL)
params = url.query_params
params["state"] = state
params["redirect_uri"] = attempt.redirect_uri
authorization_url = url.with_query(params)
~~~

Não aceite do frontend valores para client_id, config_id, redirect_uri de produção, escopos ou extras. O backend escolhe a configuração permitida para o tenant e ambiente.

## 3. Callback OAuth e validação do state

Após a jornada, a Meta redireciona o navegador:

~~~http
GET /api/oauth/meta/callback?code=<authorization_code>&state=<state>
~~~

Em cancelamento ou erro, podem vir error, error_reason e error_description. O endpoint deve devolver uma página simples para o usuário e executar a lógica no servidor.

Validação obrigatória:

1. Se existir code ou error, exija state.
2. Calcule SHA-256(state) e procure uma tentativa não expirada.
3. Verifique se a tentativa pertence ao Meta App esperado e ainda não foi cancelada.
4. Faça a transição de estado de forma atômica. Dois callbacks simultâneos não podem consumir a mesma tentativa duas vezes.
5. Se já estiver completed, responda de modo idempotente.
6. Em caso de erro da Meta, marque failed com uma causa segura para suporte e permita criar nova tentativa.

O state é a defesa contra CSRF e contra associar a resposta de um usuário à empresa errada. Não tente identificar a empresa apenas pelo code ou telefone.

O code é curto, de uso único e sensível. Não o coloque em log, analytics, HTML, URL subsequente ou fila sem criptografia. Se um worker precisar usá-lo, guarde o valor criptografado e guarde o hash para auditoria e idempotência.

## 4. Troca de authorization code por access token

A troca ocorre exclusivamente no backend do SaaS.

~~~http
GET https://graph.facebook.com/<versao>/oauth/access_token
~~~

Parâmetros:

~~~text
client_id=<META_APP_ID>
client_secret=<META_APP_SECRET>
redirect_uri=<EXATAMENTE_A_URI_PERSISTIDA_NA_TENTATIVA>
code=<CODE_RECEBIDO_NO_CALLBACK>
~~~

Pseudocódigo:

~~~python
response = http.get(
    f"{META_GRAPH_API_BASE_URL}/{META_GRAPH_API_VERSION}/oauth/access_token",
    params={
        "client_id": meta_app.app_id,
        "client_secret": decrypt(meta_app.app_secret_encrypted),
        "redirect_uri": attempt.redirect_uri,
        "code": authorization_code,
    },
    timeout=10,
)
access_token = response.json()["access_token"]
~~~

Após sucesso:

- criptografe access_token em repouso;
- registre token_exchanged_at e o hash do code;
- associe o token à WABA somente depois de confirmar o waba_id;
- masque o token em qualquer painel;
- não trate token como credencial de login de um usuário do SaaS.

Se a Meta responder que o code já foi utilizado, só é seguro reaproveitar token já salvo para a mesma tentativa ou para a mesma WABA já validada. Se não houver token persistido, marque a tentativa como falha e reinicie o onboarding. Não tente trocar o mesmo code repetidamente.

## 5. Descobrir WABA e número

O callback por URL direta pode conter apenas code e state. Portanto, depois de trocar o token, o backend precisa obter ou confirmar os recursos autorizados.

A fonte pode ser:

1. dados devolvidos pela jornada de Embedded Signup quando ela disponibilizar business_id, waba_id e phone_number_id; e/ou
2. consultas server-side à Graph API com o token obtido, incluindo debug_token quando necessário para identificar os alvos concedidos em whatsapp_business_management, seguida da consulta da WABA e dos seus phone numbers.

O backend deve validar que o waba_id é autorizado pelo token antes de persistir. Para encontrar o número:

1. se houver phone_number_id confiável retornado pela Meta, use-o;
2. caso contrário, consulte os números da WABA;
3. compare o telefone normalizado com o telefone pré-registrado;
4. se houver zero resultados, mantenha reconciling e tente novamente mais tarde;
5. se houver mais de um resultado, exija seleção explícita ou intervenção; nunca use o primeiro item retornado pela API.

Depois faça upsert, em transação:

~~~text
WABA local
  <- tenant, Meta App, business_id, meta_waba_id, token criptografado

Número local
  <- empresa, WABA local, meta_phone_number_id, telefone exibido, nome verificado

Conexão operacional
  <- empresa, número local, status active, dados de auditoria
~~~

A reconciliação pode ser assíncrona. Não mantenha o callback HTTP aberto esperando propagação da Meta.

## 6. Ativar a conexão e expor status

Depois de persistir WABA e número, marque a tentativa como completed e atualize a conexão da empresa em uma única transação. Um endpoint de consulta pode ser:

~~~http
GET /api/whatsapp/onboarding/attempts/<attempt_id>
~~~

~~~json
{
  "attempt_id": "3dd5fcec-762d-4c37-a901-c1b802622b7e",
  "status": "completed",
  "connection": {
    "phone_number_id": "123456789012345",
    "display_phone_number": "+55 11 99999-9999",
    "verified_name": "Empresa Exemplo"
  }
}
~~~

O frontend consulta esse endpoint enquanto o popup estiver aberto e por algum tempo depois. O endpoint deve respeitar tenant e company_id do usuário autenticado.

## 7. Webhook oficial da Meta

O mesmo SaaS deve receber os eventos da Meta em endpoint público HTTPS, por exemplo:

~~~text
/webhooks/meta
~~~

Implemente os dois fluxos:

1. Verificação inicial: trate GET e responda hub.challenge somente quando hub.verify_token corresponder ao segredo configurado.
2. Eventos: trate POST, valide a assinatura da Meta sobre o corpo bruto antes de desserializar ou processar e responda rapidamente com 2xx após persistir/enfileirar.

Para cada evento recebido:

- valide assinatura e timestamp quando aplicável;
- guarde o payload bruto de forma protegida e com retenção definida;
- use o identificador do evento/mensagem para deduplicar;
- encontre o número pelo phone_number_id do metadata;
- derive tenant e empresa a partir da conexão daquele número;
- processe ou coloque em fila para entrega interna;
- nunca escolha tenant por campo enviado pelo navegador ou pela mensagem do cliente.

Webhooks de onboarding e webhooks de mensagens são diferentes. O onboarding cria a conexão; os demais eventos usam a conexão já criada para rotear mensagens, status e atualizações.

## 8. Assinatura dos webhooks da Meta

O token de verificação usado no GET de inscrição não protege os eventos de
produção. Cada POST da Meta precisa ser autenticado pelo cabeçalho
`X-Hub-Signature-256`, normalmente recebido como:

~~~text
x-hub-signature-256: sha256=<hexadecimal>
~~~

O SaaS calcula HMAC-SHA256 sobre os **bytes brutos** do corpo HTTP usando o
App Secret da aplicação Meta que possui a WABA/número do evento:

~~~python
expected = hmac.new(
    meta_app.app_secret.encode("utf-8"),
    raw_request_body,
    hashlib.sha256,
).hexdigest()

valid = hmac.compare_digest(
    f"sha256={expected}",
    request.headers.get("x-hub-signature-256", ""),
)
~~~

Regras de implementação:

1. Leia e preserve o corpo bruto uma única vez antes de transformar JSON.
2. Extraia o `phone_number_id` do `metadata` do evento para localizar o
   número local, a WABA e o Meta App que fornecem o App Secret correto.
3. Para eventos de template, que podem não ter `phone_number_id`, encontre
   primeiro a WABA pelo `entry.id` e use o Meta App dessa WABA.
4. Só desserialize, persista como evento operacional ou entregue para rotas
   depois de a assinatura ser válida. Cabeçalho ausente, formato diferente de
   `sha256=<digest>`, algoritmo diferente ou comparação falha devem resultar
   em `401` ou `403`.
5. Use `hmac.compare_digest`; não compare strings com `==`.

O aplicativo mantém no evento o campo `signature_valid` para auditoria e
diagnóstico. No código atual, a assinatura é calculada e esse resultado é
persistido com o evento; antes de operar em produção, a camada de entrada deve
usar o resultado para interromper o processamento de assinaturas inválidas.
Esse campo não substitui a decisão de segurança: um evento inválido pode ser
registrado em um log de segurança separado, mas não pode disparar rota,
automação, atualização de template ou mudança de status.

Para a verificação inicial da Meta, o GET só deve devolver
`hub.challenge` quando `hub.mode=subscribe` e
`hub.verify_token` corresponderem ao token configurado para algum Meta App
ativo. Esse `verify_token` é diferente do App Secret e deve ser rotacionável.

## 9. Rotas por número

Uma rota é o destino interno que receberá eventos de **um número específico**.
Ela não é associada só ao tenant nem só à WABA: a associação obrigatória é com
o registro local de `phone_number_id`. Assim, dois números da mesma empresa
podem alimentar CRMs, filas ou automações diferentes.

Modelo de rota:

~~~text
routes
- id
- tenant_id
- phone_number_id interno
- name
- target_url
- secret_encrypted
- event_filters_json
- is_active, status
- timeout_ms, max_retries, priority
~~~

Ao finalizar o onboarding, o SaaS cria ou atualiza ao menos uma rota ativa para
o número conectado. Uma restrição de unicidade recomendada é:

~~~text
(tenant_id, phone_number_id, target_url) onde is_active = true
~~~

Exemplo de API administrativa interna:

~~~http
POST /admin-api/routes
Content-Type: application/json
~~~

~~~json
{
  "tenant_id": 42,
  "phone_number_id": 202,
  "name": "Automação comercial",
  "target_url": "https://saas.exemplo.com/internal/whatsapp/comercial",
  "secret": "<segredo-da-rota>",
  "event_filters_json": {
    "event_types": ["message_received", "message_status"],
    "message_types": ["text", "interactive", "image"]
  },
  "timeout_ms": 8000,
  "max_retries": 3,
  "priority": 100
}
~~~

Quando a Meta envia um webhook, o aplicativo:

1. normaliza cada mensagem, status ou alteração em um evento separado;
2. deduplica pelo identificador natural do evento, incluindo WABA,
   `phone_number_id`, tipo, ID da mensagem, origem e índice do item;
3. localiza o número local por `metadata.phone_number_id`;
4. confirma que número e tenant estão ativos;
5. busca somente rotas ativas desse número, em ordem de `priority`;
6. aplica os filtros de `event_types` e `message_types`;
7. cria uma entrega na Outbox para cada rota compatível.

Isso permite múltiplas rotas para o mesmo número sem envio síncrono em cascata.
O evento é persistido antes das entregas; o worker da Outbox faz o POST para o
destino, usa o timeout da rota e considera qualquer resposta `2xx` como
entregue. Timeout, erro de rede ou resposta fora de `2xx` criam nova tentativa
até `max_retries`; depois disso, a entrega vai para dead letter e pode ser
reprocessada pelo painel administrativo.

O corpo enviado a uma rota é encapsulado para que o destino conheça a origem:

~~~json
{
  "event_id": 10,
  "delivery_id": 25,
  "event_type": "message_received",
  "message_type": "text",
  "wa_message_id": "wamid.EXEMPLO",
  "wa_from": "5511999999999",
  "phone_number_id": 202,
  "route_id": 303,
  "payload": {
    "phone_number_id": "123456789012345",
    "display_phone_number": "+55 11 99999-9999"
  },
  "raw_payload": {}
}
~~~

Quando a rota possui segredo, o worker o mantém criptografado em repouso e o
envia ao destino em:

~~~text
x-waba-router-secret: <segredo-da-rota>
~~~

O destino deve comparar esse valor em tempo constante ou, preferencialmente,
evoluir o contrato para HMAC com timestamp e identificador da entrega. A rota
não deve confiar em `tenant_id` vindo do seu próprio payload; ela deve usar o
`phone_number_id`/ID de conexão já associado internamente.

## 10. Templates de mensagem

Templates pertencem à WABA na Meta, e não a um telefone individual. No SaaS,
o registro local usa a chave `(waba_id interno, name, language)` e pode ter
`phone_number_id` opcional apenas para indicar em qual número devem ser
exibidos, enviados ou para onde devem seguir notificações de status.

Modelo local:

~~~text
waba_templates
- id, tenant_id, waba_id interno, phone_number_id opcional
- meta_template_id
- name, language, category, status
- components_json
- rejection_reason
- last_status_check_at, approved_at, approved_notified_at
~~~

### Criar e submeter para aprovação

O usuário do SaaS solicita a criação para uma WABA à qual tem acesso. O backend
resolve o token criptografado dessa WABA e chama a API oficial:

~~~http
POST /<versao-graph>/<meta_waba_id>/message_templates
Authorization: Bearer <access-token-da-waba>
Content-Type: application/json
~~~

Exemplo de payload que o backend envia à Meta:

~~~json
{
  "name": "lembrete_agendamento",
  "language": "pt_BR",
  "category": "UTILITY",
  "components": [
    {
      "type": "BODY",
      "text": "Olá {{1}}, seu agendamento está confirmado para {{2}}.",
      "example": {
        "body_text": [["João", "10/06 às 14h"]]
      }
    }
  ]
}
~~~

Em resposta de sucesso, o SaaS faz upsert do template com o ID retornado pela
Meta e o status inicial, normalmente `SUBMITTED` ou `PENDING`. O status
`APPROVED` é a condição necessária para uso em produção; apresente
`rejection_reason` quando houver reprovação. Guarde os componentes para
validar, no momento do envio, a quantidade/formato dos parâmetros de cabeçalho,
corpo, botões e mídia.

### Sincronizar e receber mudanças de status

No carregamento da tela, sob demanda administrativa e por tarefa periódica, o
SaaS sincroniza:

~~~http
GET /<versao-graph>/<meta_waba_id>/message_templates?fields=id,name,language,category,status,rejected_reason,components
Authorization: Bearer <access-token-da-waba>
~~~

A sincronização percorre todas as páginas da resposta e faz upsert por WABA,
nome e idioma. Templates em `LOCAL`, `SUBMITTED`, `PENDING` ou
`IN_REVIEW` precisam ser reconsultados periodicamente; uma cadência de cinco
minutos é uma referência razoável, ajustável por configuração.

A Meta também pode publicar os campos
`message_template_status_update` e `message_template_category_update`.
O SaaS normaliza esses eventos, encontra o template primeiro por
`meta_template_id` e, se necessário, por nome + idioma, atualiza o status,
categoria e motivo de rejeição e registra `approved_at` na primeira aprovação.

Se o template tiver `phone_number_id` local, a notificação de status segue as
rotas desse número. Se não tiver, ela segue as rotas ativas de todos os números
ativos da mesma WABA. Para evitar notificações duplicadas, a transição para
`APPROVED` deve ser entregue uma vez por template, controlada por
`approved_notified_at`.

### Enviar um template aprovado

O endpoint de negócio recebe um ID interno de conexão, valida tenant/empresa,
resolve o `phone_number_id` Meta e monta o formato oficial:

~~~http
POST /<versao-graph>/<meta_phone_number_id>/messages
Authorization: Bearer <access-token-da-waba>
Content-Type: application/json
~~~

~~~json
{
  "messaging_product": "whatsapp",
  "to": "5511999999999",
  "type": "template",
  "template": {
    "name": "lembrete_agendamento",
    "language": {
      "code": "pt_BR"
    },
    "components": []
  }
}
~~~

O frontend fornece nome, idioma e valores dos parâmetros, mas o backend valida
que o template está aprovado, pertence à WABA do número e possui componentes
compatíveis. O cliente do SaaS não escolhe livremente o token, WABA ou
`meta_phone_number_id`.

## 11. Download de mídia e URL pública temporária

Mensagens recebidas com `audio`, `document`, `image`, `sticker` ou
`video` não trazem o arquivo no webhook. A Meta envia metadados e um
`media.id`. O SaaS usa esse ID para entregar ao sistema de destino uma URL
de download.

### Quando o download ocorre

O aplicativo não baixa o arquivo durante o recebimento do webhook. Primeiro,
ele persiste o evento, encontra as rotas do número e cria as entregas na
Outbox. Imediatamente antes de uma entrega ser enviada a uma rota, o worker:

1. lê o `media.id` e obtém o token criptografado da WABA do número;
2. chama `GET /<versao-graph>/<media-id>` na Graph API com
   `Authorization: Bearer <token-da-waba>`;
3. recebe da Meta uma URL de download curta;
4. dependendo da configuração de storage, entrega essa URL da Meta ou baixa o
   arquivo e publica uma URL pré-assinada no storage próprio;
5. atualiza o evento normalizado antes de fazer o POST para a rota.

Essa escolha deixa o recebimento do webhook rápido e permite que cada rota
receba a mídia pronta para consumo. Como consequência, um evento sem rota ativa
ou que ainda não tenha sido processado pelo worker não terá URL de download
resolvida.

### Sem storage próprio

Com `MEDIA_STORAGE_ENABLED=false` — o padrão — o aplicativo inclui no
payload a URL temporária retornada pela Meta:

~~~json
{
  "media": {
    "id": "<media-id>",
    "meta_download_url": "https://lookaside.fbsbx.com/...",
    "download_url": "https://lookaside.fbsbx.com/...",
    "url_status": "resolved",
    "meta_url_expires_in_seconds": 300,
    "url_requires_authorization": true,
    "storage_status": "not_enabled"
  }
}
~~~

Essa URL não é um link público para o navegador do usuário: ela exige o Bearer
token da WABA e expira, no comportamento atual, em aproximadamente cinco
minutos. O SaaS não deve repassar esse token ao frontend nem pedir que outra
aplicação use essa URL diretamente.

### Com S3 ou MinIO

Para disponibilizar um link acessível sem expor o token Meta, configure:

~~~env
MEDIA_STORAGE_ENABLED=true
MEDIA_STORAGE_TTL_SECONDS=600
MEDIA_STORAGE_DOWNLOAD_TIMEOUT_SECONDS=30
S3_ENDPOINT_URL=https://s3-interno.exemplo.com
S3_PUBLIC_ENDPOINT_URL=https://s3-publico.exemplo.com
S3_ACCESS_KEY_ID=<access-key>
S3_SECRET_ACCESS_KEY=<secret-key>
S3_BUCKET=whatsapp-media
S3_REGION=us-east-1
S3_ADDRESSING_STYLE=path
~~~

O worker baixa os bytes da URL Meta usando o token da WABA, determina a
extensão pelo nome do documento ou MIME type, grava o objeto e gera uma URL
pré-assinada de leitura. A chave do objeto isola tenant e número:

~~~text
whatsapp-media/
  tenant-<tenant-id>/
    phone-<id-interno-do-numero>/
      <meta-phone-number-id>/
        AAAA/MM/DD/HH/
          <media-id>.<extensao>
~~~

O payload entregue à rota passa a conter:

~~~json
{
  "media": {
    "id": "<media-id>",
    "download_url": "https://s3-publico.exemplo.com/...?X-Amz-Signature=...",
    "url_status": "stored",
    "url_requires_authorization": false,
    "storage_status": "stored",
    "storage_backend": "s3",
    "storage_bucket": "whatsapp-media",
    "storage_key": "whatsapp-media/tenant-42/...",
    "file_size": 45678,
    "content_sha256": "<sha256-do-arquivo>"
  }
}
~~~

O termo “link público” aqui significa URL pré-assinada: o bucket continua
privado, mas qualquer pessoa que possuir a URL pode baixá-la até o vencimento.
O prazo padrão é 600 segundos e é controlado por
`MEDIA_STORAGE_TTL_SECONDS`. Use uma duração curta e gere nova URL por um
endpoint autenticado se o usuário precisar consultar mídia antiga.

O aplicativo preserva tanto o bloco `payload.media` normalizado quanto o
objeto de mídia aninhado na estrutura original de mensagens ou ecos dentro do
payload normalizado, facilitando o consumo por aplicações que usam qualquer um
dos formatos.

### Limites e cuidados operacionais

- O download só ocorre quando a Outbox vai entregar uma rota. Reprocessar uma
  entrega pode baixar e assinar a mídia novamente, renovando a URL temporária.
- O arquivo é hoje carregado integralmente em memória antes do upload. Para
  produção com mídias grandes, adicione limite máximo de bytes, streaming,
  validação de Content-Type e varredura antimalware.
- O worker verifica/cria o bucket no caminho de upload. Em produção, prefira
  provisionar bucket, política privada e lifecycle fora da requisição.
- A URL S3 precisa usar um endpoint alcançável pelo destino. Por isso
  `S3_PUBLIC_ENDPOINT_URL` pode diferir de `S3_ENDPOINT_URL`.
- Falhas de resolução, download ou upload não descartam o evento: o payload
  recebe status como `resolve_failed`, `missing_access_token`,
  `missing_s3_config` ou `upload_failed`. A aplicação destino deve tratar
  esses estados em vez de assumir que `download_url` sempre existe.

## 12. Enviar mensagens pela API oficial

Para enviar, use o token associado à WABA e o phone_number_id da conexão ativa:

~~~http
POST https://graph.facebook.com/<versao>/<phone_number_id>/messages
Authorization: Bearer <access_token_da_waba>
Content-Type: application/json
~~~

O endpoint interno do SaaS deve receber somente um ID de conexão que pertença ao tenant autenticado. Ele resolve phone_number_id e access_token no servidor.

~~~http
POST /api/whatsapp/connections/<connection_id>/messages
~~~

Isso impede que um cliente tente enviar usando o phone_number_id de outro tenant.

## Isolamento, autorização e idempotência

Em produto com muitos números, estas regras são obrigatórias:

- Derive tenant e permissões da sessão autenticada.
- Cada tentativa pertence a um único tenant, empresa e Meta App.
- Não aceite que o frontend defina livremente qual WABA/número será associado.
- Use chaves únicas para WABA e phone_number_id na camada Meta.
- Proíba a mesma conexão ativa em empresas distintas sem um fluxo de transferência auditado.
- Faça upsert de WABA, número e conexão.
- Use attempt_id e hash do code para evitar duplicidade.
- Faça transições de estado com lock ou update condicional.
- Mantenha histórico de conexões desativadas; não apague credenciais e rotas sem processo de revogação.
- Separe token de aplicativo, token de acesso à WABA e credenciais dos usuários do SaaS.

## Segurança e observabilidade

| Dado | Regra |
| --- | --- |
| state | Aleatório, curta duração, hash em banco e uso único. |
| authorization code | Nunca em log; criptografado se persistido. |
| app secret e access token | Criptografados em repouso, mascarados em painel e fora de respostas HTTP. |
| webhook | Verificação de token no GET e assinatura no POST. |
| logs | Registrar IDs, status, duração e erro seguro; nunca segredos ou payloads sensíveis completos. |

Audite: início/cancelamento de tentativa, callback recebido, troca de token, criação/alteração de WABA e número, alteração de rota, conexão concluída, revogação e erros terminais.

Métricas úteis:

- tentativas iniciadas, concluídas, expiradas e falhas por motivo;
- tempo entre redirected e completed;
- falhas de troca de token;
- conexões por tenant e Meta App;
- webhooks recebidos, inválidos, duplicados e atrasados;
- mensagens enviadas e erros da Graph API.

## Diagnóstico de falhas

| Sintoma | Causa provável | Ação |
| --- | --- | --- |
| redirect_uri inválida | URI diferente no início e na troca ou não cadastrada | Use a URI persistida e confira igualdade literal. |
| callback sem state | Configuração incorreta ou requisição maliciosa | Recuse; não busque empresa pelo code. |
| code já consumido | Retry após troca ou token não persistido | Reuse apenas token da mesma tentativa/WABA; senão reinicie. |
| WABA encontrada sem número | Propagação assíncrona ou descoberta incompleta | Mantenha reconciling e sincronize com retentativa. |
| Mais de um número correspondente | Seleção ambígua | Peça escolha explícita; não escolha o primeiro. |
| Número associado a outra empresa | Falta de regra de unicidade/transferência | Bloqueie e exija fluxo administrativo auditado. |
| Popup fecha | Cancelamento, bloqueador ou etapa adicional | Consulte o status da tentativa até expirar. |
| Webhooks não chegam | Endpoint, assinatura ou configuração Meta | Verifique URL pública, GET de validação, assinatura e logs. |

## Checklist de aceite

- [ ] O SaaS possui App Meta, config_id, callback OAuth e webhook configurados.
- [ ] O backend cria uma tentativa persistida e state único com TTL.
- [ ] A URL final sempre recebe state e redirect_uri da tentativa.
- [ ] O callback valida state antes de ler/processar o code.
- [ ] A troca de code ocorre apenas no backend com app secret protegido.
- [ ] O token fica criptografado e não aparece em logs ou APIs.
- [ ] A WABA e o número são descobertos e validados antes de ativar.
- [ ] O número é escolhido de forma determinística.
- [ ] WABA, número e conexão são idempotentes e isolados por tenant.
- [ ] A assinatura X-Hub-Signature-256 é calculada sobre o corpo bruto, com o App Secret da WABA correta; eventos inválidos não são roteados.
- [ ] Webhooks oficiais são verificados, deduplicados e roteados por phone_number_id.
- [ ] Rotas são vinculadas ao número interno, filtradas por tipo, entregues pela Outbox com timeout, retry e dead letter.
- [ ] Templates são persistidos por WABA + nome + idioma, sincronizados com a Meta e só enviados quando aprovados.
- [ ] Mídias recebidas são resolvidas com o token da WABA e, quando necessário, copiadas para S3/MinIO com URL pré-assinada temporária.
- [ ] O fluxo de mídia possui TTL, limites de tamanho, validação de conteúdo e política de retenção adequados ao ambiente.
- [ ] O envio de mensagem resolve token e número no servidor.
- [ ] Repetir callback, webhook ou job não cria conexões duplicadas.

## Critério de pronto

O cadastro incorporado está pronto quando um administrador conecta seu número pela jornada oficial da Meta, o SaaS associa a WABA e o phone_number_id somente à empresa correta, armazena o token com segurança, recebe webhooks desse número, envia mensagens pela Graph API e mantém o mesmo comportamento correto para centenas ou milhares de empresas e números conectados.
