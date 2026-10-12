# SEO/GEO Manager 0.3.62 — Navigation Workspace

Validation slice for the Manager-first EMMAKE build path.

## Adds

- WordPress navigation menu inventory and bounded inspection in wp-admin;
- menu location visibility without exposing raw menu-item metadata;
- correction UI limited to custom-link menu items;
- published page/post target picker backed by WordPress resource IDs;
- target permalink resolved server-side for the wp-admin workflow;
- no arbitrary URL input in the Navigation Workspace;
- Preview before Apply;
- exact menu fingerprint protection;
- explicit public-navigation acknowledgement;
- environment-bound Apply;
- idempotency key;
- post-Apply verification endpoint;
- operation ID and stale-safe rollback;
- post-Rollback fingerprint restoration check;
- object-backed menu items preserved for manual review;
- dedicated Navigation Workspace CI contract.

Implementation base head: `d9a5141e930bda856c83e2f8c776363dfcba1180`.

Do not promote unless all relevant checks are green.
