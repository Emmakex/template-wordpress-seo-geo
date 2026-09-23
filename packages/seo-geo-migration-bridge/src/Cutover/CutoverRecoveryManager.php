<?php
/**
 * Phase 8G recovery snapshot preparation.
 *
 * @package SeoGeoMigrationBridge
 */

declare(strict_types=1);

namespace SeoGeo\MigrationBridge\Cutover;

use SeoGeo\MigrationBridge\BaselineSnapshotStore;
use SeoGeo\MigrationBridge\Parity\ParityAllowlist;
use SeoGeo\MigrationBridge\Sandbox\SandboxGuard;

/**
 * Builds a provider-neutral recovery manifest before production cutover.
 */
final class CutoverRecoveryManager {
	/**
	 * Backup evidence validator.
	 *
	 * @var BackupEvidenceValidator
	 */
	private BackupEvidenceValidator $backup_validator;

	/**
	 * Snapshot history store.
	 *
	 * @var CutoverSnapshotStore
	 */
	private CutoverSnapshotStore $store;

	/**
	 * Construct recovery manager.
	 */
	public function __construct(
		?BackupEvidenceValidator $backup_validator = null,
		?CutoverSnapshotStore $store = null
	) {
		$this->backup_validator = $backup_validator ?? new BackupEvidenceValidator();
		$this->store            = $store ?? new CutoverSnapshotStore();
	}

	/**
	 * Prepare and persist a recovery manifest.
	 *
	 * @param array<string,mixed> $backup_evidence       External database/uploads backup evidence.
	 * @param array<string,mixed> $accepted_parity       Accepted Phase 8F parity report.
	 * @param array<int,mixed>    $allowlist_rules       Exact Phase 8F intentional-difference rules.
	 * @param array<string,mixed> $quality_evidence      Accessibility/performance evidence.
	 * @return array{saved:bool,id:string|null,reason:string|null,blockers:list<string>}
	 */
	public function prepare(
		array $backup_evidence,
		array $accepted_parity,
		array $allowlist_rules,
		array $quality_evidence
	): array {
		$blockers = array();

		if ( SandboxGuard::enabled() ) {
			$blockers[] = 'sandbox-marker-still-enabled';
		}

		$backup = $this->backup_validator->validate( $backup_evidence );
		foreach ( $backup['errors'] as $error ) {
			$blockers[] = $error;
		}

		if (
			1 !== ( $accepted_parity['schema_version'] ?? null )
			|| 'seo-geo-parity' !== ( $accepted_parity['mode'] ?? null )
			|| true !== ( $accepted_parity['accepted'] ?? false )
			|| 0 !== (int) ( $accepted_parity['summary']['regressions'] ?? -1 )
			|| 0 !== (int) ( $accepted_parity['summary']['unknown'] ?? -1 )
		) {
			$blockers[] = 'accepted-phase-8f-parity-required';
		}

		$quality = $this->quality_evidence( $quality_evidence );
		foreach ( $quality['errors'] as $error ) {
			$blockers[] = $error;
		}

		$baseline = ( new BaselineSnapshotStore() )->latest();
		if (
			! is_array( $baseline )
			|| ! isset( $baseline['id'], $baseline['sha256'], $baseline['snapshot'] )
			|| ! is_string( $baseline['id'] )
			|| ! is_string( $baseline['sha256'] )
			|| ! is_array( $baseline['snapshot'] )
		) {
			$blockers[] = 'phase-8b-baseline-missing';
		}

		$normalized_rules = $this->allowlist_rules( $allowlist_rules );
		if ( count( $normalized_rules ) !== count( $allowlist_rules ) ) {
			$blockers[] = 'parity-allowlist-rule-invalid';
		}

		$blockers = array_values( array_unique( $blockers ) );
		sort( $blockers );

		if ( array() !== $blockers || ! is_array( $baseline ) ) {
			return array(
				'saved'    => false,
				'id'       => null,
				'reason'   => 'recovery-snapshot-blocked',
				'blockers' => $blockers,
			);
		}

		$active_plugins = get_option( 'active_plugins', array() );
		$active_plugins = is_array( $active_plugins )
			? array_values( array_filter( $active_plugins, 'is_string' ) )
			: array();
		sort( $active_plugins );

		$redirects = isset( $baseline['snapshot']['redirects'] ) && is_array( $baseline['snapshot']['redirects'] )
			? $baseline['snapshot']['redirects']
			: array();

		$snapshot = array(
			'backup_evidence' => $backup['evidence'],
			'wordpress_state' => array(
				'stylesheet'          => get_stylesheet(),
				'template'            => get_template(),
				'active_plugins'      => $active_plugins,
				'permalink_structure' => (string) get_option( 'permalink_structure', '' ),
				'blog_public'         => (string) get_option( 'blog_public', '1' ),
			),
			'baseline'        => array(
				'id'      => $baseline['id'],
				'sha256'  => $baseline['sha256'],
				'redirects_sha256' => ParityAllowlist::fingerprint( $redirects ),
			),
			'parity'          => array(
				'report_sha256' => ParityAllowlist::fingerprint( $accepted_parity ),
				'allowlist'     => $normalized_rules,
			),
			'quality'         => $quality['evidence'],
			'safety'          => array(
				'provider_neutral_backup_evidence' => true,
				'files_deleted'                    => false,
				'plugin_files_deleted'             => false,
				'theme_files_deleted'              => false,
				'rollback_required_until_acceptance' => true,
			),
		);
		$snapshot['state_sha256'] = ParityAllowlist::fingerprint( $snapshot['wordpress_state'] );

		$saved = $this->store->create( $snapshot );

		return array(
			'saved'    => $saved['saved'],
			'id'       => $saved['id'],
			'reason'   => $saved['reason'],
			'blockers' => array(),
		);
	}

	/**
	 * Validate quality evidence.
	 *
	 * @param array<string,mixed> $evidence Raw evidence.
	 * @return array{valid:bool,evidence:array<string,array<string,mixed>>,errors:list<string>}
	 */
	private function quality_evidence( array $evidence ): array {
		$errors     = array();
		$normalized = array();

		foreach ( array( 'accessibility', 'performance' ) as $key ) {
			$row = $evidence[ $key ] ?? null;
			if ( ! is_array( $row ) ) {
				$errors[] = 'quality-evidence-missing:' . $key;
				continue;
			}

			$reference = isset( $row['reference'] ) && is_string( $row['reference'] ) ? trim( $row['reference'] ) : '';
			$sha256    = isset( $row['sha256'] ) && is_string( $row['sha256'] ) ? strtolower( trim( $row['sha256'] ) ) : '';
			$passed    = true === ( $row['passed'] ?? false );

			if ( '' === $reference || 200 < strlen( $reference ) ) {
				$errors[] = 'quality-reference-invalid:' . $key;
			}
			if ( 1 !== preg_match( '/^[a-f0-9]{64}$/', $sha256 ) ) {
				$errors[] = 'quality-sha256-invalid:' . $key;
			}
			if ( ! $passed ) {
				$errors[] = 'quality-gate-not-passed:' . $key;
			}

			$normalized[ $key ] = array(
				'reference' => $reference,
				'sha256'    => $sha256,
				'passed'    => $passed,
			);
		}

		$errors = array_values( array_unique( $errors ) );
		sort( $errors );

		return array(
			'valid'    => array() === $errors,
			'evidence' => $normalized,
			'errors'   => $errors,
		);
	}

	/**
	 * Normalize exact parity allowlist rules.
	 *
	 * @param array<int,mixed> $rules Raw exact rules.
	 * @return list<array{id:string,path:string,signal:string,before_sha256:string,after_sha256:string,reason:string}>
	 */
	private function allowlist_rules( array $rules ): array {
		$normalized = array();

		foreach ( $rules as $index => $rule ) {
			if ( ! is_array( $rule ) ) {
				continue;
			}

			$path          = isset( $rule['path'] ) && is_string( $rule['path'] ) ? ParityAllowlist::normalize_path( $rule['path'] ) : '';
			$signal        = isset( $rule['signal'] ) && is_string( $rule['signal'] ) ? ParityAllowlist::normalize_signal( $rule['signal'] ) : '';
			$before_sha256 = isset( $rule['before_sha256'] ) && is_string( $rule['before_sha256'] ) ? strtolower( trim( $rule['before_sha256'] ) ) : '';
			$after_sha256  = isset( $rule['after_sha256'] ) && is_string( $rule['after_sha256'] ) ? strtolower( trim( $rule['after_sha256'] ) ) : '';
			$reason        = isset( $rule['reason'] ) && is_string( $rule['reason'] ) ? trim( $rule['reason'] ) : '';
			$id            = isset( $rule['id'] ) && is_string( $rule['id'] ) ? sanitize_key( $rule['id'] ) : 'rule-' . ( $index + 1 );

			if (
				'' === $path
				|| '' === $signal
				|| 1 !== preg_match( '/^[a-f0-9]{64}$/', $before_sha256 )
				|| 1 !== preg_match( '/^[a-f0-9]{64}$/', $after_sha256 )
				|| '' === $reason
			) {
				continue;
			}

			$normalized[] = array(
				'id'            => $id,
				'path'          => $path,
				'signal'        => $signal,
				'before_sha256' => $before_sha256,
				'after_sha256'  => $after_sha256,
				'reason'        => $reason,
			);
		}

		return $normalized;
	}
}
