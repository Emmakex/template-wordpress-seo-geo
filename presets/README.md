# Preset Packages

Each directory under `presets/` will contain configuration/content scaffolding for one repeatable site archetype without forking the Core plugin.

Implementation order:

1. `corporate` — Phase 7A complete;
2. `local-business` — Phase 7B complete;
3. `publisher`;
4. `ecommerce`.

Each preset is declarative and must compose the neutral theme/Core instead of forking it. The built theme bundles these packages so the Phase 8 onboarding layer can activate one through the server-authoritative preset registry.

See `docs/PRESETS.md` for the contract and `docs/ROADMAP.md` for phase ordering.
