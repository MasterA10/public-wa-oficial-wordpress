# Middleware de envio de mensagens

Este plugin pode funcionar como middleware entre uma aplicação externa e a WhatsApp Cloud API. A aplicação externa envia um payload compatível com o formato da Meta para o WordPress; o plugin identifica o tenant, a WABA, o número remetente e o token correto, e então envia a mensagem para o destinatário.

## Endpoint

```text
POST https://SEU-DOMINIO/wp-json/was-router/v1/webhooks/send
```

Headers obrigatórios:

```http
Content-Type: application/json
X-WAS-Webhook-Secret: SEU_SEGREDO
```

Também é aceito o header alternativo `X-Webhook-Secret`.

## Configuração do segredo

O segredo é procurado nesta ordem:

1. Constante PHP `WAS_EXTERNAL_SEND_WEBHOOK_SECRET`.
2. Variável de ambiente `WAS_EXTERNAL_SEND_WEBHOOK_SECRET`.
3. Opção do WordPress `was_external_send_webhook_secret`, configurável pelo painel Master.

Exemplo no `wp-config.php`:

```php
define('WAS_EXTERNAL_SEND_WEBHOOK_SECRET', 'troque-por-um-segredo-longo');
```

Nunca envie o segredo dentro do JSON e não o registre nos logs da aplicação externa.

## Seleção do tenant e do número

O campo mais importante é:

```json
"metadata": {
  "phone_number_id": "ID_DO_NUMERO_NA_META"
}
```

O `phone_number_id` deve estar cadastrado no plugin. A partir dele o middleware resolve:

- o tenant proprietário do número;
- a WABA vinculada;
- o token ativo da WABA;
- o número Meta usado no endpoint Graph;
- a conversa e o contato do destinatário.

`tenant_id` e `waba_id` podem ser enviados para validação adicional. Se forem informados e não corresponderem ao número cadastrado, a requisição será rejeitada.

## Payload recomendado

O formato recomendado é semelhante ao payload oficial da Meta:

```json
{
  "object": "whatsapp_business_account",
  "tenant_id": 1,
  "waba_id": "WABA_ID_META",
  "entry": [
    {
      "id": "WABA_ID_META",
      "changes": [
        {
          "field": "messages",
          "value": {
            "messaging_product": "whatsapp",
            "metadata": {
              "phone_number_id": "META_PHONE_NUMBER_ID"
            },
            "messages": [
              {
                "id": "id-da-aplicacao-externa-123",
                "to": "5511999999999",
                "type": "text",
                "text": {
                  "body": "Mensagem enviada pelo middleware"
                }
              }
            ]
          }
        }
      ]
    }
  ]
}
```

O destinatário deve ser enviado em `to`. O middleware remove caracteres não numéricos antes do envio.

## Payload simplificado

Também é possível enviar os campos diretamente no corpo:

```json
{
  "tenant_id": 1,
  "phone_number_id": "META_PHONE_NUMBER_ID",
  "to": "5511999999999",
  "type": "text",
  "text": {
    "body": "Mensagem enviada pelo middleware"
  }
}
```

## Texto

Para texto, use `type: text` e `text.body`:

```bash
curl -X POST "https://SEU-DOMINIO/wp-json/was-router/v1/webhooks/send" \
  -H "Content-Type: application/json" \
  -H "X-WAS-Webhook-Secret: SEU_SEGREDO" \
  -d '{
    "tenant_id": 1,
    "phone_number_id": "META_PHONE_NUMBER_ID",
    "to": "5511999999999",
    "type": "text",
    "text": {
      "body": "Olá, mensagem enviada pela aplicação externa"
    }
  }'
```

Textos usam o mesmo `OutboundMessageService` utilizado pelo chat. Isso significa que o envio valida a janela de atendimento, resolve o número vinculado à conversa, envia pela WABA correta e salva a mensagem localmente.

## Mídia por URL pública

Para usar o mesmo fluxo de mídia do chat, envie uma URL pública em `link`:

```json
{
  "tenant_id": 1,
  "phone_number_id": "META_PHONE_NUMBER_ID",
  "to": "5511999999999",
  "type": "image",
  "image": {
    "link": "https://outro-sistema.example/imagens/oferta.jpg",
    "mime_type": "image/jpeg",
    "filename": "oferta.jpg",
    "caption": "Confira esta imagem"
  }
}
```

Tipos de mídia aceitos nesse fluxo:

- `image` — JPEG ou PNG, até 5 MB;
- `audio` — OGG, MP3, M4A e formatos suportados, até 16 MB;
- `video` — MP4 ou 3GP, até 16 MB;
- `document` — PDF, TXT, DOC, DOCX, XLS ou XLSX, até 100 MB.

O link precisa:

1. usar `http` ou `https`;
2. estar acessível pelo servidor WordPress;
3. retornar HTTP 2xx;
4. retornar um corpo não vazio;
5. informar `mime_type` ou usar um nome de arquivo com extensão reconhecida.

O middleware baixa a mídia, salva uma cópia pública no WordPress, faz o upload para a Meta e envia a mensagem usando o `OutboundMediaService` do chat. A URL pública local não é enviada diretamente à Meta; ela é usada como origem para o download e a Meta recebe o ID do upload realizado pelo número correto.

## Mídia com ID da Meta

Também é possível enviar uma mídia que já foi carregada na Meta:

```json
{
  "tenant_id": 1,
  "phone_number_id": "META_PHONE_NUMBER_ID",
  "to": "5511999999999",
  "type": "image",
  "image": {
    "id": "MEDIA_ID_DA_META",
    "caption": "Imagem já hospedada na Meta"
  }
}
```

Nesse caso o middleware usa o transporte direto do Router. O ID precisa ser válido para a WABA/número que fará o envio. Se o ID pertencer a outra aplicação ou WABA, a Meta pode retornar `Unsupported get/post request` ou erro de permissão. Para mídias originadas em outra aplicação, prefira enviar uma URL pública e deixe o WordPress fazer um novo upload.

## Janela de atendimento

Texto e mídia enviados por `link` usam os mesmos serviços do chat e, portanto, dependem da janela de atendimento de 24 horas. Se a janela estiver fechada, use o fluxo de template aprovado ou envie a mídia já carregada usando o transporte Router, conforme as regras da Meta.

## Resposta de sucesso

Exemplo de resposta:

```json
{
  "success": true,
  "wa_message_id": "wamid....",
  "id": 123,
  "meta_phone_number_id": "META_PHONE_NUMBER_ID"
}
```

`meta_phone_number_id` confirma qual número Meta foi usado pelo middleware.

## Erros comuns

| HTTP | Código | Causa |
|---:|---|---|
| 401 | `external_send_webhook_unauthorized` | Segredo ausente ou inválido. |
| 503 | `external_send_webhook_not_configured` | O segredo ainda não foi configurado. |
| 400 | `invalid_external_send_payload` | Faltam `phone_number_id`, `to`, `type` ou o objeto da mensagem. |
| 403 | `phone_tenant_mismatch` | O número não pertence ao tenant informado. |
| 403 | `phone_waba_mismatch` | O número não pertence à WABA informada. |
| 404 | `phone_number_not_found` | O `phone_number_id` não está cadastrado no plugin. |
| 502 | `external_media_download_failed` | Não foi possível baixar a mídia da URL. |
| 502 | `external_chat_send_failed` | O serviço de texto do chat não conseguiu enviar. |
| 502 | `external_chat_media_send_failed` | O serviço de mídia do chat não conseguiu enviar. |

As respostas de erro possuem `success: false`, `error`, `message` e `detail` para facilitar o diagnóstico.

## Idempotência

O endpoint aceita `idempotency_key` e também pode usar `messages[0].id` como identificador da solicitação. Use sempre um identificador estável ao reenviar eventos externos. Para mensagens que passam pelo fluxo direto do Router, esse identificador é usado para evitar duplicidade.

## Checklist de integração

- [ ] O número Meta está cadastrado no tenant correto.
- [ ] O `phone_number_id` enviado é o ID da Meta, não o ID interno do banco.
- [ ] A WABA possui token ativo.
- [ ] O segredo está configurado e é enviado no header.
- [ ] O destinatário está em `to` com DDI e DDD.
- [ ] URLs de mídia são públicas e acessíveis pelo servidor WordPress.
- [ ] A aplicação registra o status HTTP e o JSON de resposta.
- [ ] Eventos reenviados usam o mesmo `idempotency_key`.

