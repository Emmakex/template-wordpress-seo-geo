# Preset Packages

Each directory under `presets/` will contain configuration/content scaffolding for one repeatable site archetype without forking the Core plugin.

Implementation order:

1. `corporate` — Phase 7A complete;
2. `local-business` — Phase 7B complete;
3. `publisher` — Phase 7C complete;
4. `ecommerce` — Phase 7D complete.

Phase 7 is complete. Each preset is declarative and composes the neutral theme/Core instead of forking it. The built theme bundles all four packages so the Phase 8 onboarding layer can activate one through the server-authoritative preset registry.

See `docs/PRESETS.md` for the contract and `docs/ROADMAP.md` for phase ordering.
