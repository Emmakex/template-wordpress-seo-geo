# SEO/GEO Manager 0.3.31 — Focused Finish UI

## Decision

The Manager admin surface must follow the current operational state instead of accumulating every diagnostic capability in the primary view.

For Build / Finish, the default screen is reduced to one guided sequence:

1. **Comprobar** — run the current Field Gate against the accepted historical origin.
2. **Aplicar** — show only the protected write that is actually eligible for the current site state.
3. **Verificar** — rerun the Field Gate after the write; keep rollback available when the operation supports it.

The generic Site Intelligence, root-cause diagnostics, auxiliary correction tooling, operation history and raw JSON remain available, but are moved under a collapsed **Detalles técnicos y herramientas avanzadas** section.

This is a presentation simplification only. It does not weaken or remove any mutation guard, rollback evidence, historical authority proof, environment binding, idempotency, collision scan or redirect verification.

## SEO/GEO principle

The old website is an **authority/evidence source**, not the architecture template for the new website.

Preserve:

- historical URL authority;
- content that still has value;
- backlinks and external entry points;
- canonical intent and useful internal-link evidence;
- redirect requirements needed to transfer authority.

Do not preserve merely because it existed:

- obsolete permalink architecture;
- category folders that are not part of the chosen target information architecture;
- migration corruption;
- old builder/plugin/layout debt;
- technical structures that make future SEO/GEO harder to operate.

The preferred target is chosen from current SEO/GEO evidence. Historical outliers are handled with verified one-hop 301 redirects when the target architecture is demonstrably better.

## Current EMMAKE field state entering 0.3.31

The latest 0.3.30 Field Gate on `/nuevaweb/` proved:

- Manager version: `0.3.30`;
- historical authority verified;
- target strategy: `seo-geo-clean-target`;
- target structure: `/blog/%postname%/`;
- local slug repairs remaining: `0`;
- collisions: `0`;
- historical paths already preserved: `1701`;
- one-hop redirects required: `25`;
- runtime preview safe to activate;
- Field Gate status: `ready-for-guarded-write`;
- guarded write mode: `atomic-301`;
- active redirect runtime before Apply: none.

Therefore the primary operator action is no longer generic analysis. It is the final protected sequence:

`Field Gate -> Preview estructura + 301 -> Apply atómico -> Field Gate final -> keep or rollback`

## 0.3.31 focused workflow

The main screen shows:

- one focused hero with the installed Manager version;
- the three-step finalization sequence;
- the Field Gate;
- a focused finalization card derived directly from the Field Gate evidence.

When the Field Gate is `atomic-ready`, the finalization card shows only:

- final target structure;
- number of historical URLs already preserved;
- number of 301 redirects required;
- collision count;
- one **Previsualizar operación final** action.

The preview revalidates the redirect runtime immediately before mutation. Only if the fresh runtime preview is safe and atomic Apply is available does the UI expose **Aplicar estructura + 301**.

The Apply remains a separately confirmed operation and sends the current:

- historical authority fingerprint;
- permalink plan fingerprint;
- current permalink fingerprint;
- environment fingerprint;
- explicit permalink and redirect-runtime confirmations;
- unique idempotency key.

After success the UI keeps two actions visible:

- **Volver a comprobar** — rerun Field Gate against the post-write site;
- **Revertir operación** — restore the previous permalink structure and remove the runtime installed by that atomic operation, subject to the existing stale/environment guards.

## Safety invariants retained

- Field Gate remains read-only.
- Preview remains read-only.
- No automatic mutation after Field Gate.
- Historical authority is revalidated before atomic Apply.
- Redirects are server-derived from verified historical authority.
- Only one-hop 301 maps are accepted.
- Structure + redirect runtime remain one atomic reversible operation.
- Production-class environments still require the exact environment fingerprint acknowledgement.
- Collision and stale-state guards remain mandatory.
- Generic/advanced tooling is hidden from the default path, not deleted from the product.

## Product direction

Build / Finish should become progressively simpler as blockers are resolved. The Manager should not force an operator to interpret old diagnostic sections once the site has advanced to a later stage.

The default UI must always answer four questions clearly:

1. **Where are we?**
2. **What is blocking us now?**
3. **What is the next safe action?**
4. **How do we verify or revert it?**
