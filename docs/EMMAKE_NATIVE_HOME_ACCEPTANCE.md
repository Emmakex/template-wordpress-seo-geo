# Emmake Native Home Acceptance

Status: **candidate gate — no production mutation authorized**

This document defines the bounded real-site acceptance required for the first Corporate Native Home preview in the existing `/nuevaweb/` sandbox.

## Scope

Source:

- production authority: `https://emmake.com/`;
- sandbox: `https://emmake.com/nuevaweb/`;
- preset: `corporate`;
- native presentation contract: `corporate-native-v1`;
- implementation path: Native Replatform Composer -> Content Remap Intelligence -> Reviewed Apply.
- sandbox plugin candidate: `SEO/GEO Migration Bridge 0.8.48`.

The source page remains the preserved authority during this gate. The native destination must remain a private draft.

## Preconditions

All of the following must be true before Reviewed Apply is executed on the real Home:

- the request is running inside the accepted Migration Bridge sandbox with version `0.8.48` installed;
- the Corporate preset is active;
- the preserved Home source ID, path and content SHA-256 are captured;
- the native composition plan is ready with zero blockers;
- the Content Remap plan is current and its SHA-256 matches the draft metadata;
- each selected asset ID exists in the current candidate inventory;
- every evidence-sensitive section is explicitly reviewed by an administrator;
- the native draft still contains exactly one marker for every selected semantic slot;
- the source page is still published and unchanged since the reviewed plan was created.

## Required Home semantic slots

The first real preview must review these Corporate Home slots independently:

1. `value-proposition`;
2. `service-overview`;
3. `organization-context`;
4. `verified-proof`;
5. `primary-cta`.

Missing evidence is allowed. Fabricated evidence is not.

## Blocking drift

Reviewed Apply must stop before mutation when any of these identities change:

- source page identity;
- source content fingerprint;
- native composition fingerprint;
- Content Remap plan fingerprint;
- selected candidate inventory;
- deterministic native slot marker count.

A drifted plan must be regenerated and reviewed again. The system must never silently reinterpret an old approval against new source content.

## Preview acceptance

The Home preview is accepted for the next page only when all of the following are recorded:

- destination remains `draft`;
- source page content, slug and public URL are unchanged;
- selected assets appear only in their reviewed native slots;
- evidence-sensitive material has explicit verification;
- unmapped source assets remain visible in the ledger;
- the current pre-apply draft body is retained privately as rollback evidence for the active review cycle;
- the operator can preview the private native draft without publishing it;
- the operator can download a bounded review-evidence JSON containing hashes, selected asset IDs, counts, verification decisions and drift state, but no post body or backup body;
- replaying the same reviewed selection is idempotent;
- no legacy Divi visual-layout dependency is reintroduced;
- no canonical, robots, hreflang or Schema authority is changed by draft creation;
- internal and external links selected for the draft remain traceable to source assets;
- no factual text is generated merely to fill a preset slot.

## Quality gate after editorial review

Once the draft content and presentation are approved, the sandbox Home must pass:

- SEO/GEO ownership regression;
- internal-link and media preservation review;
- responsive acceptance at the established viewport matrix;
- automated accessibility acceptance;
- performance budgets;
- clean WordPress/PHP runtime logs.

Passing this Home gate does **not** authorize production cutover. It authorizes repeating the accepted workflow for Services, Work, About, Insights and Contact.

## Evidence boundary

Do not commit post bodies, credentials, customer information, database dumps, backups or private uploads to the repository. Repository evidence must stay bounded to identities, hashes, counts, decisions, gate results and non-sensitive observations. The downloadable Reviewed Remap evidence is intentionally safe for that role: it records no post body, backup body, credentials or arbitrary option values.
