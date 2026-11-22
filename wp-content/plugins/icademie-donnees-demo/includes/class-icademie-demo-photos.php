<?php
/**
 * Lot de photos des annonces de démonstration.
 *
 * Le lot est un fichier zip : les photos (JPEG, PNG ou WebP) et « photos.json », qui décrit chaque
 * photo (fichier, catégorie, eau, rôle, auteur, source, licence). Il est préparé hors du site
 * (photos libres de droits de Pixabay), déposé sur la page Outils › Données de démonstration, puis
 * importé dans la médiathèque au début de la génération.
 *
 * @package Icademie_Donnees_Demo
 */

defined( 'ABSPATH' ) || exit;

/**
 * Lot de photos.
 */
final class Icademie_Demo_Photos {

	/** Option : photos du lot déposé (« photos » : description de chaque photo, « date »). */
	const OPTION = 'icademie_demo_photos';

	/** Dossier du lot déposé, dans le dossier des médias. */
	const DIR = 'icademie-donnees-demo';

	/** Taille maximale d'une photo du lot (octets). */
	const MAX_FILE = 8388608;

	/** Nombre maximal de photos d'un lot. */
	const MAX_PHOTOS = 400;

	/**
	 * Catégories des photos : principales (une par type de bateau) et vues de la galerie.
	 *
	 * @return string[] Libellé du texte alternatif, par catégorie.
	 */
	public static function categories() {
		return array(
			'voilier'     => __( 'Voilier', 'icademie-donnees-demo' ),
			'catamaran'   => __( 'Catamaran', 'icademie-donnees-demo' ),
			'moteur'      => __( 'Bateau à moteur', 'icademie-donnees-demo' ),
			'semi-rigide' => __( 'Semi-rigide', 'icademie-donnees-demo' ),
			'electrique'  => __( 'Bateau électrique', 'icademie-donnees-demo' ),
			'hybride'     => __( 'Bateau de croisière à moteur', 'icademie-donnees-demo' ),
			'rame'        => __( 'Barque à rames', 'icademie-donnees-demo' ),
			'interieur'   => __( 'Intérieur du bateau', 'icademie-donnees-demo' ),
			'pont'        => __( 'Pont du bateau', 'icademie-donnees-demo' ),
			'barre'       => __( 'Poste de barre', 'icademie-donnees-demo' ),
			'navigation'  => __( 'Mouillage dans une baie', 'icademie-donnees-demo' ),
			'lac'         => __( 'Navigation sur le lac', 'icademie-donnees-demo' ),
			'port'        => __( 'Le port', 'icademie-donnees-demo' ),
		);
	}

	/** Lot déposé par FTP dans le dossier des médias (envoi trop lourd pour le formulaire). */
	const FTP_FILE = 'photos-demo.zip';

	/**
	 * Chemin du lot déposé par FTP.
	 *
	 * @return string
	 */
	public static function ftp_file() {
		$uploads = wp_upload_dir( null, false );

		return trailingslashit( $uploads['basedir'] ) . self::FTP_FILE;
	}

	/**
	 * Dossier du lot déposé.
	 *
	 * @return string
	 */
	public static function folder() {
		$uploads = wp_upload_dir( null, false );

		return trailingslashit( $uploads['basedir'] ) . self::DIR;
	}

	/**
	 * Photos du lot déposé.
	 *
	 * @return array[] Vide sans lot.
	 */
	public static function photos() {
		$package = get_option( self::OPTION );

		return is_array( $package ) && ! empty( $package['photos'] ) ? (array) $package['photos'] : array();
	}

	/**
	 * Le lot est-il déposé, avec toutes ses photos ?
	 *
	 * @return bool
	 */
	public static function ready() {
		$photos = self::photos();

		if ( ! $photos ) {
			return false;
		}

		foreach ( $photos as $photo ) {
			if ( ! is_readable( trailingslashit( self::folder() ) . $photo['fichier'] ) ) {
				return false;
			}
		}

		return true;
	}

	/**
	 * Dépose un lot : vérifie photos.json et les images, les copie dans le dossier du lot.
	 *
	 * Seuls les fichiers décrits dans photos.json, aux noms simples et qui sont de vraies images,
	 * sont extraits.
	 *
	 * @param string $zip Chemin du fichier zip.
	 * @return int|WP_Error Nombre de photos.
	 */
	public static function install( $zip ) {
		$reader = self::reader( $zip );

		if ( is_wp_error( $reader ) ) {
			return $reader;
		}

		$manifest = json_decode( (string) $reader( 'photos.json' ), true );
		$photos   = array();

		if ( ! is_array( $manifest ) || empty( $manifest['photos'] ) || ! is_array( $manifest['photos'] ) ) {
			return new WP_Error( 'manifeste', __( 'Le zip ne contient pas de fichier « photos.json » valable.', 'icademie-donnees-demo' ) );
		}

		$categories = self::categories();

		foreach ( array_slice( $manifest['photos'], 0, self::MAX_PHOTOS ) as $entry ) {
			$file = isset( $entry['fichier'] ) ? (string) $entry['fichier'] : '';

			if ( ! preg_match( '/^[a-z0-9][a-z0-9-]*\.(jpe?g|png|webp)$/', $file ) || empty( $entry['categorie'] ) || ! isset( $categories[ $entry['categorie'] ] ) ) {
				continue;
			}

			$photos[ $file ] = array(
				'fichier'   => $file,
				'categorie' => (string) $entry['categorie'],
				'eau'       => isset( $entry['eau'] ) && in_array( $entry['eau'], array( 'mer', 'lac' ), true ) ? $entry['eau'] : '',
				'role'      => isset( $entry['role'] ) && 'galerie' === $entry['role'] ? 'galerie' : 'principale',
				'auteur'    => isset( $entry['auteur'] ) ? sanitize_text_field( (string) $entry['auteur'] ) : '',
				'source'    => isset( $entry['source'] ) ? esc_url_raw( (string) $entry['source'] ) : '',
				'licence'   => isset( $entry['licence'] ) ? sanitize_text_field( (string) $entry['licence'] ) : '',
			);
		}

		if ( ! $photos ) {
			return new WP_Error( 'manifeste', __( 'Aucune photo utilisable dans « photos.json ».', 'icademie-donnees-demo' ) );
		}

		self::remove_files();

		if ( ! wp_mkdir_p( self::folder() ) ) {
			return new WP_Error( 'dossier', __( 'Impossible de créer le dossier du lot dans les médias.', 'icademie-donnees-demo' ) );
		}

		// Dossier non listé par le serveur web.
		file_put_contents( trailingslashit( self::folder() ) . 'index.php', "<?php\n// Silence.\n" ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents

		foreach ( $photos as $file => $photo ) {
			$bytes = $reader( $file );

			if ( false === $bytes || strlen( $bytes ) > self::MAX_FILE || ! @getimagesizefromstring( $bytes ) ) { // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged -- Image invalide : écartée.
				unset( $photos[ $file ] );
				continue;
			}

			file_put_contents( trailingslashit( self::folder() ) . $file, $bytes ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
		}

		$reader( null );

		if ( ! $photos ) {
			return new WP_Error( 'images', __( 'Aucune image valable dans le zip.', 'icademie-donnees-demo' ) );
		}

		update_option(
			self::OPTION,
			array(
				'photos' => array_values( $photos ),
				'date'   => time(),
			),
			false
		);

		return count( $photos );
	}

	/**
	 * Lecteur du zip : ZipArchive (extension PHP « zip »), sinon PclZip (fourni avec WordPress).
	 *
	 * @param string $zip Chemin du fichier zip.
	 * @return callable|WP_Error Fonction ( nom ) → contenu ou false ; ( null ) ferme le zip.
	 */
	private static function reader( $zip ) {
		if ( class_exists( 'ZipArchive' ) ) {
			$archive = new ZipArchive();

			if ( true !== $archive->open( $zip ) ) {
				return new WP_Error( 'zip', __( 'Ce fichier n\'est pas un zip lisible.', 'icademie-donnees-demo' ) );
			}

			return static function ( $name ) use ( $archive ) {
				if ( null === $name ) {
					$archive->close();

					return false;
				}

				$stat = $archive->statName( $name );

				return $stat && $stat['size'] <= self::MAX_FILE ? $archive->getFromName( $name ) : false;
			};
		}

		require_once ABSPATH . 'wp-admin/includes/class-pclzip.php';

		$archive = new PclZip( $zip );
		$entries = $archive->listContent();

		if ( ! is_array( $entries ) ) {
			return new WP_Error( 'zip', __( 'Ce fichier n\'est pas un zip lisible.', 'icademie-donnees-demo' ) );
		}

		$index = array();

		foreach ( $entries as $entry ) {
			if ( empty( $entry['folder'] ) && $entry['size'] <= self::MAX_FILE ) {
				$index[ $entry['filename'] ] = (int) $entry['index'];
			}
		}

		return static function ( $name ) use ( $archive, $index ) {
			if ( null === $name || ! isset( $index[ $name ] ) ) {
				return false;
			}

			$content = $archive->extractByIndex( $index[ $name ], PCLZIP_OPT_EXTRACT_AS_STRING );

			return is_array( $content ) && isset( $content[0]['content'] ) ? $content[0]['content'] : false;
		};
	}

	/**
	 * Retire le lot déposé (les photos déjà importées dans la médiathèque restent).
	 */
	public static function remove() {
		self::remove_files();
		delete_option( self::OPTION );
	}

	/**
	 * Supprime les fichiers du dossier du lot.
	 */
	private static function remove_files() {
		$folder = self::folder();

		if ( ! is_dir( $folder ) ) {
			return;
		}

		foreach ( (array) glob( trailingslashit( $folder ) . '*' ) as $file ) {
			if ( is_file( $file ) ) {
				wp_delete_file( $file );
			}
		}

		@rmdir( $folder ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged, WordPress.WP.AlternativeFunctions.file_system_operations_rmdir -- Dossier vide.
	}

	/**
	 * Importe des photos du lot dans la médiathèque (miniatures comprises).
	 *
	 * @param array $lot   Lot en cours (« photos » : identifiant par fichier, complété).
	 * @param int   $limit Nombre de photos.
	 * @return int|WP_Error Photos restant à importer.
	 */
	public static function import( &$lot, $limit ) {
		require_once ABSPATH . 'wp-admin/includes/file.php';
		require_once ABSPATH . 'wp-admin/includes/media.php';
		require_once ABSPATH . 'wp-admin/includes/image.php';

		$categories = self::categories();
		$remaining  = 0;

		foreach ( self::photos() as $photo ) {
			if ( isset( $lot['photos'][ $photo['fichier'] ] ) ) {
				continue;
			}

			if ( $limit < 1 ) {
				++$remaining;
				continue;
			}

			// La médiathèque déplace le fichier : on lui en donne une copie.
			$tmp = wp_tempnam( $photo['fichier'] );

			if ( ! $tmp || ! copy( trailingslashit( self::folder() ) . $photo['fichier'], $tmp ) ) {
				return new WP_Error( 'copie', __( 'Impossible de copier une photo du lot.', 'icademie-donnees-demo' ) );
			}

			$label  = $categories[ $photo['categorie'] ];
			$credit = '' !== $photo['auteur'] ? sprintf( /* translators: %s: auteur de la photo. */ __( 'Photo : %s / Pixabay', 'icademie-donnees-demo' ), $photo['auteur'] ) : '';
			$id     = media_handle_sideload(
				array(
					'name'     => 'sailingloc-demo-' . $photo['fichier'],
					'tmp_name' => $tmp,
				),
				0,
				$label,
				array( 'post_excerpt' => $credit )
			);

			if ( is_wp_error( $id ) ) {
				wp_delete_file( $tmp );

				return $id;
			}

			update_post_meta( $id, '_wp_attachment_image_alt', $label );
			update_post_meta( $id, Icademie_Donnees_Demo::META, '1' );
			update_post_meta(
				$id,
				'_icademie_demo_photo',
				array(
					'categorie' => $photo['categorie'],
					'eau'       => $photo['eau'],
					'source'    => $photo['source'],
					'auteur'    => $photo['auteur'],
					'licence'   => $photo['licence'],
				)
			);
			$lot['photos'][ $photo['fichier'] ] = (int) $id;
			--$limit;
		}

		return $remaining;
	}

	/**
	 * Photos importées, par catégorie.
	 *
	 * @param array $lot Lot.
	 * @return array[] Par catégorie : liste de « fichier », « eau ».
	 */
	public static function pool( $lot ) {
		$pool = array();

		foreach ( self::photos() as $photo ) {
			if ( isset( $lot['photos'][ $photo['fichier'] ] ) ) {
				$pool[ $photo['categorie'] ][] = array(
					'fichier' => $photo['fichier'],
					'eau'     => $photo['eau'],
				);
			}
		}

		return $pool;
	}
}
