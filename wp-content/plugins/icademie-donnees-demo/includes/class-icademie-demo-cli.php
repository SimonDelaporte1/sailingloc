<?php
/**
 * Commande WP-CLI « wp icademie-demo ».
 *
 * @package Icademie_Donnees_Demo
 */

defined( 'ABSPATH' ) || exit;

/**
 * Génère ou supprime les données de démonstration SailingLoc.
 */
final class Icademie_Demo_CLI {

	/**
	 * Génère les données de démonstration.
	 *
	 * ## OPTIONS
	 *
	 * [--photos=<zip>]
	 * : Lot de photos à déposer d'abord (photos-demo.zip).
	 *
	 * [--proprietaires=<nombre>]
	 * : Comptes propriétaires (10 par défaut).
	 *
	 * [--annonces-min=<nombre>]
	 * : Annonces par propriétaire, au moins (5).
	 *
	 * [--annonces-max=<nombre>]
	 * : Annonces par propriétaire, au plus (10).
	 *
	 * [--commandes=<nombre>]
	 * : Réservations par bateau (3).
	 *
	 * [--locataires=<nombre>]
	 * : Comptes locataires (30).
	 *
	 * [--avis=<pourcentage>]
	 * : Part des locations terminées suivies d'un avis (70).
	 *
	 * [--graine=<nombre>]
	 * : Graine du tirage (au hasard par défaut) : la même graine donne les mêmes données.
	 *
	 * @param array $args  Arguments.
	 * @param array $assoc Options.
	 */
	public function generer( $args, $assoc ) {
		if ( isset( $assoc['photos'] ) ) {
			$count = Icademie_Demo_Photos::install( $assoc['photos'] );

			if ( is_wp_error( $count ) ) {
				WP_CLI::error( $count->get_error_message() );
			}

			WP_CLI::log( sprintf( '%d photos déposées.', $count ) );
		}

		$settings = array();

		foreach ( array(
			'proprietaires' => 'proprietaires',
			'annonces-min'  => 'annonces_min',
			'annonces-max'  => 'annonces_max',
			'commandes'     => 'commandes',
			'locataires'    => 'locataires',
			'avis'          => 'avis',
			'graine'        => 'graine',
		) as $option => $key ) {
			if ( isset( $assoc[ $option ] ) ) {
				$settings[ $key ] = $assoc[ $option ];
			}
		}

		$status = Icademie_Demo_Generateur::start( $settings );

		if ( is_wp_error( $status ) ) {
			WP_CLI::error( $status->get_error_message() );
		}

		$this->reprendre();
	}

	/**
	 * Reprend une génération interrompue.
	 */
	public function reprendre() {
		$last = '';

		do {
			$status = Icademie_Demo_Generateur::run();

			if ( is_wp_error( $status ) ) {
				WP_CLI::error( $status->get_error_message() );
			}

			if ( $status['message'] !== $last ) {
				WP_CLI::log( $status['message'] );
				$last = $status['message'];
			}
		} while ( 'en_cours' === $status['etat'] );

		$this->etat();
	}

	/**
	 * Supprime toutes les données de démonstration.
	 */
	public function supprimer() {
		do {
			$status = Icademie_Demo_Generateur::delete();
			WP_CLI::log( $status['message'] );
		} while ( 'vide' !== $status['etat'] );

		WP_CLI::success( 'Données de démonstration supprimées.' );
	}

	/**
	 * Affiche ce qui a été créé.
	 */
	public function etat() {
		$status = Icademie_Demo_Generateur::status();

		WP_CLI::log( $status['message'] );

		foreach ( $status['compte'] as $kind => $count ) {
			WP_CLI::log( sprintf( '  %s : %d', $kind, $count ) );
		}
	}
}
