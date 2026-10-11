# SEO/GEO Manager 0.3.54 — R3.4 remote contextual links

## Goal

Expose one SEO/GEO-relevant internal-link operation through the Manager-owned remote bridge without allowing arbitrary HTML or arbitrary URL rewriting.

R3.4 adds:

- bounded discovery: `GET /remote/contextual-links?source_id={id}`
- bounded mutation: `contextual-link-change`

Remote lifecycle:

`Discover edge -> Plan/Preview -> Apply -> readback -> Verify -> bounded evidence -> Rollback`

## Edge discovery

The remote caller must discover the link edge through `ContextualLinkReader` before planning a write.

The reader returns bounded link evidence only:

- stable `edge_id` derived from the current source/link state;
- source resource ID/type/status/permalink/fingerprint;
- anchor text;
- current href;
- classification;
- verified WordPress target when resolvable.

Raw post content is not returned.

The discovery route requires `site.diagnostics`. R3.4 does not add a new remote permission scope.

## Mutation contract

`contextual-link-change` delegates to the already accepted C6.2 `ContextualLinkChangeAdapter`.

The caller provides:

- `schema_version=1`;
- discovered `edge_id`;
- source resource ID;
- source expected fingerprint from discovery;
- destination WordPress resource ID.

The caller does **not** provide an arbitrary replacement href. The adapter resolves the current permalink of the destination resource and requires that destination to be an existing published post/page.

## Safety boundaries

- exact discovered edge required;
- forged/stale edge IDs are rejected;
- source optimistic fingerprint required;
- only `href` is mutable;
- anchor text is preserved;
- existing anchor attributes are preserved;
- target must be a published WordPress resource with a current permalink;
- target URL must remain inside the current site origin;
- duplicate target-href collisions are blocked;
- published source writes remain blocked unless the existing C6.2 published-source approval is explicit;
- Apply is idempotent;
- stored-state Verify uses the exact source `ContentFingerprint`;
- operation evidence exposes changed field/fingerprints but no raw source body or private href snapshots;
- Rollback delegates to C6.2 and is stale-safe;
- no generic shell, generic REST proxy or raw HTML mutation surface is introduced.

## Repository acceptance required before promotion

0.3.54 is not repository-certified merely because the code exists. Promotion requires all of the following on one exact HEAD:

1. Remote R1–R3.4 static contract passes.
2. Existing R1–R3.2 remote regression runtime remains green.
3. R3.3 Theme-native SEO runtime remains green.
4. R3.3 Yoast 28.6 runtime remains green.
5. R3.4 contextual-link runtime proves:
   - authenticated remote edge discovery;
   - raw source body is not returned;
   - fabricated edge rejection without mutation;
   - mutation-free Preview;
   - href-only Apply to a published target resource;
   - anchor text and attributes preserved;
   - idempotent Apply replay;
   - exact source fingerprint Verify;
   - bounded operation evidence;
   - exact stale-safe Rollback;
   - secret-free audit.
6. PHP Quality, Manager WordPress and deterministic release gates pass.
7. The complete PR workflow set is green on the same exact HEAD.

Repository/runtime acceptance is separate from client field acceptance. C9 remains a distinct evidence track.
