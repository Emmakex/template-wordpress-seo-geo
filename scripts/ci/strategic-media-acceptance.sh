#!/usr/bin/env bash
# Strategic Theme media acceptance.
#
# This file is sourced by corporate-preset-acceptance.sh and reuses the
# self-contained Theme WordPress fixture with zero active plugins.

printf '[self-contained] Checking strategic Corporate hero media binding.\n'

[[ -f "${BUILT_THEME}/presets/corporate/strategic-media.json" ]] \
  || fail_smoke "strategic-media-package" "Built theme is missing the strategic media contract" "presets/corporate/strategic-media.json exists" "missing"

wp_cli option update seo_geo_active_preset corporate >/dev/null \
  || fail_smoke "strategic-media-preset" "Could not activate Corporate preset for strategic media acceptance" "corporate active" "failed"

STRATEGIC_MEDIA_CONTENT_FILE="${TMP_DIR}/strategic-media-home.html"
cat >"$STRATEGIC_MEDIA_CONTENT_FILE" <<'HTML'
<!-- wp:html -->
<section class="seo-geo-corporate-native-hero">
<p class="seo-geo-content-slot--hero-eyebrow">Strategic media fixture</p>
<p class="seo-geo-content-slot--hero-lead">A Theme-owned hero rendered from semantic content.</p>
<p class="seo-geo-content-slot--hero-primary-cta"><a href="/contact/">Start</a></p>
<p class="seo-geo-content-slot--capabilities-heading">Capabilities</p>
<p class="seo-geo-content-slot--capabilities-intro">Three verified capabilities.</p>
<p class="seo-geo-content-slot--capability-1-title">Capability one</p>
<p class="seo-geo-content-slot--capability-1-body">First capability body.</p>
<p class="seo-geo-content-slot--capability-1-link"><a href="/services/one/">Explore one</a></p>
<p class="seo-geo-content-slot--capability-2-title">Capability two</p>
<p class="seo-geo-content-slot--capability-2-body">Second capability body.</p>
<p class="seo-geo-content-slot--capability-2-link"><a href="/services/two/">Explore two</a></p>
<p class="seo-geo-content-slot--capability-3-title">Capability three</p>
<p class="seo-geo-content-slot--capability-3-body">Third capability body.</p>
<p class="seo-geo-content-slot--capability-3-link"><a href="/services/three/">Explore three</a></p>
<p class="seo-geo-content-slot--process-heading">Method</p>
<p class="seo-geo-content-slot--process-intro">A bounded three-step method.</p>
<p class="seo-geo-content-slot--process-1-title">Inspect</p>
<p class="seo-geo-content-slot--process-1-body">Read the current state.</p>
<p class="seo-geo-content-slot--process-2-title">Apply</p>
<p class="seo-geo-content-slot--process-2-body">Apply one bounded change.</p>
<p class="seo-geo-content-slot--process-3-title">Verify</p>
<p class="seo-geo-content-slot--process-3-body">Verify the rendered result.</p>
<p class="seo-geo-content-slot--insights-heading">Insights</p>
<p class="seo-geo-content-slot--insights-intro">Latest authored knowledge.</p>
<p class="seo-geo-content-slot--final-cta-heading">Ready to continue?</p>
<p class="seo-geo-content-slot--final-cta-body">Use a safe, reversible operation.</p>
<p class="seo-geo-content-slot--final-cta-button"><a href="/contact/">Contact</a></p>
</section>
<!-- /wp:html -->
HTML

STRATEGIC_MEDIA_PAGE_ID="$(wp_cli post create \
  --post_type=page \
  --post_status=publish \
  --post_title='Strategic Media Home' \
  --post_name='strategic-media-home' \
  --post_content="$(cat "$STRATEGIC_MEDIA_CONTENT_FILE")" \
  --porcelain 2>"${TMP_DIR}/strategic-media-page.stderr" | tr -d '\r\n')"
[[ "$STRATEGIC_MEDIA_PAGE_ID" =~ ^[0-9]+$ ]] \
  || fail_smoke "strategic-media-page" "Could not create strategic media page fixture" "numeric page ID" "$STRATEGIC_MEDIA_PAGE_ID"

STRATEGIC_MEDIA_CONTRACT="$(wp_cli eval 'echo wp_json_encode( seo_geo_theme_strategic_media_contract( "corporate-home-v1" ) );' 2>"${TMP_DIR}/strategic-media-contract.stderr" | tr -d '\r\n')" \
  || fail_smoke "strategic-media-contract-eval" "Could not inspect strategic media contract" "hero-media contract" "wp eval failed"
[[ "$STRATEGIC_MEDIA_CONTRACT" == '{"hero-media":{"type":"media","required":false,"accept":"image"}}' ]] \
  || fail_smoke "strategic-media-contract" "Corporate strategic media contract drifted" '{"hero-media":{"type":"media","required":false,"accept":"image"}}' "$STRATEGIC_MEDIA_CONTRACT"

STRATEGIC_MEDIA_CONTENT_HASH_BEFORE="$(wp_cli eval "echo hash( 'sha256', (string) get_post( ${STRATEGIC_MEDIA_PAGE_ID} )->post_content );" | tr -d '\r\n')"
STRATEGIC_MEDIA_FP_BEFORE="$(wp_cli eval "echo seo_geo_theme_strategic_media_fingerprint( ${STRATEGIC_MEDIA_PAGE_ID}, 'corporate-home-v1', 'hero-media' );" | tr -d '\r\n')"
STRATEGIC_MEDIA_SLOT_BEFORE="$(wp_cli eval "echo seo_geo_theme_strategic_media_slot( ${STRATEGIC_MEDIA_PAGE_ID}, 'corporate-home-v1', 'hero-media' );" | tr -d '\r\n')"
[[ "$STRATEGIC_MEDIA_SLOT_BEFORE" == "0" ]] \
  || fail_smoke "strategic-media-initial-slot" "Fresh page unexpectedly has a strategic hero binding" "0" "$STRATEGIC_MEDIA_SLOT_BEFORE"

STRATEGIC_MEDIA_FALLBACK_BODY="${TMP_DIR}/strategic-media-fallback.html"
curl -fsS "${BASE_URL}/strategic-media-home/" -o "$STRATEGIC_MEDIA_FALLBACK_BODY" \
  || fail_smoke "strategic-media-fallback-request" "Could not request strategic media fallback page" "HTTP 2xx" "curl failed"
grep -Fq 'assets/images/presets/corporate/v5/hero-intelligence.svg' "$STRATEGIC_MEDIA_FALLBACK_BODY" \
  || fail_smoke "strategic-media-fallback" "Corporate hero lost its local SVG fallback before a media binding exists" "hero-intelligence.svg" "fallback absent"
grep -Eq 'seo-geo-corporate-native-hero__visual-column"[^>]*aria-hidden="true"' "$STRATEGIC_MEDIA_FALLBACK_BODY" \
  || fail_smoke "strategic-media-fallback-a11y" "Decorative fallback is not hidden from assistive technology" "visual column aria-hidden=true" "aria-hidden absent"

STRATEGIC_MEDIA_IMAGE="${TMP_DIR}/strategic-hero-1200x800.png"
python3 - "$STRATEGIC_MEDIA_IMAGE" <<'PY'
import struct
import sys
import zlib

path = sys.argv[1]
width, height = 1200, 800
pixel = bytes((42, 111, 126))
raw = b"".join(b"\x00" + pixel * width for _ in range(height))

def chunk(kind, payload):
    return struct.pack(">I", len(payload)) + kind + payload + struct.pack(">I", zlib.crc32(kind + payload) & 0xFFFFFFFF)

png = b"\x89PNG\r\n\x1a\n"
png += chunk(b"IHDR", struct.pack(">IIBBBBB", width, height, 8, 2, 0, 0, 0))
png += chunk(b"IDAT", zlib.compress(raw, 9))
png += chunk(b"IEND", b"")
with open(path, "wb") as handle:
    handle.write(png)
PY

docker cp "$STRATEGIC_MEDIA_IMAGE" "$WP_CONTAINER":/var/www/html/wp-content/strategic-hero-1200x800.png \
  || fail_smoke "strategic-media-image-copy" "Could not copy strategic hero fixture into WordPress" "image copied" "docker cp failed"
docker exec "$WP_CONTAINER" chown www-data:www-data /var/www/html/wp-content/strategic-hero-1200x800.png \
  || fail_smoke "strategic-media-image-owner" "Could not set strategic hero fixture ownership" "www-data owns image" "chown failed"

STRATEGIC_MEDIA_ATTACHMENT_ID="$(wp_cli media import /var/www/html/wp-content/strategic-hero-1200x800.png --title='Strategic Hero Acceptance' --porcelain 2>"${TMP_DIR}/strategic-media-import.stderr" | tr -d '\r\n')"
[[ "$STRATEGIC_MEDIA_ATTACHMENT_ID" =~ ^[0-9]+$ ]] \
  || fail_smoke "strategic-media-image-import" "Could not import strategic hero image" "numeric attachment ID" "$STRATEGIC_MEDIA_ATTACHMENT_ID"
wp_cli post meta update "$STRATEGIC_MEDIA_ATTACHMENT_ID" _wp_attachment_image_alt 'Strategic hero acceptance' >/dev/null \
  || fail_smoke "strategic-media-alt" "Could not set authored alt text on strategic hero image" "alt saved" "failed"

if ! STRATEGIC_MEDIA_BIND_RESULT="$(wp_cli eval "wp_set_current_user( 1 ); \$result = seo_geo_theme_set_strategic_media_slot( ${STRATEGIC_MEDIA_PAGE_ID}, 'corporate-home-v1', 'hero-media', ${STRATEGIC_MEDIA_ATTACHMENT_ID} ); if ( is_wp_error( \$result ) ) { echo \$result->get_error_code(); exit( 1 ); } echo seo_geo_theme_strategic_media_slot( ${STRATEGIC_MEDIA_PAGE_ID}, 'corporate-home-v1', 'hero-media' );" 2>"${TMP_DIR}/strategic-media-bind.stderr" | tr -d '\r\n')"; then
  STRATEGIC_MEDIA_BIND_ERROR="$(tr -d '\r' <"${TMP_DIR}/strategic-media-bind.stderr" | head -c 400)"
  fail_smoke "strategic-media-bind" "Could not bind Corporate hero image" "$STRATEGIC_MEDIA_ATTACHMENT_ID" "${STRATEGIC_MEDIA_BIND_RESULT:-}${STRATEGIC_MEDIA_BIND_ERROR:-}"
fi
[[ "$STRATEGIC_MEDIA_BIND_RESULT" == "$STRATEGIC_MEDIA_ATTACHMENT_ID" ]] \
  || fail_smoke "strategic-media-bind-readback" "Strategic media binding did not read back exactly" "$STRATEGIC_MEDIA_ATTACHMENT_ID" "$STRATEGIC_MEDIA_BIND_RESULT"

STRATEGIC_MEDIA_FP_BOUND="$(wp_cli eval "echo seo_geo_theme_strategic_media_fingerprint( ${STRATEGIC_MEDIA_PAGE_ID}, 'corporate-home-v1', 'hero-media' );" | tr -d '\r\n')"
[[ "$STRATEGIC_MEDIA_FP_BOUND" != "$STRATEGIC_MEDIA_FP_BEFORE" ]] \
  || fail_smoke "strategic-media-fingerprint-bind" "Strategic media fingerprint did not change after binding" "different fingerprint" "$STRATEGIC_MEDIA_FP_BOUND"

STRATEGIC_MEDIA_CONTENT_HASH_BOUND="$(wp_cli eval "echo hash( 'sha256', (string) get_post( ${STRATEGIC_MEDIA_PAGE_ID} )->post_content );" | tr -d '\r\n')"
[[ "$STRATEGIC_MEDIA_CONTENT_HASH_BOUND" == "$STRATEGIC_MEDIA_CONTENT_HASH_BEFORE" ]] \
  || fail_smoke "strategic-media-content-mutation" "Binding strategic media modified Gutenberg post_content" "$STRATEGIC_MEDIA_CONTENT_HASH_BEFORE" "$STRATEGIC_MEDIA_CONTENT_HASH_BOUND"

STRATEGIC_MEDIA_BOUND_BODY="${TMP_DIR}/strategic-media-bound.html"
curl -fsS "${BASE_URL}/strategic-media-home/" -o "$STRATEGIC_MEDIA_BOUND_BODY" \
  || fail_smoke "strategic-media-bound-request" "Could not request bound strategic hero page" "HTTP 2xx" "curl failed"
for expected_media_fragment in \
  'alt="Strategic hero acceptance"' \
  'class="seo-geo-corporate-hero-visual__image' \
  'loading="eager"' \
  'fetchpriority="high"' \
  'sizes="(max-width: 781px) calc(100vw - 2rem), 46vw"' \
  'srcset="'; do
  grep -Fq "$expected_media_fragment" "$STRATEGIC_MEDIA_BOUND_BODY" \
    || fail_smoke "strategic-media-render" "Bound Corporate hero is missing responsive WordPress image markup" "$expected_media_fragment" "fragment absent"
done
if grep -Fq 'hero-intelligence.svg' "$STRATEGIC_MEDIA_BOUND_BODY"; then
  fail_smoke "strategic-media-bound-fallback" "Decorative SVG fallback remained active after a valid image binding" "fallback absent" "hero-intelligence.svg present"
fi
if grep -Eq 'seo-geo-corporate-native-hero__visual-column"[^>]*aria-hidden=' "$STRATEGIC_MEDIA_BOUND_BODY"; then
  fail_smoke "strategic-media-bound-a11y" "Meaningful bound hero image remained hidden from assistive technology" "no aria-hidden on visual column" "aria-hidden present"
fi

STRATEGIC_MEDIA_NON_IMAGE_ID="$(wp_cli eval '$id = wp_insert_attachment( array( "post_mime_type" => "text/plain", "post_title" => "Strategic Media Non Image", "post_status" => "inherit" ), false, 0, true ); if ( is_wp_error( $id ) ) { exit( 1 ); } echo (int) $id;' 2>"${TMP_DIR}/strategic-media-non-image.stderr" | tr -d '\r\n')"
[[ "$STRATEGIC_MEDIA_NON_IMAGE_ID" =~ ^[0-9]+$ ]] \
  || fail_smoke "strategic-media-non-image" "Could not create non-image attachment fixture" "numeric attachment ID" "$STRATEGIC_MEDIA_NON_IMAGE_ID"

STRATEGIC_MEDIA_INVALID_CODE="$(wp_cli eval "wp_set_current_user( 1 ); \$result = seo_geo_theme_set_strategic_media_slot( ${STRATEGIC_MEDIA_PAGE_ID}, 'corporate-home-v1', 'hero-media', ${STRATEGIC_MEDIA_NON_IMAGE_ID} ); echo is_wp_error( \$result ) ? \$result->get_error_code() : 'unexpected-success';" | tr -d '\r\n')"
[[ "$STRATEGIC_MEDIA_INVALID_CODE" == 'seo_geo_theme_strategic_media_attachment_invalid' ]] \
  || fail_smoke "strategic-media-invalid-guard" "Non-image attachment was not rejected by the strategic media guard" "seo_geo_theme_strategic_media_attachment_invalid" "$STRATEGIC_MEDIA_INVALID_CODE"

STRATEGIC_MEDIA_SLOT_AFTER_INVALID="$(wp_cli eval "echo seo_geo_theme_strategic_media_slot( ${STRATEGIC_MEDIA_PAGE_ID}, 'corporate-home-v1', 'hero-media' );" | tr -d '\r\n')"
STRATEGIC_MEDIA_FP_AFTER_INVALID="$(wp_cli eval "echo seo_geo_theme_strategic_media_fingerprint( ${STRATEGIC_MEDIA_PAGE_ID}, 'corporate-home-v1', 'hero-media' );" | tr -d '\r\n')"
[[ "$STRATEGIC_MEDIA_SLOT_AFTER_INVALID" == "$STRATEGIC_MEDIA_ATTACHMENT_ID" ]] \
  || fail_smoke "strategic-media-invalid-state" "Rejected non-image mutation changed the effective hero binding" "$STRATEGIC_MEDIA_ATTACHMENT_ID" "$STRATEGIC_MEDIA_SLOT_AFTER_INVALID"
[[ "$STRATEGIC_MEDIA_FP_AFTER_INVALID" == "$STRATEGIC_MEDIA_FP_BOUND" ]] \
  || fail_smoke "strategic-media-invalid-fingerprint" "Rejected non-image mutation changed the strategic media fingerprint" "$STRATEGIC_MEDIA_FP_BOUND" "$STRATEGIC_MEDIA_FP_AFTER_INVALID"

if ! STRATEGIC_MEDIA_REMOVE_RESULT="$(wp_cli eval "wp_set_current_user( 1 ); \$result = seo_geo_theme_remove_strategic_media_slot( ${STRATEGIC_MEDIA_PAGE_ID}, 'corporate-home-v1', 'hero-media' ); if ( is_wp_error( \$result ) ) { echo \$result->get_error_code(); exit( 1 ); } echo seo_geo_theme_strategic_media_slot( ${STRATEGIC_MEDIA_PAGE_ID}, 'corporate-home-v1', 'hero-media' );" 2>"${TMP_DIR}/strategic-media-remove.stderr" | tr -d '\r\n')"; then
  STRATEGIC_MEDIA_REMOVE_ERROR="$(tr -d '\r' <"${TMP_DIR}/strategic-media-remove.stderr" | head -c 400)"
  fail_smoke "strategic-media-remove" "Could not remove Corporate hero binding" "0" "${STRATEGIC_MEDIA_REMOVE_RESULT:-}${STRATEGIC_MEDIA_REMOVE_ERROR:-}"
fi
[[ "$STRATEGIC_MEDIA_REMOVE_RESULT" == "0" ]] \
  || fail_smoke "strategic-media-remove-readback" "Removed strategic media binding still reads as active" "0" "$STRATEGIC_MEDIA_REMOVE_RESULT"

STRATEGIC_MEDIA_FP_REMOVED="$(wp_cli eval "echo seo_geo_theme_strategic_media_fingerprint( ${STRATEGIC_MEDIA_PAGE_ID}, 'corporate-home-v1', 'hero-media' );" | tr -d '\r\n')"
[[ "$STRATEGIC_MEDIA_FP_REMOVED" == "$STRATEGIC_MEDIA_FP_BEFORE" ]] \
  || fail_smoke "strategic-media-fingerprint-remove" "Removing binding did not restore the original fingerprint" "$STRATEGIC_MEDIA_FP_BEFORE" "$STRATEGIC_MEDIA_FP_REMOVED"

STRATEGIC_MEDIA_CONTENT_HASH_REMOVED="$(wp_cli eval "echo hash( 'sha256', (string) get_post( ${STRATEGIC_MEDIA_PAGE_ID} )->post_content );" | tr -d '\r\n')"
[[ "$STRATEGIC_MEDIA_CONTENT_HASH_REMOVED" == "$STRATEGIC_MEDIA_CONTENT_HASH_BEFORE" ]] \
  || fail_smoke "strategic-media-remove-content" "Removing strategic media modified Gutenberg post_content" "$STRATEGIC_MEDIA_CONTENT_HASH_BEFORE" "$STRATEGIC_MEDIA_CONTENT_HASH_REMOVED"

STRATEGIC_MEDIA_META_EXISTS="$(wp_cli eval "echo metadata_exists( 'post', ${STRATEGIC_MEDIA_PAGE_ID}, '_seo_geo_theme_strategic_media_v1' ) ? '1' : '0';" | tr -d '\r\n')"
[[ "$STRATEGIC_MEDIA_META_EXISTS" == "0" ]] \
  || fail_smoke "strategic-media-empty-state" "Empty strategic media state was not removed after the last binding was deleted" "0" "$STRATEGIC_MEDIA_META_EXISTS"

STRATEGIC_MEDIA_REMOVED_BODY="${TMP_DIR}/strategic-media-removed.html"
curl -fsS "${BASE_URL}/strategic-media-home/" -o "$STRATEGIC_MEDIA_REMOVED_BODY" \
  || fail_smoke "strategic-media-removed-request" "Could not request strategic hero after removal" "HTTP 2xx" "curl failed"
grep -Fq 'assets/images/presets/corporate/v5/hero-intelligence.svg' "$STRATEGIC_MEDIA_REMOVED_BODY" \
  || fail_smoke "strategic-media-fallback-restored" "Removing binding did not restore the local SVG fallback" "hero-intelligence.svg" "fallback absent"
if grep -Fq 'alt="Strategic hero acceptance"' "$STRATEGIC_MEDIA_REMOVED_BODY"; then
  fail_smoke "strategic-media-bound-leak" "Removed hero image still appears in public HTML" "bound image absent" "alt text still present"
fi

wp_cli post delete "$STRATEGIC_MEDIA_PAGE_ID" "$STRATEGIC_MEDIA_ATTACHMENT_ID" "$STRATEGIC_MEDIA_NON_IMAGE_ID" --force >/dev/null 2>&1 || true
wp_cli eval 'delete_option( "seo_geo_active_preset" );' >/dev/null \
  || fail_smoke "strategic-media-reset" "Could not reset Corporate strategic media fixture" "preset option removed" "failed"

printf '[self-contained] Strategic hero media OK: fallback, real image binding, invalid-resource guard, unchanged post_content, fingerprint restoration and cleanup verified.\n'
