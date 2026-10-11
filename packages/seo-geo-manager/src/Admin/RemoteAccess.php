<?php
/**
 * Local security console for the SEO/GEO Manager remote bridge.
 *
 * @package SeoGeoManager
 */

declare(strict_types=1);

namespace SeoGeo\Manager\Admin;

use SeoGeo\Manager\Remote\Audit;
use SeoGeo\Manager\Remote\Credentials;
use SeoGeo\Manager\Remote\TokenService;
use WP_Error;

final class RemoteAccess {
	private const TOKEN_TRANSIENT_PREFIX  = 'sgm_remote_credential_';
	private const NOTICE_TRANSIENT_PREFIX = 'sgm_remote_notice_';

	public static function register(): void {
		add_action( 'admin_menu', array( self::class, 'register_menu' ) );
		add_action( 'admin_post_seo_geo_manager_remote', array( self::class, 'handle_action' ) );
	}

	public static function register_menu(): void {
		add_submenu_page(
			'seo-geo-manager',
			__( 'Conexión remota', 'seo-geo-manager' ),
			__( 'Conexión remota', 'seo-geo-manager' ),
			'manage_options',
			'seo-geo-manager-remote',
			array( self::class, 'render' )
		);
	}

	public static function render(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'No tienes permisos para gestionar la conexión remota del Manager.', 'seo-geo-manager' ) );
		}

		$credentials = new Credentials();
		$state       = $credentials->state();
		$supported   = Credentials::supported_scopes();
		$granted     = is_array( $state['scopes'] ?? null ) ? $state['scopes'] : array();
		$audit       = ( new Audit() )->recent( 20 );
		$credential  = get_transient( self::token_key() );
		$notice      = get_transient( self::notice_key() );

		if ( is_string( $credential ) && '' !== $credential ) {
			delete_transient( self::token_key() );
		} else {
			$credential = '';
		}
		if ( is_array( $notice ) ) {
			delete_transient( self::notice_key() );
		} else {
			$notice = array();
		}
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'SEO/GEO Manager · Conexión remota', 'seo-geo-manager' ); ?></h1>
			<p><?php esc_html_e( 'Consola local para autorizar el puente ChatGPT → SEO/GEO Manager. La conexión se puede rotar, desactivar o revocar desde WordPress sin compartir la contraseña del administrador.', 'seo-geo-manager' ); ?></p>

			<?php if ( $notice ) : ?>
				<div class="notice notice-<?php echo esc_attr( (string) ( $notice['type'] ?? 'info' ) ); ?> inline"><p><?php echo esc_html( (string) ( $notice['message'] ?? '' ) ); ?></p></div>
			<?php endif; ?>

			<?php if ( '' !== $credential ) : ?>
				<div class="notice notice-warning inline">
					<p><strong><?php esc_html_e( 'Copia esta credencial ahora. Solo se muestra una vez; el Manager almacena únicamente su hash.', 'seo-geo-manager' ); ?></strong></p>
					<p><code style="word-break:break-all;user-select:all"><?php echo esc_html( $credential ); ?></code></p>
				</div>
			<?php endif; ?>

			<h2><?php esc_html_e( 'Estado de la conexión', 'seo-geo-manager' ); ?></h2>
			<table class="widefat striped" style="max-width:1000px">
				<tbody>
					<tr><th><?php esc_html_e( 'Connection ID', 'seo-geo-manager' ); ?></th><td><code><?php echo esc_html( (string) ( $state['connection_id'] ?? '' ) ); ?></code></td></tr>
					<tr><th><?php esc_html_e( 'Etiqueta', 'seo-geo-manager' ); ?></th><td><?php echo esc_html( (string) ( $state['label'] ?? '' ) ); ?></td></tr>
					<tr><th><?php esc_html_e( 'Acceso remoto', 'seo-geo-manager' ); ?></th><td><strong><?php echo ! empty( $state['enabled'] ) ? esc_html__( 'ACTIVO', 'seo-geo-manager' ) : esc_html__( 'DESACTIVADO', 'seo-geo-manager' ); ?></strong></td></tr>
					<tr>
						<th><?php esc_html_e( 'Credencial', 'seo-geo-manager' ); ?></th>
						<td>
							<?php echo ! empty( $state['credential_present'] ) ? esc_html__( 'Presente', 'seo-geo-manager' ) : esc_html__( 'No generada', 'seo-geo-manager' ); ?>
							<?php if ( ! empty( $state['credential_prefix'] ) ) : ?>
								— <code><?php echo esc_html( (string) $state['credential_prefix'] ); ?>…</code>
							<?php endif; ?>
						</td>
					</tr>
					<tr><th><?php esc_html_e( 'Escrituras concedidas', 'seo-geo-manager' ); ?></th><td><?php echo ! empty( $state['writes_enabled'] ) ? esc_html__( 'Sí', 'seo-geo-manager' ) : esc_html__( 'No', 'seo-geo-manager' ); ?></td></tr>
					<tr><th><?php esc_html_e( 'Administrador vinculado', 'seo-geo-manager' ); ?></th><td><?php echo esc_html( (string) (int) ( $state['wordpress_user_id'] ?? 0 ) ); ?></td></tr>
					<tr><th><?php esc_html_e( 'Generación', 'seo-geo-manager' ); ?></th><td><?php echo esc_html( (string) (int) ( $state['generation'] ?? 0 ) ); ?></td></tr>
					<tr><th><?php esc_html_e( 'Última rotación', 'seo-geo-manager' ); ?></th><td><?php echo esc_html( (string) ( $state['rotated_at'] ?? '' ) ); ?></td></tr>
					<tr><th><?php esc_html_e( 'Último uso', 'seo-geo-manager' ); ?></th><td><?php echo esc_html( (string) ( $state['last_used_at'] ?? '' ) ); ?></td></tr>
					<tr><th><?php esc_html_e( 'Último request ID', 'seo-geo-manager' ); ?></th><td><code><?php echo esc_html( (string) ( $state['last_request_id'] ?? '' ) ); ?></code></td></tr>
				</tbody>
			</table>

			<h2><?php esc_html_e( 'Controles', 'seo-geo-manager' ); ?></h2>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="display:flex;gap:8px;flex-wrap:wrap;align-items:center;margin-bottom:18px">
				<input type="hidden" name="action" value="seo_geo_manager_remote">
				<?php wp_nonce_field( 'seo_geo_manager_remote_connection' ); ?>
				<button class="button button-primary" name="remote_action" value="generate" type="submit"><?php esc_html_e( 'Generar / reemplazar credencial', 'seo-geo-manager' ); ?></button>
				<button class="button" name="remote_action" value="rotate" type="submit" <?php disabled( empty( $state['credential_present'] ) ); ?>><?php esc_html_e( 'Rotar', 'seo-geo-manager' ); ?></button>
				<?php if ( ! empty( $state['enabled'] ) ) : ?>
					<button class="button" name="remote_action" value="disable" type="submit"><?php esc_html_e( 'Desactivar', 'seo-geo-manager' ); ?></button>
				<?php else : ?>
					<button class="button" name="remote_action" value="enable" type="submit" <?php disabled( empty( $state['credential_present'] ) ); ?>><?php esc_html_e( 'Activar', 'seo-geo-manager' ); ?></button>
				<?php endif; ?>
				<button class="button" name="remote_action" value="revoke" type="submit" <?php disabled( empty( $state['credential_present'] ) ); ?>><?php esc_html_e( 'Revocar', 'seo-geo-manager' ); ?></button>
			</form>

			<h2><?php esc_html_e( 'Scopes', 'seo-geo-manager' ); ?></h2>
			<p><?php esc_html_e( 'Los scopes limitan las superficies remotas disponibles. Las escrituras seguirán pasando por Preview → Apply → Verify y rollback cuando el contrato lo permita.', 'seo-geo-manager' ); ?></p>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="max-width:1000px">
				<input type="hidden" name="action" value="seo_geo_manager_remote">
				<input type="hidden" name="remote_action" value="save_scopes">
				<?php wp_nonce_field( 'seo_geo_manager_remote_connection' ); ?>
				<fieldset style="display:grid;grid-template-columns:repeat(auto-fit,minmax(220px,1fr));gap:8px 18px;margin:12px 0 18px">
					<?php foreach ( $supported as $scope ) : ?>
						<label><input type="checkbox" name="scopes[]" value="<?php echo esc_attr( $scope ); ?>" <?php checked( in_array( $scope, $granted, true ) ); ?>> <code><?php echo esc_html( $scope ); ?></code></label>
					<?php endforeach; ?>
				</fieldset>
				<?php submit_button( __( 'Guardar scopes', 'seo-geo-manager' ), 'secondary', 'submit', false ); ?>
			</form>

			<h2><?php esc_html_e( 'Seguridad del puente', 'seo-geo-manager' ); ?></h2>
			<p><?php esc_html_e( 'La nueva línea remota usa credencial propia del Manager, scopes, request ID, nonce, timestamp, rate limit, idempotencia y auditoría acotada. No expone shell ni proxy REST genérico.', 'seo-geo-manager' ); ?></p>

			<h2><?php esc_html_e( 'Auditoría remota reciente', 'seo-geo-manager' ); ?></h2>
			<?php if ( ! $audit ) : ?>
				<p><?php esc_html_e( 'Todavía no hay eventos remotos registrados.', 'seo-geo-manager' ); ?></p>
			<?php else : ?>
				<table class="widefat striped" style="max-width:1200px">
					<thead><tr><th><?php esc_html_e( 'Fecha', 'seo-geo-manager' ); ?></th><th><?php esc_html_e( 'Evento', 'seo-geo-manager' ); ?></th><th><?php esc_html_e( 'Resultado', 'seo-geo-manager' ); ?></th><th><?php esc_html_e( 'Ruta', 'seo-geo-manager' ); ?></th><th><?php esc_html_e( 'Scope', 'seo-geo-manager' ); ?></th><th><?php esc_html_e( 'Request ID', 'seo-geo-manager' ); ?></th><th><?php esc_html_e( 'Error', 'seo-geo-manager' ); ?></th></tr></thead>
					<tbody>
						<?php foreach ( $audit as $entry ) : ?>
							<tr>
								<td><?php echo esc_html( (string) ( $entry['timestamp'] ?? '' ) ); ?></td>
								<td><?php echo esc_html( (string) ( $entry['event'] ?? '' ) ); ?></td>
								<td><?php echo esc_html( (string) ( $entry['outcome'] ?? '' ) ); ?></td>
								<td><code><?php echo esc_html( (string) ( $entry['route'] ?? '' ) ); ?></code></td>
								<td><code><?php echo esc_html( (string) ( $entry['scope'] ?? '' ) ); ?></code></td>
								<td><code><?php echo esc_html( (string) ( $entry['request_id'] ?? '' ) ); ?></code></td>
								<td><code><?php echo esc_html( (string) ( $entry['error_code'] ?? '' ) ); ?></code></td>
							</tr>
						<?php endforeach; ?>
					</tbody>
				</table>
			<?php endif; ?>
		</div>
		<?php
	}

	public static function handle_action(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'No tienes permisos para gestionar la conexión remota del Manager.', 'seo-geo-manager' ) );
		}
		check_admin_referer( 'seo_geo_manager_remote_connection' );

		$credentials = new Credentials();
		$tokens      = new TokenService( $credentials );
		$audit       = new Audit();
		$action      = sanitize_key( (string) ( $_POST['remote_action'] ?? '' ) );
		$user_id     = get_current_user_id();
		$result      = null;
		$message     = '';

		switch ( $action ) {
			case 'generate':
				$state  = $credentials->state();
				$scopes = is_array( $state['scopes'] ?? null ) && $state['scopes'] ? $state['scopes'] : Credentials::default_scopes();
				$result = $tokens->issue( $scopes, $user_id );
				$message = __( 'Credencial remota generada. Copia el valor de un solo uso mostrado en esta página.', 'seo-geo-manager' );
				break;
			case 'rotate':
				$result = $tokens->rotate( $user_id );
				$message = __( 'Credencial remota rotada. La anterior ya no es válida.', 'seo-geo-manager' );
				break;
			case 'enable':
				$result = $credentials->set_enabled( true );
				$message = __( 'Acceso remoto activado.', 'seo-geo-manager' );
				break;
			case 'disable':
				$result = $credentials->set_enabled( false );
				$message = __( 'Acceso remoto desactivado.', 'seo-geo-manager' );
				break;
			case 'revoke':
				$result = $tokens->revoke();
				$message = __( 'Credencial remota revocada.', 'seo-geo-manager' );
				break;
			case 'save_scopes':
				$scopes = isset( $_POST['scopes'] ) && is_array( $_POST['scopes'] )
					? array_map( 'sanitize_text_field', wp_unslash( $_POST['scopes'] ) )
					: array();
				$result = $credentials->set_scopes( $scopes );
				$message = __( 'Scopes de la conexión actualizados.', 'seo-geo-manager' );
				break;
			default:
				$result = new WP_Error( 'validation_failed', __( 'Acción remota desconocida.', 'seo-geo-manager' ) );
		}

		if ( is_wp_error( $result ) ) {
			self::notice( 'error', $result->get_error_message() );
		} else {
			if ( is_array( $result ) && isset( $result['credential'] ) && is_string( $result['credential'] ) ) {
				set_transient( self::token_key(), $result['credential'], 5 * MINUTE_IN_SECONDS );
			}
			self::notice( 'success', $message );
			$state = $credentials->state();
			$audit->record(
				array(
					'connection_id'     => (string) ( $state['connection_id'] ?? '' ),
					'wordpress_user_id' => $user_id,
					'event'             => 'local-connection-' . $action,
					'outcome'           => 'success',
					'route'             => 'wp-admin',
					'scope'             => 'credentials.manage',
				)
			);
		}

		wp_safe_redirect( admin_url( 'admin.php?page=seo-geo-manager-remote' ) );
		exit;
	}

	private static function token_key(): string {
		return self::TOKEN_TRANSIENT_PREFIX . get_current_user_id();
	}

	private static function notice_key(): string {
		return self::NOTICE_TRANSIENT_PREFIX . get_current_user_id();
	}

	private static function notice( string $type, string $message ): void {
		set_transient( self::notice_key(), array( 'type' => $type, 'message' => $message ), 5 * MINUTE_IN_SECONDS );
	}
}
