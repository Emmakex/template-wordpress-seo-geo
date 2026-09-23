<?php
/**
 * Built-in Migration Bridge operator copy.
 *
 * @package SeoGeoMigrationBridge
 */

declare(strict_types=1);

namespace SeoGeo\MigrationBridge\Operator;

/**
 * Resolves one key-complete EN/ES operator catalog without runtime dependencies.
 */
final class OperatorCopy {
	/**
	 * Explicit locale override.
	 *
	 * @var string|null
	 */
	private ?string $locale;

	/**
	 * Create a copy resolver.
	 *
	 * @param string|null $locale Optional locale override.
	 */
	public function __construct( ?string $locale = null ) {
		$this->locale = $locale;
	}

	/**
	 * Return all shipped operator catalogs.
	 *
	 * @return array<string,array<string,string>>
	 */
	public static function catalogs(): array {
		return array(
			'en' => array(
				'page_title'                    => 'SEO/GEO Migration Bridge',
				'menu_title'                    => 'SEO/GEO Migration',
				'intro'                         => 'Review migration status and the safest next step. This screen is read-only and does not execute migration, cutover, rollback, or report persistence actions.',
				'forbidden'                     => 'You do not have permission to view Migration Bridge status.',
				'overview_heading'              => 'Migration status',
				'review_heading'                => 'Review status',
				'dependency_heading'            => 'Dependency classifications',
				'next_step_heading'             => 'Recommended next step',
				'privacy_heading'               => 'Safety and privacy',
				'privacy_text'                  => 'This screen shows bounded status metadata only. It does not expose post bodies, builder payloads, credentials, or raw recovery artifacts.',
				'label_baseline'                => 'SEO/GEO baseline',
				'label_dependency_plan'         => 'Dependency plan',
				'label_cutover'                 => 'Production cutover',
				'label_final_report'            => 'Final migration report',
				'label_bridge_disposition'      => 'Migration Bridge disposition',
				'label_blocking_review'         => 'Blocking review items',
				'label_advisory_review'         => 'Advisory review items',
				'label_classification'          => 'Classification',
				'label_count'                   => 'Count',
				'status_ready'                  => 'Ready',
				'status_missing'                => 'Missing',
				'status_accepted'               => 'Accepted',
				'status_pending'                => 'Pending',
				'status_needs_attention'        => 'Needs attention',
				'status_not_applicable'         => 'Not applicable',
				'disposition_remove'            => 'Remove after final verification',
				'disposition_retain_audit_only' => 'Retain in audit-only mode',
				'disposition_retain_operational'=> 'Retain while migration is operational',
				'next_capture_baseline'         => 'Capture and persist the public SEO/GEO baseline before planning migration changes.',
				'next_continue_migration'       => 'Continue the sandbox migration and parity workflow before production cutover.',
				'next_complete_cutover'         => 'Complete and explicitly accept the reversible production cutover before final handoff.',
				'next_generate_report'          => 'Generate and persist the final migration report after the accepted cutover.',
				'next_resolve_blockers'         => 'Resolve blocking migration review items before the bridge can be retired.',
				'next_review_advisories'        => 'Review the remaining advisory items. Runtime migration work is complete; the bridge may remain audit-only if desired.',
				'next_remove_bridge'            => 'Archive the final handoff evidence and remove the Migration Bridge after final operational verification.',
			),
			'es' => array(
				'page_title'                    => 'Puente de migración SEO/GEO',
				'menu_title'                    => 'Migración SEO/GEO',
				'intro'                         => 'Revisa el estado de la migración y el siguiente paso más seguro. Esta pantalla es de solo lectura y no ejecuta acciones de migración, cambio a producción, reversión ni persistencia del informe.',
				'forbidden'                     => 'No tienes permisos para consultar el estado del Puente de migración.',
				'overview_heading'              => 'Estado de la migración',
				'review_heading'                => 'Estado de revisión',
				'dependency_heading'            => 'Clasificaciones de dependencias',
				'next_step_heading'             => 'Siguiente paso recomendado',
				'privacy_heading'               => 'Seguridad y privacidad',
				'privacy_text'                  => 'Esta pantalla muestra únicamente metadatos de estado acotados. No expone cuerpos de entradas, cargas de constructores, credenciales ni artefactos de recuperación en bruto.',
				'label_baseline'                => 'Línea base SEO/GEO',
				'label_dependency_plan'         => 'Plan de dependencias',
				'label_cutover'                 => 'Cambio a producción',
				'label_final_report'            => 'Informe final de migración',
				'label_bridge_disposition'      => 'Disposición del Puente de migración',
				'label_blocking_review'         => 'Elementos de revisión bloqueantes',
				'label_advisory_review'         => 'Elementos de revisión recomendados',
				'label_classification'          => 'Clasificación',
				'label_count'                   => 'Cantidad',
				'status_ready'                  => 'Listo',
				'status_missing'                => 'Falta',
				'status_accepted'               => 'Aceptado',
				'status_pending'                => 'Pendiente',
				'status_needs_attention'        => 'Requiere atención',
				'status_not_applicable'         => 'No aplica',
				'disposition_remove'            => 'Eliminar tras la verificación final',
				'disposition_retain_audit_only' => 'Mantener solo para auditoría',
				'disposition_retain_operational'=> 'Mantener mientras la migración siga operativa',
				'next_capture_baseline'         => 'Captura y guarda la línea base pública SEO/GEO antes de planificar cambios de migración.',
				'next_continue_migration'       => 'Continúa la migración en sandbox y la validación de paridad antes del cambio a producción.',
				'next_complete_cutover'         => 'Completa y acepta explícitamente el cambio reversible a producción antes de la entrega final.',
				'next_generate_report'          => 'Genera y guarda el informe final de migración después de aceptar el cambio a producción.',
				'next_resolve_blockers'         => 'Resuelve los elementos bloqueantes de revisión antes de retirar el puente.',
				'next_review_advisories'        => 'Revisa los elementos recomendados restantes. El trabajo operativo de migración está completo; el puente puede mantenerse solo para auditoría si se desea.',
				'next_remove_bridge'            => 'Archiva la evidencia final de entrega y elimina el Puente de migración después de la verificación operativa final.',
			),
		);
	}

	/**
	 * Resolve one localized key with English fallback.
	 *
	 * @param string $key Catalog key.
	 */
	public function text( string $key ): string {
		$catalogs = self::catalogs();
		$locale   = $this->locale ?? get_user_locale();
		$language = str_starts_with( strtolower( $locale ), 'es' ) ? 'es' : 'en';

		return $catalogs[ $language ][ $key ] ?? $catalogs['en'][ $key ] ?? $key;
	}
}
