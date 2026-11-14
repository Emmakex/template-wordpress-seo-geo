#!/usr/bin/env python3
"""CI contract: Emmake interior art is site-scoped, content-preserving, immutable."""
from __future__ import annotations

import hashlib
import json
from pathlib import Path

repo = Path(__file__).resolve().parents[2]
theme = repo / "packages" / "seo-geo-theme"
manifest = json.loads((repo / "assets/manifests/emmake-inner-art.json").read_text())
assets = manifest["assets"]
assert manifest["site"] == "emmake-nuevaweb" and manifest["type"] == "decorative-render-only"
assert len(assets) == 9

css = (theme / "assets/css/presets/emmake-inner-art-v1.css").read_text()
runtime = (theme / "inc/Emmake/InnerVisualRuntime.php").read_text()
bootstrap = (theme / "functions.php").read_text()

total = 0
for slug, item in assets.items():
    art = theme / "assets/images/emmake/inner" / (slug + ".webp")
    data = art.read_bytes()
    assert data[:4] == b"RIFF" and data[8:12] == b"WEBP", slug
    assert len(data) == int.from_bytes(data[4:8], "little") + 8, slug
    assert len(data) == item["bytes"] and len(data) <= 100_000, slug
    assert hashlib.sha256(data).hexdigest() == item["sha256"], slug
    assert css.count(f'url("../../images/emmake/inner/{slug}.webp")') == 1, slug
    assert runtime.count(f"'{slug}'") == 1, slug
    assert "wp-content/uploads" not in css
    total += len(data)

assert total < 500_000, total
assert "Emmake/InnerVisualRuntime.php" in bootstrap
assert "InnerVisualRuntime::register()" in bootstrap
for guard in (
    "is_front_page()", "is_admin()", "is_home()", "is_singular( 'page' )",
    "seo_geo_theme_active_preset_id", "'corporate'", "'emmake.com'",
    "'/nuevaweb'", "self::SLUGS",
):
    assert guard in runtime, guard
assert "add_filter( 'body_class'" in runtime
assert "add_action( 'wp_enqueue_scripts'" in runtime
assert "render_block" not in runtime and "the_content" not in runtime
assert "content:" in css and "background-image:" in css
assert "media (max-width: 650px)" in css
assert "seo-geo-emmake-inner-visual-politica-de-privacidad" in css
assert not any(bad in css for bad in ("https://", "http://", "display: none"))
print(f"PASS: {len(assets)} original first-party WebP image references, {total} B, all render-only")
