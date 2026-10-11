<?php
/**
 * Deterministic runtime identity for the installed Manager package tree.
 *
 * @package SeoGeoManager
 */

declare(strict_types=1);

namespace SeoGeo\Manager\Support;

use FilesystemIterator;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;

/** Build the same content fingerprint emitted by the deterministic release builder. */
final class PackageIdentity {
	private const ALGORITHM = 'sha256-tree-v1';

	/**
	 * Return a bounded identity for the installed plugin tree.
	 *
	 * @return array{algorithm:string,fingerprint:string,file_count:int,version:string}
	 */
	public static function snapshot(): array {
		$files   = self::files();
		$context = hash_init( 'sha256' );

		foreach ( $files as $relative => $absolute ) {
			$size = filesize( $absolute );
			$size = false === $size ? 0 : $size;
			hash_update( $context, $relative . "\0" . (string) $size . "\0" );

			$handle = fopen( $absolute, 'rb' );
			if ( false === $handle ) {
				continue;
			}
			while ( ! feof( $handle ) ) {
				$chunk = fread( $handle, 1024 * 1024 );
				if ( false === $chunk ) {
					break;
				}
				hash_update( $context, $chunk );
			}
			fclose( $handle );
			hash_update( $context, "\0" );
		}

		return array(
			'algorithm'   => self::ALGORITHM,
			'fingerprint' => hash_final( $context ),
			'file_count'  => count( $files ),
			'version'     => defined( 'SEO_GEO_MANAGER_VERSION' ) ? (string) SEO_GEO_MANAGER_VERSION : '',
		);
	}

	/**
	 * @return array<string, string>
	 */
	private static function files(): array {
		$root = realpath( SEO_GEO_MANAGER_DIR );
		if ( false === $root ) {
			return array();
		}

		$root  = rtrim( str_replace( '\\', '/', $root ), '/' );
		$files = array();
		$iterator = new RecursiveIteratorIterator(
			new RecursiveDirectoryIterator( $root, FilesystemIterator::SKIP_DOTS )
		);

		foreach ( $iterator as $file ) {
			if ( ! $file instanceof SplFileInfo || ! $file->isFile() ) {
				continue;
			}

			$absolute = str_replace( '\\', '/', $file->getPathname() );
			$relative = ltrim( substr( $absolute, strlen( $root ) ), '/' );
			if ( '' === $relative || '.DS_Store' === $file->getFilename() || self::has_excluded_segment( $relative ) ) {
				continue;
			}
			$files[ $relative ] = $absolute;
		}

		ksort( $files, SORT_STRING );

		return $files;
	}

	private static function has_excluded_segment( string $relative ): bool {
		$segments = explode( '/', $relative );

		return in_array( '__pycache__', $segments, true );
	}
}
