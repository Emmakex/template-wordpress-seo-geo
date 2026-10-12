# SEO/GEO Manager 0.3.66 — Safe Recovery / Readback

Validation slice for the Manager-first EMMAKE build path.

## Adds

- recovery/readback controls integrated into Operation Evidence without turning the history endpoint into a mutation proxy;
- GET-only privacy-safe recovery manifest per visible operation;
- strict server-side allowlist for five already-certified operation families: SEO authority, contextual links, navigation, Featured Media and Media ALT;
- adapter identity must match the operation family before any route is exposed;
- Verify delegates to the existing type-specific GET endpoint and requires `verified=true`;
- rollback delegates to the existing type-specific POST endpoint;
- rollback obtains the current environment fingerprint immediately before mutation and every existing adapter keeps its own stale/environment guards;
- unsupported or non-applied operations expose no actionable route;
- private payloads, before/after values and private fingerprints remain server-side;
- Operation Evidence core remains read-only; mutation logic lives in a separate recovery module;
- WordPress runtime acceptance creates a real Media ALT operation, obtains the manifest, executes type-specific Verify and rollback, confirms exact restoration, rejects unsupported routing and enforces hidden-row permissions;
- dedicated Safe Recovery CI contract prohibits generic operation Apply/Verify/Rollback routes and generic execution primitives.

Do not promote unless all relevant checks are green and the deterministic 0.3.66 package identity is reproduced by Release CI.
