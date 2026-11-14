#!/usr/bin/env python3
"""Emmake Corporate v5 photography bundle: immutable, scoped offline contract."""
from __future__ import annotations

import hashlib
from pathlib import Path

ROOT = Path("packages/seo-geo-theme")
ASSETS = ROOT / "assets/images/presets/corporate/v5"
EXPECTED = {
    "hero-consultoria-digital.webp": (
        57612, "3810c0d05f8929cb1c3a896a74498b8d5a118d989bce929a534d997910812984"
    ),
    "capabilities-soluciones-digitales.webp": (
        72422, "9c9e983e210fe2b8595a3c4855d39033587ae814f940fec30da86a18f36f2ca7"
    ),
    "insights-resultados-analitica.webp": (
        60992, "315e2d5f00655f18f29c2445f7a23726535a7e14f1b190b324e9d7b070a27370"
    ),
    "contact-consultoria-estrategica.webp": (
        55726, "21c11d5011e1fd0af73ffc6e2c12069696bd6910ded1d58981cc693f69aac060"
    ),
}
total = 0
for name, (size, sha) in EXPECTED.items():
    data = (ASSETS / name).read_bytes()
    assert data[:4] == b"RIFF" and data[8:12] == b"WEBP", name
    assert int.from_bytes(data[4:8], "little") + 8 == len(data), name
    assert len(data) == size and hashlib.sha256(data).hexdigest() == sha, name
    total += len(data)
assert total <= 260_000, "Four images exceed homepage payload budget"

renderer = (ROOT / "inc/Strategic/CorporateHomeRenderer.php").read_text()
css = (ROOT / "assets/css/presets/corporate-v5-a3-1.css").read_text()
for name in ("hero-consultoria-digital.webp", "insights-resultados-analitica.webp", "contact-consultoria-estrategica.webp"):
    assert renderer.count(name) == 1, name
assert "'hero-consultoria-digital.webp', 'seo-geo-corporate-hero-visual__image', 1439, 810 )" in renderer
assert 'loading="lazy" decoding="async"' in renderer
assert 'aria-hidden="true"' in renderer
for name in ("capabilities-soluciones-digitales.webp",):
    assert css.count(name) == 1, name
for marker in ("seo-geo-corporate-native-hero__visual-column", "seo-geo-corporate-card--1::before",
               "seo-geo-corporate-insights-visual__image", "seo-geo-final-cta__image",
               "@media (max-width: 820px)"):
    assert marker in css, marker
runtime = (ROOT / "inc/Strategic/StrategicSurfaceRuntime.php").read_text()
assert "add_action( 'wp_head', array( $this, 'preload_hero_art' ), 1 )" in runtime
assert "if ( ! $this->is_active() )" in runtime
assert 'rel="preload" as="image" type="image/webp"' in runtime
assert "hero-consultoria-digital.webp" in runtime
assert "body.seo-geo-corporate-home-v5 .seo-geo-corporate-native-hero__grid {" in css
assert "grid-template-columns: minmax(0, 1fr);" in css
assert "media=\"(min-width: 821px)\"" in runtime
assert "noindex" not in css  # no SEO/indexation mutation
print("PASS: 4 original optimized WebP assets, valid RIFF, hashes, rendering, CSS, responsive, lazy/eager")
