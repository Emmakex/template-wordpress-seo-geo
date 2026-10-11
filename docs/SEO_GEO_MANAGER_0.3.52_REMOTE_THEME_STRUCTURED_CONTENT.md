# SEO/GEO Manager 0.3.52 — R3.2 remote Theme structured content

## Purpose

R3.2 extends the Manager-owned remote bridge with one additional semantic operation:

- `content-change` — existing R3 WordPress content change-set operation.
- `theme-structured-content` — bounded SEO/GEO Theme semantic-slot mutation.

This milestone does **not** add a second mutation engine. `theme-structured-content` delegates to the already accepted C5 `ThemeStructuredContentAdapter`, which in turn applies changes through the existing `ChangeSetEngine`.

## Remote lifecycle

`Inspect -> Plan/Preview -> explicit Apply -> stored-state Verify -> bounded evidence -> Rollback`

Plans remain short-lived and bound to the Manager connection, WordPress execution identity and environment fingerprint.

## Theme semantic contract

A remote Theme structured plan must provide:

- an existing WordPress page target;
- the exact current content fingerprint;
- an accepted Theme `model_id` mapped to that page;
- one or more slots defined by the active Theme model contract.

The Manager validates the active Theme/preset/model mapping before preparing a mutation. Arbitrary HTML and arbitrary model/slot names are not accepted.

## Accepted R3.2 operation

The first runtime acceptance uses the existing `corporate-contact-v1` model and changes only declared Contact slots. The test proves that:

- Plan/Preview does not mutate the page;
- the Theme model and page mapping are resolved before Apply;
- Apply changes only the requested semantic slots;
- untargeted content remains unchanged;
- Theme-owned layout/wrapper markup is preserved;
- the target remains a draft;
- request replay is idempotent;
- Verify matches the exact stored fingerprint;
- remote operation evidence exposes model identity/layout preservation without reflecting slot values or private before/changes payloads;
- Rollback restores the exact original WordPress content;
- audit evidence remains secret-free.

## Deliberate limits

R3.2 does not remotely expose:

- rendered/public-route verification;
- arbitrary HTML editing;
- arbitrary metadata editing;
- SEO-provider writes;
- media writes;
- navigation writes;
- generic REST proxying;
- shell execution.

Rendered verification remains available through the accepted local C5 operation but is deliberately not part of the remote R3.2 surface yet.

## Compatibility

The R3 `content-change` operation remains supported and must continue to pass its existing Plan -> Apply -> Verify -> Rollback runtime acceptance unchanged.

## Promotion rule

0.3.52 may be called repository-certified only when the exact candidate passes:

- Remote Bridge R1-R3.2 contract;
- pinned WordPress + self-contained SEO/GEO Theme runtime acceptance;
- PHP Quality (WPCS + PHPStan);
- Manager WordPress CI;
- deterministic Release CI;
- all repository workflows triggered for the candidate.

Real client installation/acceptance remains separate field evidence.
