# Portable contract fixtures

The files in this directory are the portable part of the regression suite.
They contain JSON only, so they can be copied to a new implementation in
Python, Node.js, Go, Java or another language.

The current fixture is:

```text
meta-message-contracts.json
```

Each case has a stable `id`, a `kind` and one of these inputs:

| kind | input | contract being checked |
| --- | --- | --- |
| `received` | `meta_webhook` with `messages` | Meta inbound message normalization and `message_received` event |
| `echo` | `meta_webhook` with `message_echoes`/`smb_message_echoes` | outbound echo normalization and `message_echo` event |
| `status` | `meta_webhook` with `statuses` | status event and `sent` status preservation |
| `outbound` | `external_request` shaped like Meta | phone normalization, message type and payload passed to Meta |
| `meta_operation` | operation-specific input | OAuth exchange, WABA/phone discovery, webhook subscription/callback URI, template creation and template listing |

The `expected_normalized`, `expected_event` and `expected_meta_request` fields
are assertions, not implementation instructions. A port can use the same JSON
and provide its own adapter:

```text
for case in fixture.cases:
    result = adapter.handle(case)
    assert_subset(case.expected_normalized, result.normalized)
    assert_subset(case.expected_event, result.event)
    assert_subset(case.expected_request, result.meta_request)
    assert_subset(case.expected_response, result.meta_response)
```

The PHP adapter is [PortableContractFixturesTest.php](../router/PortableContractFixturesTest.php).
It intentionally loops over the fixture instead of duplicating one test method
per payload. The rest of `tests/router` remains useful for WordPress-specific
integration contracts such as database persistence, route delivery and media
storage.

To port the suite:

1. copy this directory and the JSON fixture;
2. implement the adapter for the target application;
3. preserve the fixture `version` and case IDs;
4. run the target-language loop against every case;
5. add new behavior by adding a new case before changing the adapter.

The fixture contains no production tokens or secrets. URLs and IDs are test
values only.
