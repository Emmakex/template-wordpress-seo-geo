# SEO/GEO Manager 0.3.32 — clean-target atomic verification fix

## Field finding

Real EMMAKE `/nuevaweb/` evidence on Manager 0.3.31 reached the intended final state:

- historical authority verified;
- clean target `/blog/%postname%/`;
- 1,701 historical paths preserved directly;
- 25 one-hop 301 redirects required;
- zero collisions;
- zero local slug repairs pending;
- atomic runtime preview safe.

When the operator confirmed **Aplicar estructura + 301**, the engine wrote the guarded transition, revalidated it, then rolled it back with `seo_geo_manager_permalink_redirect_verification_failed`.

## Root cause

The clean-target planner introduced the preservation mode:

`one-hop-301-to-clean-target`

The atomic verification code still required the older exact mode:

`authoritative-301`

Therefore a correct clean-target revalidation was incorrectly classified as `authoritative-structure-verification-failed` and the safety rollback ran as designed.

## 0.3.32 correction

The atomic verifier now accepts exactly two supported redirect-preservation modes:

- `authoritative-301`;
- `one-hop-301-to-clean-target`.

It does **not** simply loosen the guard. The mode must remain identical before and after the write. A transition from one preservation strategy to another during Apply still fails verification and rolls back.

Applied operation evidence now stores the actual plan preservation mode instead of always labeling it `authoritative-301`.

## Operator UX

The focused finalization screen now surfaces the exact verification code(s) returned by the REST error, including the failed operation ID when available.

The Field Gate JSON evidence and operation history are kept in **Detalles técnicos y herramientas avanzadas**, rather than occupying the focused finalization flow.

## Safety unchanged

0.3.32 does not weaken any existing write guard:

- current authority fingerprint required;
- current plan fingerprint required;
- current permalink fingerprint required;
- explicit permalink confirmation required;
- explicit redirect-runtime confirmation required;
- environment fingerprint required where applicable;
- runtime armed before structure mutation;
- structure + runtime jointly verified after mutation;
- redirect map must remain identical after revalidation;
- every redirect must resolve one hop to the expected target;
- any verification failure restores the previous structure and removes the runtime.

## Field sequence

After installing 0.3.32 on EMMAKE `/nuevaweb/`:

1. Run **Comprobar** with historical origin `https://emmake.com/`.
2. Confirm target `/blog/%postname%/`, zero collisions and the expected redirect count.
3. Run **Previsualizar operación final**.
4. Confirm **Aplicar estructura + 301**.
5. If Apply succeeds, run **Volver a comprobar** and confirm the final state.
6. If Apply fails, retain the exact verification code shown by 0.3.32; automatic rollback remains mandatory.

No Migration Bridge reset, Theme reset or content hydration is required for this correction.
