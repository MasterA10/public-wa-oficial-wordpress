# Regression suite

The router suite is a dependency-free contract suite for the plugin's Meta,
WhatsApp, inbox and external-webhook paths.

Run it from the plugin root:

```bash
php tests/router/run.php
```

The suite covers the three WhatsApp message directions:

- received messages (`messages`) are normalized, persisted as `inbound`, and
  forwarded to active routes;
- messages sent by the chat or external middleware use the selected internal
  phone/WABA, Meta's payload, typing, local persistence and error status;
- app echoes (`message_echoes` / `smb_message_echoes`) are persisted as
  `outbound`, including media and idempotency.

It also verifies the main Meta input/output contracts: HMAC validation, tenant
and WABA resolution, Graph paths and bearer tokens, template payloads, media
download failures, public WordPress media URLs, route delivery logs, webhook
diagnostics, onboarding credentials and REST error responses.

The same loop also fixes the embedded-signup contracts: OAuth code exchange,
WABA and phone discovery, WABA webhook subscription (including callback URI
and verify token), template creation and template listing, including their
Meta request and response shapes.

The language-neutral loop and JSON cases live in
[`tests/contracts`](../contracts/README.md). Use those fixtures as the source
of truth when reimplementing the middleware in another language; the PHP tests
in this directory add WordPress persistence and integration assertions.

Mac metadata files named `._*` are excluded from the source/test inventory.
