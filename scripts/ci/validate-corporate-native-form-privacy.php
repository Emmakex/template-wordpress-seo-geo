<?php
declare(strict_types=1);

/** Unit contract: the native form links ONLY the WordPress-configured policy. */
namespace {
    $GLOBALS['privacy_contract_url'] = 'https://example.test/politica-de-privacidad/';
    $GLOBALS['privacy_contract_locale'] = 'es_ES';
    class WP_Block {
        public array $context = [];
    }
    function absint($v): int { return abs((int) $v); }
    function get_the_ID(): int { return 50085; }
    function get_post_meta(int $id, string $key, bool $single): array {
        if ($id !== 50085 || $key !== '_seo_geo_contact_forms_v1' || !$single) {
            return [];
        }
        return [
            'contacto' => [
                'fields'=>[
                    ['id'=>'nombre','label'=>'Nombre','type'=>'text','required'=>true],
                    ['id'=>'email','label'=>'Email','type'=>'email','required'=>true],
                    ['id'=>'mensaje','label'=>'Mensaje','type'=>'textarea','required'=>true],
                ],
                'button_text'=>'Enviar',
            ],
        ];
    }
    function sanitize_key(string $s): string {return preg_replace('/[^a-z0-9_\-]/','',strtolower($s))??'';}
    function esc_attr(string $s): string {return htmlspecialchars($s,ENT_QUOTES,'UTF-8');}
    function esc_html(string $s): string {return htmlspecialchars($s,ENT_QUOTES,'UTF-8');}
    function esc_url(string $s): string {return htmlspecialchars($s,ENT_QUOTES,'UTF-8');}
    function admin_url(string $s): string {return 'https://example.test/wp-admin/'.$s;}
    function wp_nonce_field(string $action, string $name, bool $referer, bool $echo): string {
        return '<input type="hidden" name="'.esc_attr($name).'" value="fixture">';
    }
    function get_privacy_policy_url(): string {return $GLOBALS['privacy_contract_url'];}
    function get_locale(): string {return $GLOBALS['privacy_contract_locale'];}
    function __(string $s, string $domain): string {return $s;}
}
namespace SeoGeo\Theme\Forms {
    require_once dirname(__DIR__, 2).'/packages/seo-geo-theme/inc/Forms/ContactFormRuntime.php';
    $form = new ContactFormRuntime();
    $html = $form->render(['formId'=>'contacto']);
    if (substr_count($html,'seo-geo-contact-form-privacy')!==1
        || !str_contains($html,'href="https://example.test/politica-de-privacidad/"')
        || !str_contains($html,'política de privacidad')
        || substr_count($html, ' required aria-required="true"')!==3
        || !str_contains($html,'name="action" value="seo_geo_contact_submit"')
        || !str_contains($html,'type="submit">Enviar')) {
        throw new \RuntimeException('Spanish configured WordPress privacy link/form semantics failed.');
    }
    if (strpos($html,'seo-geo-contact-form-privacy') > strpos($html,'type="submit">Enviar')) {
        throw new \RuntimeException('Privacy link must precede submit.');
    }
    $GLOBALS['privacy_contract_url']='';
    $html = $form->render(['formId'=>'contacto']);
    if (str_contains($html,'seo-geo-contact-form-privacy') || substr_count($html,'required aria-required="true"')!==3) {
        throw new \RuntimeException('No configured policy: must omit link, preserve form requirements.');
    }
    $GLOBALS['privacy_contract_url']='https://example.test/privacy/';
    $GLOBALS['privacy_contract_locale']='en_US';
    $html = $form->render(['formId'=>'contacto']);
    if (!str_contains($html,'privacy policy') || str_contains($html,'política de privacidad')
        || !str_contains($html,'href="https://example.test/privacy/"')) {
        throw new \RuntimeException('Non-Spanish configured policy link failed.');
    }
    echo "Native form WP-configured privacy policy: OK (ES/EN, no-page fallback, fields preserved)\n";
}
