<?php
/**
 * CI-only transport fixture.
 *
 * Application Passwords normally require HTTPS (or a local environment). The
 * Manager runtime fixture is intentionally plain HTTP inside an isolated Docker
 * network, so this mu-plugin enables Application Password availability only for
 * that disposable CI installation. It is never packaged with SEO/GEO Manager.
 */

add_filter( 'wp_is_application_passwords_available', '__return_true' );
