<?php
/**
 * Générateur des données de démonstration.
 *
 * Déroulé (par lots, pour tenir dans le temps d'exécution d'un hébergement mutualisé) :
 * photos (import du lot dans la médiathèque), plan (tout est tiré au sort d'avance à partir de la
 * graine), propriétaires, annonces, locataires, commandes, avis, fin.
 *
 * Tout ce qui est créé porte la métadonnée « _icademie_demo » et son identifiant est gardé dans le
 * lot (option « icademie_demo_lot ») : la suppression retire tout, et rien d'autre. Pendant la
 * génération et la suppression, aucun e-mail ne part.
 *
 * @package Icademie_Donnees_Demo
 */

defined( 'ABSPATH' ) || exit;

/**
 * Générateur.
 */
final class Icademie_Demo_Generateur {

	/** Option : lot en cours ou créé. */
	const OPTION_LOT = 'icademie_demo_lot';

	/** Option : plan de la génération en cours. */
	const OPTION_PLAN = 'icademie_demo_plan';

	/** Étapes, dans l'ordre. */
	const STEPS = array( 'photos', 'plan', 'proprietaires', 'annonces', 'locataires', 'commandes', 'avis', 'fin' );

	/** Éléments traités par appel, par étape. */
	const BATCH = array(
		'photos'        => 3,
		'proprietaires' => 30,
		'annonces'      => 8,
		'locataires'    => 50,
		'commandes'     => 10,
		'avis'          => 60,
	);

	/** Éléments supprimés par appel, par sorte. */
	const DELETE_BATCH = array(
		'avis'          => 150,
		'commandes'     => 20,
		'annonces'      => 25,
		'photos'        => 25,
		'locataires'    => 50,
		'proprietaires' => 50,
	);

	/**
	 * Réglages par défaut.
	 *
	 * @return int[]
	 */
	public static function defaults() {
		return array(
			'proprietaires' => 10,
			'annonces_min'  => 5,
			'annonces_max'  => 10,
			'commandes'     => 3,
			'locataires'    => 30,
			'avis'          => 70,
			'graine'        => 0,
		);
	}

	/**
	 * Nettoie les réglages.
	 *
	 * @param array $input Réglages envoyés.
	 * @return int[]
	 */
	public static function sanitize_settings( $input ) {
		$input    = is_array( $input ) ? $input : array();
		$settings = self::defaults();
		$limits   = array(
			'proprietaires' => array( 1, 30 ),
			'annonces_min'  => array( 1, 20 ),
			'annonces_max'  => array( 1, 20 ),
			'commandes'     => array( 0, 10 ),
			'locataires'    => array( 1, 200 ),
			'avis'          => array( 0, 100 ),
			'graine'        => array( 0, 999999999 ),
		);

		foreach ( $limits as $key => $range ) {
			if ( isset( $input[ $key ] ) && is_numeric( $input[ $key ] ) ) {
				$settings[ $key ] = max( $range[0], min( $range[1], (int) $input[ $key ] ) );
			}
		}

		$settings['annonces_max'] = max( $settings['annonces_min'], $settings['annonces_max'] );

		return $settings;
	}

	/**
	 * Lot en cours ou créé.
	 *
	 * @return array|null
	 */
	public static function lot() {
		$lot = get_option( self::OPTION_LOT );

		return is_array( $lot ) ? $lot : null;
	}

	/** Option : verrou (une seule génération ou suppression à la fois). */
	const LOCK = 'icademie_demo_verrou';

	/** E-mails coupés dans cette requête ? */
	private static $muted = false;

	/**
	 * Prend le verrou (deux onglets, double clic) ; rendu en fin de requête, même après une erreur.
	 *
	 * @return bool Faux : une autre génération ou suppression est en cours.
	 */
	private static function lock() {
		$since = (int) get_transient( self::LOCK );

		if ( $since && time() - $since < 2 * MINUTE_IN_SECONDS ) {
			return false;
		}

		set_transient( self::LOCK, time(), 2 * MINUTE_IN_SECONDS );
		register_shutdown_function( array( __CLASS__, 'unlock' ) );

		return true;
	}

	/**
	 * Rend le verrou.
	 */
	public static function unlock() {
		delete_transient( self::LOCK );
	}

	/**
	 * État quand le verrou est pris par un autre appel.
	 *
	 * @return array
	 */
	private static function busy() {
		$status            = self::status();
		$status['occupe']  = true;
		$status['message'] = __( 'Une autre génération ou suppression est en cours (autre onglet ?)…', 'icademie-donnees-demo' );

		return $status;
	}

	/**
	 * Coupe tous les e-mails jusqu'à la fin de la requête (génération, suppression) : y compris
	 * ceux que WooCommerce enverrait en fin de requête (envoi différé).
	 */
	private static function mute() {
		if ( ! self::$muted ) {
			add_filter( 'pre_wp_mail', '__return_false', 1 );
			self::$muted = true;
		}

		if ( function_exists( 'set_time_limit' ) ) {
			@set_time_limit( 300 ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged -- Peut être interdit par l'hébergeur.
		}

		wp_raise_memory_limit( 'admin' );
	}

	/**
	 * Rétablit les e-mails dans la même requête (tests, ligne de commande qui enchaîne d'autres
	 * tâches).
	 */
	public static function unmute() {
		remove_filter( 'pre_wp_mail', '__return_false', 1 );
		self::$muted = false;
	}

	/**
	 * Démarre une génération.
	 *
	 * @param array $settings Réglages.
	 * @return array|WP_Error État.
	 */
	public static function start( $settings ) {
		if ( self::lot() ) {
			return new WP_Error( 'lot', __( 'Des données de démonstration existent déjà : supprimez-les avant d\'en générer d\'autres.', 'icademie-donnees-demo' ) );
		}

		if ( ! Icademie_Demo_Photos::ready() ) {
			return new WP_Error( 'photos', __( 'Déposez d\'abord le lot de photos.', 'icademie-donnees-demo' ) );
		}

		foreach ( array( 'vendor' => __( 'le rôle « vendor » de WC Vendors', 'icademie-donnees-demo' ), 'customer' => __( 'le rôle « customer » de WooCommerce', 'icademie-donnees-demo' ) ) as $role => $label ) {
			if ( ! get_role( $role ) ) {
				/* translators: %s: élément manquant. */
				return new WP_Error( 'role', sprintf( __( 'Il manque %s.', 'icademie-donnees-demo' ), $label ) );
			}
		}

		if ( ! taxonomy_exists( 'pa_date' ) || ! taxonomy_exists( 'pa_type-de-bateau' ) ) {
			return new WP_Error( 'attributs', __( 'Il manque les attributs « Date » et « Type de bateau » (extension des dates de location).', 'icademie-donnees-demo' ) );
		}

		if ( ! self::ports() ) {
			return new WP_Error( 'ports', __( 'Aucune catégorie de port : créez d\'abord les catégories d\'annonces (Produits › Catégories).', 'icademie-donnees-demo' ) );
		}

		$settings = self::sanitize_settings( $settings );

		if ( ! $settings['graine'] ) {
			$settings['graine'] = wp_rand( 1, 999999 );
		}

		$lot = array(
			'version'       => 1,
			'etat'          => 'en_cours',
			'etape'         => 'photos',
			'curseur'       => 0,
			'reglages'      => $settings,
			'debut'         => time(),
			'fin'           => 0,
			'photos'        => array(),
			'proprietaires' => array(),
			'annonces'      => array(),
			'locataires'    => array(),
			'commandes'     => array(),
			'avis'          => array(),
			'termes'        => array(),
		);
		update_option( self::OPTION_LOT, $lot, false );

		return self::status();
	}

	/**
	 * Traite un lot de l'étape en cours.
	 *
	 * @return array|WP_Error État.
	 */
	public static function run() {
		$lot = self::lot();

		if ( ! $lot || 'en_cours' !== $lot['etat'] ) {
			return self::status();
		}

		if ( ! self::lock() ) {
			return self::busy();
		}

		self::mute();
		$result = self::run_step( $lot );
		update_option( self::OPTION_LOT, $lot, false );
		self::unlock();

		return is_wp_error( $result ) ? $result : self::status();
	}

	/**
	 * Une étape.
	 *
	 * @param array $lot Lot (modifié).
	 * @return true|WP_Error
	 */
	private static function run_step( &$lot ) {
		$step = $lot['etape'];

		// Avancement gardé après chaque élément : un appel coupé par le temps d'exécution de
		// l'hébergeur ne refait pas, à la reprise, ce qu'il avait déjà créé.
		if ( 'photos' === $step ) {
			for ( $n = 0; $n < self::BATCH['photos']; $n++ ) {
				$remaining = Icademie_Demo_Photos::import( $lot, 1 );

				if ( is_wp_error( $remaining ) ) {
					return $remaining;
				}

				update_option( self::OPTION_LOT, $lot, false );

				if ( 0 === $remaining ) {
					self::next( $lot );
					break;
				}
			}

			return true;
		}

		if ( 'plan' === $step ) {
			$plan = self::build_plan( $lot );

			if ( is_wp_error( $plan ) ) {
				return $plan;
			}

			update_option( self::OPTION_PLAN, $plan, false );
			self::next( $lot );

			return true;
		}

		if ( 'fin' === $step ) {
			self::finish( $lot );

			return true;
		}

		$plan  = get_option( self::OPTION_PLAN );
		$items = is_array( $plan ) && isset( $plan[ $step ] ) ? $plan[ $step ] : null;

		if ( null === $items ) {
			return new WP_Error( 'plan', __( 'Le plan de la génération est introuvable : supprimez les données puis recommencez.', 'icademie-donnees-demo' ) );
		}

		$end = min( count( $items ), $lot['curseur'] + self::BATCH[ $step ] );

		if ( 'annonces' === $step ) {
			wp_defer_term_counting( true );
		}

		for ( $index = $lot['curseur']; $index < $end; $index++ ) {
			$id = self::create( $step, $items[ $index ], $lot, $plan );

			if ( is_wp_error( $id ) ) {
				return $id;
			}

			$lot[ $step ][ $index ] = (int) $id;
			$lot['curseur']         = $index + 1;
			update_option( self::OPTION_LOT, $lot, false );
		}

		if ( 'annonces' === $step ) {
			wp_defer_term_counting( false );
		}

		if ( $lot['curseur'] >= count( $items ) ) {
			if ( 'avis' === $step ) {
				foreach ( array_unique( array_values( $lot['annonces'] ) ) as $product_id ) {
					wp_update_comment_count_now( $product_id );
					WC_Comments::clear_transients( $product_id );
				}
			}

			self::next( $lot );
		}

		return true;
	}

	/**
	 * Passe à l'étape suivante.
	 *
	 * @param array $lot Lot (modifié).
	 */
	private static function next( &$lot ) {
		$position       = array_search( $lot['etape'], self::STEPS, true );
		$lot['etape']   = self::STEPS[ min( count( self::STEPS ) - 1, (int) $position + 1 ) ];
		$lot['curseur'] = 0;
	}

	/**
	 * Crée un élément du plan.
	 *
	 * @param string $step Étape.
	 * @param array  $spec Élément.
	 * @param array  $lot  Lot.
	 * @param array  $plan Plan.
	 * @return int|WP_Error Identifiant.
	 */
	private static function create( $step, $spec, &$lot, $plan ) {
		switch ( $step ) {
			case 'proprietaires':
				return self::create_owner( $spec );
			case 'annonces':
				return self::create_listing( $spec, $lot );
			case 'locataires':
				return self::create_renter( $spec );
			case 'commandes':
				return self::create_order( $spec, $lot, $plan );
			case 'avis':
				return self::create_review( $spec, $lot, $plan );
		}

		return new WP_Error( 'etape', __( 'Étape inconnue.', 'icademie-donnees-demo' ) );
	}

	/**
	 * Fin de la génération : compteurs, réservations, cache.
	 *
	 * @param array $lot Lot (modifié).
	 */
	private static function finish( &$lot ) {
		if ( function_exists( 'wc_recount_all_terms' ) ) {
			wc_recount_all_terms();
		}

		self::refresh();
		delete_option( self::OPTION_PLAN );
		$lot['etat']  = 'termine';
		$lot['etape'] = 'fin';
		$lot['fin']   = time();
	}

	/**
	 * Réservations relues, caches vidés.
	 */
	private static function refresh() {
		if ( class_exists( 'Icademie_Annonce_Reservation' ) ) {
			Icademie_Annonce_Reservation::bump_version();
		}

		if ( function_exists( 'wc_delete_product_transients' ) ) {
			wc_delete_product_transients();
		}

		if ( function_exists( 'wp_cache_clear_cache' ) ) {
			wp_cache_clear_cache();
		}
	}

	/**
	 * État pour la page d'administration et la ligne de commande.
	 *
	 * @return array
	 */
	public static function status() {
		$lot  = self::lot();
		$plan = $lot && 'en_cours' === $lot['etat'] ? get_option( self::OPTION_PLAN ) : null;

		if ( ! $lot ) {
			return array(
				'etat'    => 'vide',
				'message' => __( 'Aucune donnée de démonstration sur le site.', 'icademie-donnees-demo' ),
				'fait'    => 0,
				'total'   => 0,
				'compte'  => self::counts( null ),
			);
		}

		$labels = array(
			'photos'        => __( 'Import des photos', 'icademie-donnees-demo' ),
			'plan'          => __( 'Préparation', 'icademie-donnees-demo' ),
			'proprietaires' => __( 'Comptes propriétaires', 'icademie-donnees-demo' ),
			'annonces'      => __( 'Annonces', 'icademie-donnees-demo' ),
			'locataires'    => __( 'Comptes locataires', 'icademie-donnees-demo' ),
			'commandes'     => __( 'Réservations', 'icademie-donnees-demo' ),
			'avis'          => __( 'Avis', 'icademie-donnees-demo' ),
			'fin'           => __( 'Finalisation', 'icademie-donnees-demo' ),
		);

		// Avancement : photos, puis chaque élément du plan.
		$photos = count( Icademie_Demo_Photos::photos() );
		$sizes  = array(
			'photos' => max( $photos, count( isset( $lot['photos'] ) ? (array) $lot['photos'] : array() ) ),
			'plan'   => 1,
		);

		foreach ( array( 'proprietaires', 'annonces', 'locataires', 'commandes', 'avis' ) as $key ) {
			$sizes[ $key ] = is_array( $plan ) && isset( $plan[ $key ] ) ? count( $plan[ $key ] ) : max( 1, count( isset( $lot[ $key ] ) ? (array) $lot[ $key ] : array() ) );
		}

		$sizes['fin'] = 1;
		$done         = 0;
		$total        = array_sum( $sizes );

		foreach ( self::STEPS as $step ) {
			if ( $step === $lot['etape'] ) {
				$done += 'photos' === $step ? count( (array) $lot['photos'] ) : (int) $lot['curseur'];
				break;
			}

			$done += $sizes[ $step ];
		}

		if ( 'termine' === $lot['etat'] ) {
			$done = $total;
		}

		$step_label = isset( $labels[ $lot['etape'] ] ) ? $labels[ $lot['etape'] ] : '';

		if ( 'termine' === $lot['etat'] ) {
			$message = __( 'Données de démonstration créées.', 'icademie-donnees-demo' );
		} elseif ( 'suppression' === $lot['etat'] ) {
			$message = __( 'Suppression en cours…', 'icademie-donnees-demo' );
		} else {
			$current = 'photos' === $lot['etape'] ? count( (array) $lot['photos'] ) : (int) $lot['curseur'];
			/* translators: 1: étape, 2: fait, 3: total de l'étape. */
			$message = sprintf( __( '%1$s : %2$d sur %3$d', 'icademie-donnees-demo' ), $step_label, $current, $sizes[ $lot['etape'] ] );
		}

		return array(
			'etat'    => $lot['etat'],
			'etape'   => $lot['etape'],
			'message' => $message,
			'fait'    => $done,
			'total'   => $total,
			'graine'  => isset( $lot['reglages']['graine'] ) ? (int) $lot['reglages']['graine'] : 0,
			'date'    => (int) ( $lot['fin'] ? $lot['fin'] : $lot['debut'] ),
			'compte'  => self::counts( $lot ),
		);
	}

	/**
	 * Éléments créés, par sorte.
	 *
	 * @param array|null $lot Lot.
	 * @return int[]
	 */
	private static function counts( $lot ) {
		$counts = array();

		foreach ( array( 'proprietaires', 'annonces', 'locataires', 'commandes', 'avis', 'photos' ) as $key ) {
			$counts[ $key ] = $lot && isset( $lot[ $key ] ) ? count( $lot[ $key ] ) : 0;
		}

		return $counts;
	}

	// ------------------------------------------------------------------------------------------
	// Tirages (graine du lot : le même plan pour la même graine et le même site).
	// ------------------------------------------------------------------------------------------

	/**
	 * Entier entre deux bornes.
	 *
	 * @param int $min Minimum.
	 * @param int $max Maximum.
	 * @return int
	 */
	private static function rand( $min, $max ) {
		return mt_rand( (int) $min, (int) $max ); // phpcs:ignore WordPress.WP.AlternativeFunctions.rand_mt_rand -- Tirage reproductible (graine).
	}

	/**
	 * Vrai avec une probabilité donnée.
	 *
	 * @param int $percent Pourcentage.
	 * @return bool
	 */
	private static function chance( $percent ) {
		return self::rand( 1, 100 ) <= $percent;
	}

	/**
	 * Élément au hasard.
	 *
	 * @param array $items Éléments.
	 * @return mixed
	 */
	private static function pick( $items ) {
		$items = array_values( $items );

		return $items ? $items[ self::rand( 0, count( $items ) - 1 ) ] : null;
	}

	/**
	 * Clé au hasard, selon des poids.
	 *
	 * @param int[] $weights Poids par clé.
	 * @return string|int
	 */
	private static function weighted( $weights ) {
		$weights = array_filter( $weights );
		$draw    = self::rand( 1, max( 1, (int) array_sum( $weights ) ) );

		foreach ( $weights as $key => $weight ) {
			$draw -= $weight;

			if ( $draw <= 0 ) {
				return $key;
			}
		}

		return array_key_first( $weights );
	}

	/**
	 * Mélange (reproductible).
	 *
	 * @param array $items Éléments.
	 * @return array
	 */
	private static function shuffle( $items ) {
		$items = array_values( $items );

		for ( $i = count( $items ) - 1; $i > 0; $i-- ) {
			$j           = self::rand( 0, $i );
			$swap        = $items[ $i ];
			$items[ $i ] = $items[ $j ];
			$items[ $j ] = $swap;
		}

		return $items;
	}

	/**
	 * Tirage autour d'une moyenne (loi normale).
	 *
	 * @param float $mean  Moyenne.
	 * @param float $sigma Écart type.
	 * @return float
	 */
	private static function gauss( $mean, $sigma ) {
		$u = max( 1e-9, self::rand( 1, 1000000 ) / 1000000 );
		$v = self::rand( 0, 1000000 ) / 1000000;

		return $mean + $sigma * sqrt( -2 * log( $u ) ) * cos( 2 * M_PI * $v );
	}

	// ------------------------------------------------------------------------------------------
	// Plan.
	// ------------------------------------------------------------------------------------------

	/**
	 * Ports : catégories d'annonces sans sous-catégorie (hors « Non classé »), avec les tournures.
	 *
	 * @return array[] Par identifiant : « nom », « eau » (mer, lac), « a » (« à Ajaccio »),
	 *                 « lieu » (« autour d'Ajaccio »), « lieu_court » (« Ajaccio »), « rdv ».
	 */
	public static function ports() {
		if ( ! taxonomy_exists( 'product_cat' ) ) {
			return array();
		}

		$terms = get_terms(
			array(
				'taxonomy'   => 'product_cat',
				'hide_empty' => false,
				'exclude'    => array( (int) get_option( 'default_product_cat' ) ),
			)
		);

		if ( is_wp_error( $terms ) ) {
			return array();
		}

		$parents = array_map( 'intval', wp_list_pluck( $terms, 'parent' ) );
		$lakes   = Icademie_Demo_Catalogue::lakes();
		$lands   = Icademie_Demo_Catalogue::countries();
		$ports   = array();

		foreach ( $terms as $term ) {
			if ( in_array( (int) $term->term_id, $parents, true ) ) {
				continue;
			}

			$name = $term->name;
			$key  = sanitize_title( remove_accents( $name ) ) . ' ' . $term->slug;
			$lake = '';

			foreach ( $lakes as $word => $label ) {
				if ( false !== strpos( $key, $word ) ) {
					$lake = $label;
					break;
				}
			}

			$country = sanitize_title( remove_accents( $name ) );
			$de      = self::de( $name );

			if ( '' !== $lake ) {
				$port = array(
					'a'          => 'à ' . $name,
					'lieu'       => 'sur ' . $lake,
					'lieu_court' => $lake,
					'rdv'        => 'au ponton, à ' . $name,
				);
			} elseif ( isset( $lands[ $country ] ) ) {
				$prep    = $lands[ $country ];
				$article = 'en' === $prep ? ( self::starts_with_vowel( $name ) ? 'l\'' : 'la ' ) : ( 'au' === $prep ? 'le ' : '' );
				$port    = array(
					'a'          => $prep . ' ' . $name,
					'lieu'       => $prep . ' ' . $name,
					'lieu_court' => $article . $name,
					'rdv'        => 'à la marina',
				);
			} else {
				$port = array(
					'a'          => 'à ' . $name,
					'lieu'       => 'autour ' . $de . $name,
					'lieu_court' => $name,
					'rdv'        => 'au port ' . $de . $name,
				);
			}

			$ports[ (int) $term->term_id ] = array_merge(
				$port,
				array(
					'id'  => (int) $term->term_id,
					'nom' => $name,
					'eau' => '' !== $lake ? 'lac' : 'mer',
				)
			);
		}

		return $ports;
	}

	/**
	 * Le nom commence-t-il par une voyelle (élision) ?
	 *
	 * @param string $name Nom.
	 * @return bool
	 */
	private static function starts_with_vowel( $name ) {
		return 1 === preg_match( '/^[aeiouyh]/i', remove_accents( $name ) );
	}

	/**
	 * « de » ou « d' » devant un nom.
	 *
	 * @param string $name Nom.
	 * @return string
	 */
	private static function de( $name ) {
		return self::starts_with_vowel( $name ) ? 'd\'' : 'de ';
	}

	/**
	 * Tire tout le plan : comptes, annonces, commandes, avis.
	 *
	 * @param array $lot Lot.
	 * @return array|WP_Error
	 */
	private static function build_plan( $lot ) {
		$settings = $lot['reglages'];
		mt_srand( (int) $settings['graine'] ); // phpcs:ignore WordPress.WP.AlternativeFunctions.rand_seeding_mt_srand -- Plan reproductible.

		$ports = self::ports();
		$pool  = Icademie_Demo_Photos::pool( $lot );

		if ( ! $ports ) {
			return new WP_Error( 'ports', __( 'Aucune catégorie de port.', 'icademie-donnees-demo' ) );
		}

		$now     = time();
		$tz      = wp_timezone();
		$today   = ( new DateTimeImmutable( 'now', $tz ) )->setTime( 0, 0 );
		$people  = self::people_drawer();
		$uses    = array();
		$owners  = array();
		$ids     = self::shuffle( array_keys( $ports ) );
		$models  = Icademie_Demo_Catalogue::models();
		$renters = array();

		// Propriétaires : répartis entre les ports.
		for ( $i = 0; $i < $settings['proprietaires']; $i++ ) {
			$person   = $people();
			$home     = $ids[ $i % count( $ids ) ];
			$since    = self::rand( 2012, 2024 );
			$owners[] = array_merge(
				$person,
				array(
					'port'        => $home,
					'boutique'    => sprintf( self::pick( Icademie_Demo_Catalogue::shop_patterns() ), $person['prenom'], $person['nom'] ),
					'description' => sprintf( self::pick( Icademie_Demo_Catalogue::owner_bios() ), $person['prenom'], $ports[ $home ]['a'], $since, 'f' === $person['genre'] ? 'e' : '', 'f' === $person['genre'] ? 'Navigatrice' : 'Navigateur' ),
					'inscrit'     => $now - self::rand( 30, 400 ) * DAY_IN_SECONDS,
				)
			);
		}

		// Annonces.
		$listings = array();

		foreach ( $owners as $owner_index => $owner ) {
			$count      = self::rand( $settings['annonces_min'], $settings['annonces_max'] );
			$own_models = array();

			for ( $k = 0; $k < $count; $k++ ) {
				$port  = self::chance( 75 ) ? $owner['port'] : self::pick( array_keys( $ports ) );
				$water = $ports[ $port ]['eau'];
				$type  = self::weighted( Icademie_Demo_Catalogue::type_weights( $water ) );
				$fit   = array_values(
					array_filter(
						$models,
						static function ( $model ) use ( $type, $water ) {
							return $model['type'] === $type && ( 'lac' !== $water || $model['lac'] );
						}
					)
				);

				if ( ! $fit ) {
					continue;
				}

				// Pas deux fois le même modèle chez un propriétaire, si possible.
				$fresh = array_values(
					array_filter(
						$fit,
						static function ( $model ) use ( $own_models ) {
							return ! in_array( $model['modele'], $own_models, true );
						}
					)
				);
				$model        = self::pick( $fresh ? $fresh : $fit );
				$own_models[] = $model['modele'];
				$listings[]   = self::plan_listing( $model, $ports[ $port ], $owner_index, $owner, $pool, $uses );
			}
		}

		// Locataires.
		$cities = Icademie_Demo_Catalogue::cities();

		for ( $i = 0; $i < $settings['locataires']; $i++ ) {
			$renters[] = array_merge( $people(), array( 'ville' => self::pick( $cities ), 'inscrit' => $now ) );
		}

		// Commandes : sans chevauchement sur un même bateau, ni (si possible) pour un même locataire.
		$orders    = array();
		$busy      = array();
		$weights   = array();
		$renter_ct = count( $renters );

		foreach ( array_keys( $renters ) as $r ) {
			// Quelques fidèles : les premiers locataires réservent plus souvent.
			$weights[ $r ] = 1 + (int) round( 3 * ( $renter_ct - $r ) / $renter_ct );
		}

		foreach ( $listings as $listing_index => $listing ) {
			$periods = array();

			for ( $n = 0; $n < $settings['commandes']; $n++ ) {
				$slot = null;

				for ( $try = 0; $try < 60 && ! $slot; $try++ ) {
					$future = self::chance( 30 );
					$start  = $future ? self::rand( 2, 80 ) : -self::rand( 4, 250 );
					$days   = (int) self::weighted( self::durations( $listing ) );
					$end    = $start + $days - 1;

					foreach ( $periods as $period ) {
						if ( $start <= $period[1] + 1 && $end >= $period[0] - 1 ) {
							continue 2;
						}
					}

					$slot = array( $start, $end, $days );
				}

				if ( ! $slot ) {
					continue;
				}

				$periods[] = $slot;
				$renter    = null;

				for ( $try = 0; $try < 12 && null === $renter; $try++ ) {
					$candidate = (int) self::weighted( $weights );
					$free      = true;

					foreach ( isset( $busy[ $candidate ] ) ? $busy[ $candidate ] : array() as $period ) {
						if ( $slot[0] <= $period[1] && $slot[1] >= $period[0] ) {
							$free = false;
							break;
						}
					}

					if ( $free || 11 === $try ) {
						$renter = $candidate;
					}
				}

				$busy[ $renter ][] = $slot;
				$start_date        = $today->modify( $slot[0] . ' days' );
				$end_date          = $today->modify( $slot[1] . ' days' );

				if ( $slot[1] < 0 ) {
					$status = self::chance( 90 ) ? 'completed' : 'cancelled';
				} elseif ( $slot[0] <= 0 ) {
					$status = 'processing';
				} else {
					$status = (string) self::weighted(
						array(
							'processing' => 85,
							'on-hold'    => 5,
							'cancelled'  => 10,
						)
					);
				}

				$created = min( $now - 3600, $start_date->getTimestamp() - self::rand( 4, 45 ) * DAY_IN_SECONDS + self::rand( 8 * 3600, 22 * 3600 ) );
				$orders[] = array(
					'annonce'   => $listing_index,
					'locataire' => $renter,
					'debut'     => $start_date->format( 'Y-m-d' ),
					'fin'       => $end_date->format( 'Y-m-d' ),
					'jours'     => $slot[2],
					'passagers' => self::rand( max( 1, (int) round( $listing['passagers'] / 3 ) ), $listing['passagers'] ),
					'statut'    => $status,
					'cree'      => $created,
					'paye'      => in_array( $status, array( 'processing', 'completed' ), true ) ? $created + self::rand( 60, 900 ) : 0,
					'termine'   => 'completed' === $status ? min( $now - 1800, $end_date->getTimestamp() + DAY_IN_SECONDS + 10 * 3600 ) : 0,
				);
			}
		}

		usort(
			$orders,
			static function ( $a, $b ) {
				return $a['cree'] <=> $b['cree'];
			}
		);

		// Dates d'inscription et de publication : avant la première commande.
		$first_order = array();

		foreach ( $orders as $order ) {
			$first_order['annonce'][ $order['annonce'] ]     = min( isset( $first_order['annonce'][ $order['annonce'] ] ) ? $first_order['annonce'][ $order['annonce'] ] : $now, $order['cree'] );
			$first_order['locataire'][ $order['locataire'] ] = min( isset( $first_order['locataire'][ $order['locataire'] ] ) ? $first_order['locataire'][ $order['locataire'] ] : $now, $order['cree'] );
		}

		foreach ( $listings as $index => $listing ) {
			$first                     = isset( $first_order['annonce'][ $index ] ) ? $first_order['annonce'][ $index ] : $now - self::rand( 5, 60 ) * DAY_IN_SECONDS;
			$listings[ $index ]['cree'] = $first - self::rand( 15, 90 ) * DAY_IN_SECONDS - self::rand( 0, 20 * 3600 );
			$owner                     = $listings[ $index ]['proprietaire'];
			$owners[ $owner ]['inscrit'] = min( $owners[ $owner ]['inscrit'], $listings[ $index ]['cree'] - self::rand( 5, 60 ) * DAY_IN_SECONDS );
		}

		foreach ( $renters as $index => $renter ) {
			$first                        = isset( $first_order['locataire'][ $index ] ) ? $first_order['locataire'][ $index ] : $now - self::rand( 5, 200 ) * DAY_IN_SECONDS;
			$renters[ $index ]['inscrit'] = $first - self::rand( 0, 120 ) * DAY_IN_SECONDS - self::rand( 600, 7200 );
		}

		// Avis : locataires des locations terminées, après la fin de la location.
		$reviews = array();
		$texts   = array();

		foreach ( $orders as $order_index => $order ) {
			if ( 'completed' !== $order['statut'] || ! self::chance( $settings['avis'] ) ) {
				continue;
			}

			$end_ts = ( new DateTimeImmutable( $order['fin'], $tz ) )->getTimestamp();
			$date   = $end_ts + self::rand( 1, 10 ) * DAY_IN_SECONDS + self::rand( 8 * 3600, 22 * 3600 );

			if ( $date > $now - 3600 ) {
				continue;
			}

			$listing   = $listings[ $order['annonce'] ];
			$reviews[] = array_merge(
				array(
					'commande'  => $order_index,
					'annonce'   => $order['annonce'],
					'locataire' => $order['locataire'],
					'date'      => $date,
				),
				self::plan_review( $listing, $owners[ $listing['proprietaire'] ], $ports[ $listing['port'] ], $order['jours'], $texts )
			);
		}

		return array(
			'proprietaires' => $owners,
			'annonces'      => $listings,
			'locataires'    => $renters,
			'commandes'     => $orders,
			'avis'          => $reviews,
		);
	}

	/**
	 * Tireur de personnes fictives (prénom, nom, genre ; sans doublon).
	 *
	 * Prénoms tous différents tant qu'il en reste : un avis ne remercie pas « Camille » quand
	 * l'auteur s'appelle aussi Camille.
	 *
	 * @return callable
	 */
	private static function people_drawer() {
		list( $first, $last ) = Icademie_Demo_Catalogue::people();
		$used                 = array();
		$names                = array();

		return static function () use ( $first, $last, &$used, &$names ) {
			$free = array_values(
				array_filter(
					$first,
					static function ( $person ) use ( $names ) {
						return ! isset( $names[ $person[0] ] );
					}
				)
			);

			do {
				$pick = self::pick( $free ? $free : $first );
				$name = self::pick( $last );
				$key  = $pick[0] . ' ' . $name;
			} while ( isset( $used[ $key ] ) && count( $used ) < count( $first ) * count( $last ) );

			$used[ $key ]      = true;
			$names[ $pick[0] ] = true;

			return array(
				'prenom' => $pick[0],
				'genre'  => $pick[1],
				'nom'    => $name,
			);
		};
	}

	/**
	 * Durées de location possibles (jours, avec leur poids), selon le bateau.
	 *
	 * @param array $listing Annonce.
	 * @return int[]
	 */
	private static function durations( $listing ) {
		switch ( $listing['type'] ) {
			case 'rame':
			case 'electrique':
				return array(
					1 => 70,
					2 => 30,
				);
			case 'semi-rigide':
				return array(
					1 => 50,
					2 => 30,
					3 => 20,
				);
			case 'catamaran':
				return array(
					3 => 20,
					4 => 20,
					5 => 20,
					7 => 40,
				);
			case 'moteur':
				return $listing['cabines'] > 0 ? array(
					1 => 20,
					2 => 30,
					3 => 25,
					4 => 15,
					5 => 10,
				) : array(
					1 => 45,
					2 => 30,
					3 => 25,
				);
			case 'voilier':
				return $listing['cabines'] > 0 ? array(
					2 => 20,
					3 => 25,
					4 => 20,
					5 => 15,
					7 => 20,
				) : array(
					1 => 50,
					2 => 35,
					3 => 15,
				);
		}

		return array(
			2 => 25,
			3 => 25,
			4 => 20,
			5 => 15,
			7 => 15,
		);
	}

	/**
	 * Une annonce du plan.
	 *
	 * @param array $model       Modèle.
	 * @param array $port        Port.
	 * @param int   $owner_index Propriétaire.
	 * @param array $owner       Propriétaire.
	 * @param array $pool        Photos par catégorie.
	 * @param int[] $uses        Utilisations de chaque photo (modifié).
	 * @return array
	 */
	private static function plan_listing( $model, $port, $owner_index, $owner, $pool, &$uses ) {
		$type    = $model['type'];
		$types   = Icademie_Demo_Catalogue::types();
		$length  = (float) $model['longueur'];
		$water   = $port['eau'];
		$price   = (int) ( round( $model['prix'] * self::rand( 85, 115 ) / 100 / 5 ) * 5 );
		$skipper = self::chance(
			array(
				'voilier'     => 20,
				'catamaran'   => 40,
				'moteur'      => 15,
				'hybride'     => 30,
				'semi-rigide' => 5,
			)[ $type ] ?? 0
		);
		$power   = self::power_kw( $model['puissance'] );
		$free    = $skipper || 'rame' === $type || ( null !== $power && $power <= 4.5 );

		// Équipements : ceux de série, puis quelques autres.
		list( $core, $more ) = Icademie_Demo_Catalogue::equipment( $type, $length );
		$extra               = self::shuffle( $more );
		$equipment           = array_merge( $core, array_slice( $extra, 0, $more ? self::rand( 2, min( count( $more ), $length >= 10 ? 8 : 4 ) ) : 0 ) );

		// Conditions de location.
		$caution_choices = $price <= 60 ? array( 0, 300 ) : ( $price <= 150 ? array( 300, 500 ) : ( $price <= 300 ? array( 800, 1000, 1500 ) : ( $price <= 500 ? array( 1500, 2000, 3000 ) : array( 3000, 5000 ) ) ) );
		$cabins          = (int) $model['cabines'];

		if ( $skipper ) {
			$permit = 'skipper';
		} elseif ( $free ) {
			$permit = 'aucun';
		} elseif ( 'lac' === $water ) {
			$permit = 'fluvial';
		} elseif ( 'voilier' === $type ) {
			$permit = self::chance( 50 ) ? 'experience' : 'cotier';
		} elseif ( 'catamaran' === $type ) {
			$permit = self::chance( 25 ) ? 'hauturier' : 'cotier';
		} else {
			$permit = 'cotier';
		}

		if ( in_array( $type, array( 'rame', 'electrique' ), true ) ) {
			$fuel = 'sans_objet';
		} elseif ( $skipper && self::chance( 50 ) ) {
			$fuel = 'inclus';
		} elseif ( in_array( $type, array( 'voilier', 'catamaran' ), true ) ) {
			$fuel = self::chance( 60 ) ? 'plein' : 'consommation';
		} elseif ( 'hybride' === $type ) {
			$fuel = 'plein';
		} else {
			$fuel = (string) self::weighted(
				array(
					'consommation' => 50,
					'locataire'    => 25,
					'plein'        => 25,
				)
			);
		}

		$conditions = array(
			'caution'      => (string) self::pick( $caution_choices ),
			'annulation'   => (string) self::weighted(
				array(
					'souple'           => 30,
					'moderee'          => 40,
					'stricte'          => 20,
					'non_remboursable' => 10,
				)
			),
			'embarquement' => (string) self::weighted(
				$cabins > 0 ? array(
					'9h'       => 30,
					'10h'      => 25,
					'14h'      => 15,
					'17h'      => 15,
					'convenir' => 15,
				) : array(
					'8h'       => 25,
					'9h'       => 40,
					'10h'      => 25,
					'convenir' => 10,
				)
			),
			'debarquement' => (string) self::weighted(
				$cabins > 0 ? array(
					'12h'      => 15,
					'17h'      => 25,
					'18h'      => 30,
					'19h'      => 15,
					'convenir' => 15,
				) : array(
					'17h'      => 25,
					'18h'      => 35,
					'19h'      => 20,
					'20h'      => 10,
					'convenir' => 10,
				)
			),
			'permis'       => $permit,
			'carburant'    => $fuel,
		);

		// Attributs (valeurs par slug ; année et puissance par nom, créées si besoin).
		$attributes = array(
			'pa_type-de-bateau'        => array( $types[ $type ]['attribut'] ),
			'pa_passagers'             => array_map( 'strval', range( 1, max( 1, min( 15, (int) $model['passagers'] ) ) ) ),
			'pa_cabine'                => $cabins > 0 ? array( (string) min( 4, $cabins ) ) : array(),
			'pa_nombre-de-couchages'   => $model['couchages'] > 0 ? array( (string) min( 10, (int) $model['couchages'] ) ) : array(),
			'pa_longueur-du-bateau'    => array( $length > 15 ? '15-2' : (string) max( 1, (int) round( $length ) ) ),
			'pa_skipper'               => array( $skipper ? 'avec-skipper' : 'sans-skipper' ),
			'pa_permis-bateau'         => $free ? array( 'sans-permis-bateau-requis' ) : array(),
			'pa_equipements'           => $equipment,
			'pa_annee-de-construction' => array( (string) self::rand( in_array( $type, array( 'catamaran', 'hybride' ), true ) ? 2015 : 2008, 2024 ) ),
			'pa_puissance-du-moteur'   => '' !== $model['puissance'] ? array( $model['puissance'] ) : array(),
		);

		// Photos : une principale du type (de lac pour un lac), puis la galerie.
		$main    = self::pick_photo( $pool, $type, $water, $uses, array() );
		$gallery = array();
		$wanted  = array();

		if ( $cabins > 0 && in_array( $type, array( 'voilier', 'catamaran', 'moteur', 'hybride' ), true ) && self::chance( 45 ) ) {
			$wanted[] = 'interieur';
		}

		if ( in_array( $type, array( 'voilier', 'catamaran' ), true ) ) {
			$wanted[] = 'pont';
			$wanted[] = 'pont';
		} elseif ( 'rame' !== $type ) {
			$wanted[] = 'barre';
		}

		$wanted[] = 'lac' === $water ? 'lac' : 'navigation';
		$wanted[] = 'lac' === $water ? 'lac' : ( self::chance( 50 ) ? 'port' : 'navigation' );

		if ( 'rame' === $type || 'electrique' === $type ) {
			$wanted[] = $type;
		}

		foreach ( $wanted as $category ) {
			if ( count( $gallery ) >= 4 ) {
				break;
			}

			$photo = self::pick_photo( $pool, $category, $water, $uses, array_merge( array( $main ), $gallery ) );

			if ( null !== $photo ) {
				$gallery[] = $photo;
			}
		}

		// Textes.
		$vars = array(
			'{modele}'      => $model['modele'],
			'{longueur}'    => self::length_text( $length ),
			'{lieu}'        => $port['lieu'],
			'{lieu_court}'  => $port['lieu_court'],
			'{passagers}'   => (string) (int) $model['passagers'],
			'{puissance}'   => '' !== $model['puissance'] ? $model['puissance'] : '',
			'{cabines}'     => self::cabins_text( $cabins ),
			'{couchages}'   => (string) (int) $model['couchages'],
			'{rdv}'         => $port['rdv'],
		);
		$texts = Icademie_Demo_Catalogue::descriptions()[ $type ];
		$end   = Icademie_Demo_Catalogue::description_endings();
		$paras = array( self::pick( $texts['intro'] ) );
		$body  = $cabins > 0 && $texts['interieur'] ? $texts['interieur'] : $texts['ouvert'];

		if ( $body ) {
			$paras[] = self::pick( $body );
		}

		$names = self::equipment_names( $equipment );

		if ( $names ) {
			$paras[] = 'À bord : ' . self::join_list( $names ) . '.';
		}

		$paras[] = self::pick( $end['rdv'] ) . ' ' . self::pick( $skipper ? $end['skipper'] : $end['sans_skipper'] );
		$paras[] = implode( ' ', array_slice( self::shuffle( $end['divers'] ), 0, self::rand( 1, 2 ) ) );
		$html    = '';

		foreach ( $paras as $paragraph ) {
			// Élision devant un modèle qui commence par une voyelle (« L'Elan », « Cet Excess »).
			if ( self::starts_with_vowel( $model['modele'] ) && ! preg_match( '/^h/i', $model['modele'] ) ) {
				$paragraph = str_replace( array( 'Le {modele}', 'le {modele}', 'Ce {modele}' ), array( 'L\'{modele}', 'l\'{modele}', 'Cet {modele}' ), $paragraph );
			}

			// Texte simple (apostrophes gardées telles quelles, lisibles dans le formulaire du vendeur).
			$html .= '<p>' . htmlspecialchars( strtr( $paragraph, $vars ), ENT_NOQUOTES, 'UTF-8' ) . "</p>\n";
		}

		// Qualité du bateau : la plupart très bien notés, quelques-uns décevants.
		$tier    = self::weighted(
			array(
				'bon'    => 80,
				'moyen'  => 15,
				'mauvais' => 5,
			)
		);
		$quality = 'bon' === $tier ? self::rand( 410, 480 ) / 100 : ( 'moyen' === $tier ? self::rand( 330, 400 ) / 100 : self::rand( 240, 320 ) / 100 );

		return array(
			'proprietaire' => $owner_index,
			'type'         => $type,
			'titre'        => $types[ $type ]['titre'] . ' ' . $model['modele'],
			'port'         => $port['id'],
			'prix'         => $price,
			'passagers'    => (int) $model['passagers'],
			'cabines'      => $cabins,
			'attributs'    => $attributes,
			'conditions'   => $conditions,
			'photo'        => $main,
			'galerie'      => $gallery,
			'description'  => $html,
			/* translators: 1: type de bateau, 2: longueur, 3: passagers, 4: lieu. */
			'resume'       => sprintf( '%1$s de %2$s pour %3$d personnes, %4$s.', ucfirst( $types[ $type ]['nom'] ), self::length_text( $length ), (int) $model['passagers'], $port['a'] ),
			'qualite'      => $quality,
			'cree'         => 0,
		);
	}

	/**
	 * Photo d'une catégorie : de la bonne eau si possible, la moins utilisée.
	 *
	 * @param array    $pool     Photos par catégorie.
	 * @param string   $category Catégorie.
	 * @param string   $water    Eau du port.
	 * @param int[]    $uses     Utilisations (modifié).
	 * @param string[] $exclude  Photos déjà prises pour l'annonce.
	 * @return string|null Fichier.
	 */
	private static function pick_photo( $pool, $category, $water, &$uses, $exclude ) {
		$photos = isset( $pool[ $category ] ) ? $pool[ $category ] : array();
		$match  = array_values(
			array_filter(
				$photos,
				static function ( $photo ) use ( $water, $exclude ) {
					return ( '' === $photo['eau'] || $water === $photo['eau'] ) && ! in_array( $photo['fichier'], $exclude, true );
				}
			)
		);

		if ( ! $match ) {
			$match = array_values(
				array_filter(
					$photos,
					static function ( $photo ) use ( $exclude ) {
						return ! in_array( $photo['fichier'], $exclude, true );
					}
				)
			);
		}

		if ( ! $match ) {
			return null;
		}

		$least = min(
			array_map(
				static function ( $photo ) use ( $uses ) {
					return isset( $uses[ $photo['fichier'] ] ) ? $uses[ $photo['fichier'] ] : 0;
				},
				$match
			)
		);
		$fresh = array_values(
			array_filter(
				$match,
				static function ( $photo ) use ( $uses, $least ) {
					return ( isset( $uses[ $photo['fichier'] ] ) ? $uses[ $photo['fichier'] ] : 0 ) === $least;
				}
			)
		);
		$file  = self::pick( $fresh )['fichier'];

		$uses[ $file ] = ( isset( $uses[ $file ] ) ? $uses[ $file ] : 0 ) + 1;

		return $file;
	}

	/**
	 * Un avis du plan : note, notes détaillées et texte cohérents.
	 *
	 * @param array    $listing Annonce.
	 * @param array    $owner   Propriétaire.
	 * @param array    $port    Port.
	 * @param int      $days    Durée de la location.
	 * @param string[] $texts   Textes déjà utilisés (modifié).
	 * @return array « note », « criteres », « texte ».
	 */
	private static function plan_review( $listing, $owner, $port, $days, &$texts ) {
		$note     = (int) max( 1, min( 5, round( self::gauss( $listing['qualite'], 0.7 ) ) ) );
		$keys     = array( 'conformite', 'proprete', 'communication', 'qualite_prix' );
		$criteria = array();

		foreach ( $keys as $key ) {
			$criteria[ $key ] = max( 1, min( 5, $note + (int) self::pick( array( -1, 0, 0, 0, 1 ) ) ) );
		}

		// Avis mitigé ou négatif : un ou deux critères en cause.
		if ( $note <= 3 ) {
			foreach ( array_slice( self::shuffle( $keys ), 0, self::rand( 1, 2 ) ) as $key ) {
				$criteria[ $key ] = max( 1, $note - (int) self::pick( array( 0, 1 ) ) );
			}
		}

		$pieces = Icademie_Demo_Catalogue::reviews();
		$vars   = array(
			'{proprietaire}' => $owner['prenom'],
			'{lieu}'         => $port['lieu'],
			'{lieu_court}'   => $port['lieu_court'],
			'{duree}'        => Icademie_Demo_Catalogue::days_text( $days ),
		);
		$sorted = $criteria;
		arsort( $sorted );
		$best  = array_keys( $sorted );
		$worst = array_reverse( $best );
		$text  = '';

		for ( $try = 0; $try < 6; $try++ ) {
			$sentences = array( self::pick( $pieces['debut'][ $note ] ) );

			if ( $note >= 4 ) {
				foreach ( array_slice( $best, 0, self::rand( 1, 2 ) ) as $key ) {
					$sentences[] = self::pick( $pieces['criteres'][ $key ]['+'] );
				}

				if ( 4 === $note && $criteria[ $worst[0] ] <= 3 && self::chance( 50 ) ) {
					$sentences[] = self::pick( $pieces['criteres'][ $worst[0] ]['-'] );
				}
			} elseif ( 3 === $note ) {
				$sentences[] = self::pick( $pieces['criteres'][ $best[0] ]['+'] );
				$sentences[] = self::pick( $pieces['criteres'][ $worst[0] ]['-'] );
			} else {
				foreach ( array_slice( $worst, 0, self::rand( 1, 2 ) ) as $key ) {
					$sentences[] = self::pick( $pieces['criteres'][ $key ]['-'] );
				}
			}

			if ( $note >= 3 && self::chance( 40 ) ) {
				$sentences[] = self::pick( $pieces['type'][ $listing['type'] ] );
			}

			if ( self::chance( 60 ) ) {
				$sentences[] = self::pick( $pieces['fin'][ $note >= 4 ? '+' : ( 3 === $note ? '=' : '-' ) ] );
			}

			$text = strtr( implode( ' ', array_unique( $sentences ) ), $vars );

			if ( ! isset( $texts[ $text ] ) ) {
				break;
			}
		}

		$texts[ $text ] = true;

		return array(
			'note'     => $note,
			'criteres' => $criteria,
			'texte'    => $text,
		);
	}

	/**
	 * Puissance en kW (« 2 × 57 CV », « 10 kW », « 220 CV et 7 kW » : moteur principal).
	 *
	 * @param string $power Puissance.
	 * @return float|null
	 */
	private static function power_kw( $power ) {
		if ( ! preg_match( '/^(?:(\d+)\s*×\s*)?(\d+(?:[.,]\d+)?)\s*(CV|kW)/u', (string) $power, $match ) ) {
			return null;
		}

		$value = (float) str_replace( ',', '.', $match[2] ) * ( '' !== $match[1] ? (int) $match[1] : 1 );

		return 'kW' === $match[3] ? $value : $value * 0.7355;
	}

	/**
	 * Longueur lisible (« 10,9 m »).
	 *
	 * @param float $length Longueur.
	 * @return string
	 */
	private static function length_text( $length ) {
		$text = number_format( $length, 1, ',', '' );

		return ( ',0' === substr( $text, -2 ) ? substr( $text, 0, -2 ) : $text ) . ' m';
	}

	/**
	 * « une cabine », « trois cabines ».
	 *
	 * @param int $count Cabines.
	 * @return string
	 */
	private static function cabins_text( $count ) {
		$words = array( 1 => 'une cabine', 2 => 'deux cabines', 3 => 'trois cabines', 4 => 'quatre cabines' );

		return isset( $words[ $count ] ) ? $words[ $count ] : $count . ' cabines';
	}

	/**
	 * Noms des équipements, pour une phrase (« GPS, pilote automatique… »).
	 *
	 * @param string[] $slugs Valeurs de l'attribut.
	 * @return string[]
	 */
	private static function equipment_names( $slugs ) {
		$names = array();

		foreach ( $slugs as $slug ) {
			$term = taxonomy_exists( 'pa_equipements' ) ? get_term_by( 'slug', $slug, 'pa_equipements' ) : false;

			if ( ! $term ) {
				continue;
			}

			$name    = html_entity_decode( $term->name, ENT_QUOTES, 'UTF-8' );
			$names[] = preg_match( '/^\p{Lu}{2,}/u', $name ) ? $name : mb_strtolower( mb_substr( $name, 0, 1 ) ) . mb_substr( $name, 1 );
		}

		return $names;
	}

	/**
	 * « a, b et c ».
	 *
	 * @param string[] $items Éléments.
	 * @return string
	 */
	private static function join_list( $items ) {
		if ( count( $items ) < 2 ) {
			return implode( '', $items );
		}

		$last = array_pop( $items );

		return implode( ', ', $items ) . ' et ' . $last;
	}

	// ------------------------------------------------------------------------------------------
	// Création.
	// ------------------------------------------------------------------------------------------

	/**
	 * Identifiant et adresse e-mail libres pour une personne fictive.
	 *
	 * @param array $person Personne.
	 * @return string[] login, e-mail.
	 */
	private static function account_names( $person ) {
		$base = sanitize_title( remove_accents( $person['prenom'] . ' ' . $person['nom'] ) );
		$mail = str_replace( '-', '.', $base );
		$n    = 1;

		do {
			$suffix = $n > 1 ? '-' . $n : '';
			$login  = 'demo-' . $base . $suffix;
			$email  = $mail . ( $n > 1 ? $n : '' ) . '@' . Icademie_Donnees_Demo::EMAIL_DOMAIN;
			++$n;
		} while ( username_exists( $login ) || email_exists( $email ) );

		return array( $login, $email );
	}

	/**
	 * Compte propriétaire (vendeur WC Vendors, boutique).
	 *
	 * @param array $spec Propriétaire.
	 * @return int|WP_Error
	 */
	private static function create_owner( $spec ) {
		list( $login, $email ) = self::account_names( $spec );
		$id                    = wp_insert_user(
			array(
				'user_login'      => $login,
				'user_pass'       => wp_generate_password( 32, true, true ),
				'user_email'      => $email,
				'first_name'      => $spec['prenom'],
				'last_name'       => $spec['nom'],
				'display_name'    => $spec['prenom'] . ' ' . $spec['nom'],
				'nickname'        => $spec['prenom'],
				'description'     => $spec['description'],
				'role'            => 'vendor',
				'user_registered' => gmdate( 'Y-m-d H:i:s', (int) $spec['inscrit'] ),
			)
		);

		if ( is_wp_error( $id ) ) {
			return $id;
		}

		$slug = sanitize_title( $spec['boutique'] );
		$base = $slug;

		for ( $n = 2; get_users(
			array(
				'meta_key'   => 'pv_shop_slug', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
				'meta_value' => $slug, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value
				'fields'     => 'ID',
				'number'     => 1,
			)
		); $n++ ) {
			$slug = $base . '-' . $n;
		}

		update_user_meta( $id, 'pv_shop_name', $spec['boutique'] );
		update_user_meta( $id, 'pv_shop_slug', $slug );
		update_user_meta( $id, 'pv_shop_description', $spec['description'] );
		update_user_meta( $id, 'pv_seller_info', '' );
		update_user_meta( $id, '_wcv_vendor_status', 'active' );
		update_user_meta( $id, Icademie_Donnees_Demo::META, '1' );

		return $id;
	}

	/**
	 * Compte locataire (client WooCommerce).
	 *
	 * @param array $spec Locataire.
	 * @return int|WP_Error
	 */
	private static function create_renter( $spec ) {
		list( $login, $email ) = self::account_names( $spec );
		$display               = $spec['prenom'] . ' ' . mb_substr( $spec['nom'], 0, 1 ) . '.';
		$id                    = wp_insert_user(
			array(
				'user_login'      => $login,
				'user_pass'       => wp_generate_password( 32, true, true ),
				'user_email'      => $email,
				'first_name'      => $spec['prenom'],
				'last_name'       => $spec['nom'],
				'display_name'    => $display,
				'nickname'        => $spec['prenom'],
				'role'            => 'customer',
				'user_registered' => gmdate( 'Y-m-d H:i:s', (int) $spec['inscrit'] ),
			)
		);

		if ( is_wp_error( $id ) ) {
			return $id;
		}

		foreach ( array(
			'billing_first_name' => $spec['prenom'],
			'billing_last_name'  => $spec['nom'],
			'billing_email'      => $email,
			'billing_city'       => $spec['ville'],
			'billing_country'    => 'FR',
			'paying_customer'    => 1,
		) as $key => $value ) {
			update_user_meta( $id, $key, $value );
		}

		update_user_meta( $id, Icademie_Donnees_Demo::META, '1' );

		return $id;
	}

	/**
	 * Valeur d'attribut : terme existant (par slug, ou par nom pour l'année et la puissance), créé
	 * si besoin pour l'année et la puissance (gardé dans le lot pour la suppression).
	 *
	 * @param string $taxonomy Attribut.
	 * @param string $value    Slug ou nom.
	 * @param array  $lot      Lot (modifié).
	 * @return int 0 : absent.
	 */
	private static function term_id( $taxonomy, $value, &$lot ) {
		$by_name = in_array( $taxonomy, array( 'pa_annee-de-construction', 'pa_puissance-du-moteur' ), true );
		$term    = get_term_by( 'slug', $by_name ? sanitize_title( $value ) : $value, $taxonomy );

		if ( ! $term && $by_name ) {
			$term = get_term_by( 'name', $value, $taxonomy );
		}

		if ( $term ) {
			return (int) $term->term_id;
		}

		if ( ! $by_name ) {
			return 0;
		}

		$created = wp_insert_term( $value, $taxonomy, array( 'slug' => sanitize_title( $value ) ) );

		if ( is_wp_error( $created ) ) {
			return 0;
		}

		$lot['termes'][] = array( $taxonomy, (int) $created['term_id'] );

		return (int) $created['term_id'];
	}

	/**
	 * Annonce (produit simple, virtuel, à dates), rattachée à son propriétaire.
	 *
	 * @param array $spec Annonce.
	 * @param array $lot  Lot (modifié : termes créés).
	 * @return int|WP_Error
	 */
	private static function create_listing( $spec, &$lot ) {
		$owner = isset( $lot['proprietaires'][ $spec['proprietaire'] ] ) ? (int) $lot['proprietaires'][ $spec['proprietaire'] ] : 0;

		if ( ! $owner ) {
			return new WP_Error( 'proprietaire', __( 'Propriétaire introuvable.', 'icademie-donnees-demo' ) );
		}

		$attributes = array();
		$position   = 0;
		$specs      = array_merge( array( 'pa_date' => array( 'date-debut', 'date-fin' ) ), $spec['attributs'] );

		foreach ( $specs as $taxonomy => $values ) {
			if ( ! $values || ! taxonomy_exists( $taxonomy ) ) {
				continue;
			}

			$ids = array();

			foreach ( $values as $value ) {
				$term_id = self::term_id( $taxonomy, (string) $value, $lot );

				if ( $term_id ) {
					$ids[] = $term_id;
				}
			}

			if ( ! $ids ) {
				continue;
			}

			$attribute = new WC_Product_Attribute();
			$attribute->set_id( wc_attribute_taxonomy_id_by_name( substr( $taxonomy, 3 ) ) );
			$attribute->set_name( $taxonomy );
			$attribute->set_options( $ids );
			$attribute->set_position( $position++ );
			$attribute->set_visible( 'pa_date' !== $taxonomy );
			$attribute->set_variation( false );
			$attributes[ $taxonomy ] = $attribute;
		}

		$photo   = isset( $lot['photos'][ $spec['photo'] ] ) ? (int) $lot['photos'][ $spec['photo'] ] : 0;
		$gallery = array();

		foreach ( $spec['galerie'] as $file ) {
			if ( isset( $lot['photos'][ $file ] ) ) {
				$gallery[] = (int) $lot['photos'][ $file ];
			}
		}

		$product = new WC_Product_Simple();
		$product->set_name( $spec['titre'] );
		$product->set_status( 'publish' );
		$product->set_catalog_visibility( 'visible' );
		$product->set_virtual( true );
		$product->set_regular_price( (string) $spec['prix'] );
		$product->set_description( $spec['description'] );
		$product->set_short_description( $spec['resume'] );
		$product->set_category_ids( array( (int) $spec['port'] ) );
		$product->set_image_id( $photo );
		$product->set_gallery_image_ids( $gallery );
		$product->set_reviews_allowed( true );
		$product->set_attributes( $attributes );
		$product->set_date_created( (int) $spec['cree'] );
		$product->update_meta_data( Icademie_Donnees_Demo::META, '1' );
		// Google Listings & Ads : annonces fictives jamais envoyées à Google Merchant Center.
		$product->update_meta_data( '_wc_gla_visibility', 'dont-sync-and-show' );

		if ( class_exists( 'Icademie_Annonce_Conditions' ) ) {
			$definitions = Icademie_Annonce_Conditions::definitions();

			foreach ( $spec['conditions'] as $key => $value ) {
				if ( isset( $definitions[ $key ]['choices'][ $value ] ) ) {
					$product->update_meta_data( Icademie_Annonce_Conditions::META_PREFIX . $key, $value );
				}
			}
		}

		// Auteur du produit : le propriétaire (WC Vendors rattache l'annonce à sa boutique).
		$author = static function ( $data ) use ( $owner ) {
			$data['post_author'] = $owner;

			return $data;
		};
		add_filter( 'woocommerce_new_product_data', $author );
		$id = $product->save();
		remove_filter( 'woocommerce_new_product_data', $author );

		if ( ! $id ) {
			return new WP_Error( 'annonce', __( 'Impossible de créer une annonce.', 'icademie-donnees-demo' ) );
		}

		if ( (int) get_post_field( 'post_author', $id ) !== $owner ) {
			wp_update_post(
				array(
					'ID'          => $id,
					'post_author' => $owner,
				)
			);
		}

		return $id;
	}

	/**
	 * Commande d'une location : ligne aux dates (prix × jours), passagers, frais de service,
	 * sous-commande du propriétaire (WC Vendors), puis l'état prévu.
	 *
	 * @param array $spec Commande.
	 * @param array $lot  Lot.
	 * @param array $plan Plan.
	 * @return int|WP_Error
	 */
	private static function create_order( $spec, $lot, $plan ) {
		$product_id = isset( $lot['annonces'][ $spec['annonce'] ] ) ? (int) $lot['annonces'][ $spec['annonce'] ] : 0;
		$renter_id  = isset( $lot['locataires'][ $spec['locataire'] ] ) ? (int) $lot['locataires'][ $spec['locataire'] ] : 0;
		$product    = $product_id ? wc_get_product( $product_id ) : null;
		$renter     = $plan['locataires'][ $spec['locataire'] ];
		$user       = $renter_id ? get_userdata( $renter_id ) : null;

		if ( ! $product || ! $user ) {
			return new WP_Error( 'commande', __( 'Annonce ou locataire introuvable pour une commande.', 'icademie-donnees-demo' ) );
		}

		$order = wc_create_order(
			array(
				'customer_id' => $renter_id,
				'created_via' => 'icademie-demo',
				'status'      => 'pending',
			)
		);

		if ( is_wp_error( $order ) ) {
			return $order;
		}

		$days = (int) $spec['jours'];
		$line = (float) $product->get_regular_price() * $days;
		$item = new WC_Order_Item_Product();
		$item->set_product( $product );
		$item->set_quantity( 1 );
		$item->set_subtotal( $line );
		$item->set_total( $line );

		$dates = array(
			'debut' => $spec['debut'],
			'fin'   => $spec['fin'],
		);

		if ( class_exists( 'Icademie_Dates_Produit' ) ) {
			Icademie_Dates_Produit::add_order_item_meta( $item, '', array( Icademie_Dates_Produit::CART_KEY => $dates ) );
		} else {
			$item->add_meta_data( '_icademie_date_debut', $dates['debut'], true );
			$item->add_meta_data( '_icademie_date_fin', $dates['fin'], true );
			$item->add_meta_data( '_icademie_nb_jours', $days, true );
		}

		if ( class_exists( 'Icademie_Annonce_Reservation' ) ) {
			Icademie_Annonce_Reservation::add_order_item_meta( $item, '', array( Icademie_Annonce_Reservation::CART_PASSENGERS => (int) $spec['passagers'] ) );
		}

		$order->add_item( $item );

		// Frais de service, comme au panier.
		$rate = class_exists( 'Icademie_Annonce_Reservation' ) ? Icademie_Annonce_Reservation::fee_rate() : 0.10;
		$fee  = round( $line * $rate, wc_get_price_decimals() );

		if ( $fee > 0 ) {
			$fee_item = new WC_Order_Item_Fee();
			/* translators: %s: taux des frais (« 10 »). */
			$fee_item->set_name( sprintf( __( 'Frais de service (%s %%)', 'icademie-page-annonce' ), number_format_i18n( $rate * 100 ) ) );
			$fee_item->set_amount( $fee );
			$fee_item->set_total( $fee );
			$fee_item->set_tax_status( 'none' );
			$order->add_item( $fee_item );
		}

		$order->set_billing_first_name( $renter['prenom'] );
		$order->set_billing_last_name( $renter['nom'] );
		$order->set_billing_email( $user->user_email );
		$order->set_billing_city( $renter['ville'] );
		$order->set_billing_country( 'FR' );
		$order->set_payment_method( 'icademie-demo' );
		$order->set_payment_method_title( __( 'Paiement de démonstration', 'icademie-donnees-demo' ) );
		$order->set_date_created( (int) $spec['cree'] );

		if ( $spec['paye'] ) {
			$order->set_date_paid( (int) $spec['paye'] );
		}

		if ( $spec['termine'] ) {
			$order->set_date_completed( (int) $spec['termine'] );
		}

		$order->update_meta_data( Icademie_Donnees_Demo::META, '1' );
		$order->calculate_totals( false );
		$order->save();

		// Sous-commande du propriétaire (comme à la validation du panier), puis l'état prévu :
		// commissions et sous-commandes suivent.
		if ( class_exists( 'WCV_Vendors' ) && method_exists( 'WCV_Vendors', 'create_child_orders' ) ) {
			WCV_Vendors::create_child_orders( $order );
		}

		$order->set_status( $spec['statut'], __( 'Commande de démonstration.', 'icademie-donnees-demo' ) );
		$order->save();

		return $order->get_id();
	}

	/**
	 * Avis du locataire sur le bateau loué (achat vérifié, notes détaillées).
	 *
	 * @param array $spec Avis.
	 * @param array $lot  Lot.
	 * @param array $plan Plan.
	 * @return int|WP_Error
	 */
	private static function create_review( $spec, $lot, $plan ) {
		$product_id = isset( $lot['annonces'][ $spec['annonce'] ] ) ? (int) $lot['annonces'][ $spec['annonce'] ] : 0;
		$renter_id  = isset( $lot['locataires'][ $spec['locataire'] ] ) ? (int) $lot['locataires'][ $spec['locataire'] ] : 0;
		$user       = $renter_id ? get_userdata( $renter_id ) : null;

		if ( ! $product_id || ! $user ) {
			return new WP_Error( 'avis', __( 'Annonce ou locataire introuvable pour un avis.', 'icademie-donnees-demo' ) );
		}

		$meta = array(
			'rating'                     => (int) $spec['note'],
			'verified'                   => 1,
			Icademie_Donnees_Demo::META => '1',
		);

		if ( class_exists( 'Icademie_Avis_Criteres' ) ) {
			$known = array_keys( Icademie_Avis_Criteres::criteria() );

			foreach ( $spec['criteres'] as $key => $value ) {
				if ( in_array( $key, $known, true ) ) {
					$meta[ Icademie_Avis_Criteres::META_PREFIX . $key ] = (int) $value;
				}
			}
		}

		$id = wp_insert_comment(
			array(
				'comment_post_ID'      => $product_id,
				'comment_author'       => $user->display_name,
				'comment_author_email' => $user->user_email,
				'comment_content'      => $spec['texte'],
				'comment_type'         => 'review',
				'comment_approved'     => 1,
				'user_id'              => $renter_id,
				'comment_date'         => wp_date( 'Y-m-d H:i:s', (int) $spec['date'] ),
				'comment_date_gmt'     => gmdate( 'Y-m-d H:i:s', (int) $spec['date'] ),
				'comment_meta'         => $meta,
			)
		);

		return $id ? $id : new WP_Error( 'avis', __( 'Impossible d\'enregistrer un avis.', 'icademie-donnees-demo' ) );
	}

	// ------------------------------------------------------------------------------------------
	// Suppression.
	// ------------------------------------------------------------------------------------------

	/**
	 * Éléments marqués, même hors du lot (lot perdu, génération interrompue).
	 *
	 * @return array[] Par sorte : identifiants.
	 */
	public static function tagged() {
		$meta  = array(
			'meta_key'   => Icademie_Donnees_Demo::META, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
			'meta_value' => '1', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value
		);
		$users = get_users( array_merge( $meta, array( 'fields' => 'ID' ) ) );
		$found = array(
			'avis'          => array_map( 'intval', get_comments( array_merge( $meta, array( 'status' => 'all', 'fields' => 'ids', 'number' => 0, 'type' => 'review' ) ) ) ),
			'commandes'     => function_exists( 'wc_get_orders' ) ? array_map(
				'intval',
				wc_get_orders(
					array(
						'limit'      => -1,
						'return'     => 'ids',
						'type'       => 'shop_order',
						'status'     => array_keys( wc_get_order_statuses() ),
						'meta_query' => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
							array(
								'key'   => Icademie_Donnees_Demo::META,
								'value' => '1',
							),
						),
					)
				)
			) : array(),
			'annonces'      => array_map( 'intval', get_posts( array_merge( $meta, array( 'post_type' => 'product', 'post_status' => 'any', 'fields' => 'ids', 'numberposts' => -1 ) ) ) ),
			'photos'        => array_map( 'intval', get_posts( array_merge( $meta, array( 'post_type' => 'attachment', 'post_status' => 'any', 'fields' => 'ids', 'numberposts' => -1 ) ) ) ),
			'proprietaires' => array(),
			'locataires'    => array(),
		);

		foreach ( $users as $user_id ) {
			$user = get_userdata( (int) $user_id );

			if ( $user ) {
				$found[ in_array( 'vendor', (array) $user->roles, true ) ? 'proprietaires' : 'locataires' ][] = (int) $user_id;
			}
		}

		return $found;
	}

	/**
	 * Supprime un lot d'éléments ; à rappeler jusqu'à l'état « vide ».
	 *
	 * @return array État.
	 */
	public static function delete() {
		if ( ! self::lock() ) {
			return self::busy();
		}

		$lot = self::lot();

		if ( ! $lot || empty( $lot['suppression'] ) ) {
			$tagged = self::tagged();
			$lot    = $lot ? $lot : array(
				'etat'          => 'suppression',
				'etape'         => '',
				'curseur'       => 0,
				'reglages'      => array(),
				'debut'         => time(),
				'fin'           => 0,
				'photos'        => array(),
				'proprietaires' => array(),
				'annonces'      => array(),
				'locataires'    => array(),
				'commandes'     => array(),
				'avis'          => array(),
				'termes'        => array(),
			);

			foreach ( array_keys( self::DELETE_BATCH ) as $kind ) {
				$known                       = isset( $lot[ $kind ] ) ? array_values( (array) $lot[ $kind ] ) : array();
				$lot['suppression'][ $kind ] = array_values( array_unique( array_map( 'intval', array_merge( $known, $tagged[ $kind ] ) ) ) );
			}

			$lot['etat'] = 'suppression';
		}

		self::mute();

		foreach ( self::DELETE_BATCH as $kind => $size ) {
			if ( empty( $lot['suppression'][ $kind ] ) ) {
				continue;
			}

			$batch                       = array_splice( $lot['suppression'][ $kind ], 0, $size );
			$lot[ $kind ]                = array_values( array_diff( isset( $lot[ $kind ] ) ? (array) $lot[ $kind ] : array(), $batch ) );
			self::delete_items( $kind, $batch );
			update_option( self::OPTION_LOT, $lot, false );
			self::unlock();

			return self::delete_status( $lot );
		}

		// Tout est supprimé : valeurs d'attributs créées, compteurs, caches.
		foreach ( isset( $lot['termes'] ) ? $lot['termes'] : array() as $term ) {
			$current = get_term( (int) $term[1], $term[0] );

			if ( $current && ! is_wp_error( $current ) && 0 === (int) $current->count ) {
				wp_delete_term( (int) $term[1], $term[0] );
			}
		}

		if ( function_exists( 'wc_recount_all_terms' ) ) {
			wc_recount_all_terms();
		}

		self::refresh();
		delete_option( self::OPTION_LOT );
		delete_option( self::OPTION_PLAN );
		self::unlock();

		return self::status();
	}

	/**
	 * Avancement de la suppression.
	 *
	 * @param array $lot Lot.
	 * @return array
	 */
	private static function delete_status( $lot ) {
		$left = 0;

		foreach ( $lot['suppression'] as $ids ) {
			$left += count( $ids );
		}

		$status            = self::status();
		$status['etat']    = 'suppression';
		$status['reste']   = $left;
		/* translators: %d: éléments restants. */
		$status['message'] = sprintf( _n( 'Suppression : %d élément restant', 'Suppression : %d éléments restants', $left, 'icademie-donnees-demo' ), $left );

		return $status;
	}

	/**
	 * Supprime des éléments d'une sorte.
	 *
	 * @param string $kind Sorte.
	 * @param int[]  $ids  Identifiants.
	 */
	private static function delete_items( $kind, $ids ) {
		global $wpdb;

		switch ( $kind ) {
			case 'avis':
				$products = array();

				foreach ( $ids as $id ) {
					$comment = get_comment( $id );

					if ( $comment ) {
						$products[] = (int) $comment->comment_post_ID;
						wp_delete_comment( $id, true );
					}
				}

				foreach ( array_unique( $products ) as $product_id ) {
					if ( get_post( $product_id ) ) {
						wp_update_comment_count_now( $product_id );
						WC_Comments::clear_transients( $product_id );
					}
				}

				break;
			case 'commandes':
				$table      = $wpdb->prefix . 'pv_commission';
				$has_table  = $table === $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
				$sub_orders = in_array( 'shop_order_vendor', wc_get_order_types(), true );

				foreach ( $ids as $id ) {
					// Sous-commandes du propriétaire (WC Vendors), puis la commande et ses commissions.
					if ( $sub_orders ) {
						foreach ( wc_get_orders(
							array(
								'parent' => $id,
								'type'   => 'shop_order_vendor',
								'limit'  => -1,
								'status' => array_keys( wc_get_order_statuses() ),
							)
						) as $child ) {
							$child->delete( true );
						}
					}

					$order = wc_get_order( $id );

					if ( $order ) {
						$order->delete( true );
					}

					if ( $has_table ) {
						$wpdb->delete( $table, array( 'order_id' => $id ), array( '%d' ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
					}
				}

				break;
			case 'annonces':
				foreach ( $ids as $id ) {
					$product = wc_get_product( $id );

					if ( $product ) {
						$product->delete( true );
					} else {
						wp_delete_post( $id, true );
					}
				}

				break;
			case 'photos':
				foreach ( $ids as $id ) {
					wp_delete_attachment( $id, true );
				}

				break;
			case 'proprietaires':
			case 'locataires':
				require_once ABSPATH . 'wp-admin/includes/user.php';

				foreach ( $ids as $id ) {
					// Seulement les comptes marqués : jamais un vrai compte.
					if ( '1' === get_user_meta( $id, Icademie_Donnees_Demo::META, true ) ) {
						wp_delete_user( $id );
					}
				}

				break;
		}
	}
}
