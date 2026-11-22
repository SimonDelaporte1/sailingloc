<?php
/**
 * Catalogue des données de démonstration : modèles de bateaux, noms fictifs, textes.
 *
 * Les modèles reprennent des bateaux de série réels (longueur, cabines, couchages et motorisation
 * plausibles) ; les personnes et les boutiques sont fictives.
 *
 * @package Icademie_Donnees_Demo
 */

defined( 'ABSPATH' ) || exit;

/**
 * Données fixes du générateur.
 */
final class Icademie_Demo_Catalogue {

	/**
	 * Types de bateau : valeur de l'attribut « Type de bateau », libellé du titre, libellé du texte.
	 *
	 * @return array[]
	 */
	public static function types() {
		return array(
			'voilier'     => array( 'attribut' => 'voile', 'titre' => 'Voilier', 'nom' => 'voilier' ),
			'catamaran'   => array( 'attribut' => 'voile', 'titre' => 'Catamaran', 'nom' => 'catamaran' ),
			'moteur'      => array( 'attribut' => 'moteur', 'titre' => 'Bateau à moteur', 'nom' => 'bateau à moteur' ),
			'semi-rigide' => array( 'attribut' => 'semi-rigide', 'titre' => 'Semi-rigide', 'nom' => 'semi-rigide' ),
			'electrique'  => array( 'attribut' => 'electrique', 'titre' => 'Bateau électrique', 'nom' => 'bateau électrique' ),
			'hybride'     => array( 'attribut' => 'hybride', 'titre' => 'Bateau hybride', 'nom' => 'bateau hybride' ),
			'rame'        => array( 'attribut' => 'rame', 'titre' => 'Barque à rames', 'nom' => 'barque' ),
		);
	}

	/**
	 * Part de chaque type, en mer et sur un lac.
	 *
	 * @param string $water « mer » ou « lac ».
	 * @return int[]
	 */
	public static function type_weights( $water ) {
		return 'lac' === $water
			? array( 'electrique' => 30, 'moteur' => 25, 'voilier' => 25, 'rame' => 15, 'semi-rigide' => 5 )
			: array( 'voilier' => 32, 'catamaran' => 13, 'moteur' => 32, 'semi-rigide' => 12, 'hybride' => 8, 'rame' => 3 );
	}

	/**
	 * Modèles : type, nom, longueur (m), cabines, couchages, passagers, puissance, prix par jour, lac.
	 *
	 * « lac » : modèle loué aussi sur un lac (petite taille). Puissance : moteur principal, ou
	 * moteur auxiliaire d'un voilier ; vide pour les barques.
	 *
	 * @return array[]
	 */
	public static function models() {
		$rows = array(
			// Voiliers.
			array( 'voilier', 'Dufour 360 Grand Large', 10.9, 3, 6, 8, '30 CV', 290, false ),
			array( 'voilier', 'Bavaria Cruiser 34', 10.5, 2, 6, 8, '30 CV', 260, false ),
			array( 'voilier', 'Jeanneau Sun Odyssey 349', 10.3, 2, 6, 8, '20 CV', 260, false ),
			array( 'voilier', 'Beneteau Oceanis 38.1', 11.5, 3, 8, 8, '30 CV', 320, false ),
			array( 'voilier', 'Hanse 388', 11.4, 3, 6, 8, '30 CV', 310, false ),
			array( 'voilier', 'Elan Impression 40', 12.3, 3, 8, 9, '40 CV', 340, false ),
			array( 'voilier', 'Dehler 34', 10.3, 2, 6, 8, '20 CV', 280, false ),
			array( 'voilier', 'Jeanneau Sun Odyssey 410', 12.4, 3, 8, 10, '40 CV', 380, false ),
			array( 'voilier', 'Pogo 30', 9.2, 2, 6, 6, '15 CV', 240, false ),
			array( 'voilier', 'RM 1070', 10.7, 2, 6, 8, '20 CV', 270, false ),
			array( 'voilier', 'Bavaria C42', 12.7, 3, 8, 10, '50 CV', 400, false ),
			array( 'voilier', 'Dufour 430', 13.2, 3, 8, 10, '50 CV', 420, false ),
			array( 'voilier', 'Hallberg-Rassy 340', 10.3, 2, 5, 6, '30 CV', 300, false ),
			array( 'voilier', 'Jeanneau Sun Fast 3200', 9.7, 2, 6, 6, '20 CV', 230, false ),
			array( 'voilier', 'Beneteau First 27', 8.0, 1, 4, 6, '15 CV', 170, true ),
			array( 'voilier', 'Beneteau First 21', 6.5, 0, 2, 5, '5 CV', 110, true ),
			array( 'voilier', 'Catalina 22', 6.7, 1, 4, 5, '5 CV', 120, true ),
			array( 'voilier', 'Elan 210', 6.4, 0, 2, 5, '5 CV', 100, true ),
			// Catamarans.
			array( 'catamaran', 'Lagoon 42', 12.8, 4, 10, 12, '2 × 57 CV', 650, false ),
			array( 'catamaran', 'Lagoon 40', 11.7, 4, 10, 12, '2 × 45 CV', 580, false ),
			array( 'catamaran', 'Fountaine Pajot Lucia 40', 11.7, 4, 10, 12, '2 × 30 CV', 600, false ),
			array( 'catamaran', 'Bali 4.1', 12.1, 4, 10, 12, '2 × 45 CV', 620, false ),
			array( 'catamaran', 'Nautitech 40 Open', 11.9, 4, 8, 10, '2 × 30 CV', 590, false ),
			array( 'catamaran', 'Excess 11', 11.3, 3, 8, 10, '2 × 30 CV', 520, false ),
			array( 'catamaran', 'Leopard 42', 12.6, 4, 10, 12, '2 × 45 CV', 640, false ),
			// Bateaux à moteur.
			array( 'moteur', 'Jeanneau Cap Camarat 7.5 WA', 7.5, 1, 2, 10, '250 CV', 330, false ),
			array( 'moteur', 'Beneteau Flyer 7 Spacedeck', 7.2, 1, 2, 8, '200 CV', 290, false ),
			array( 'moteur', 'Quicksilver Activ 605 Open', 6.0, 0, 0, 7, '115 CV', 210, true ),
			array( 'moteur', 'Jeanneau Merry Fisher 795', 7.9, 2, 4, 8, '250 CV', 320, false ),
			array( 'moteur', 'Beneteau Antares 9', 9.0, 2, 4, 10, '300 CV', 420, false ),
			array( 'moteur', 'Pacific Craft 670 Sun Cruiser', 6.7, 1, 2, 8, '150 CV', 240, false ),
			array( 'moteur', 'Rio 650 Cabin', 6.5, 1, 2, 7, '150 CV', 230, true ),
			array( 'moteur', 'Sea Ray SPX 190', 5.9, 0, 0, 8, '150 CV', 220, true ),
			array( 'moteur', 'Frauscher 717 GT', 7.2, 1, 2, 8, '300 CV', 450, true ),
			array( 'moteur', 'Jeanneau Leader 9', 9.4, 1, 4, 10, '2 × 200 CV', 480, false ),
			array( 'moteur', 'Beneteau Gran Turismo 36', 11.0, 2, 4, 10, '2 × 300 CV', 650, false ),
			array( 'moteur', 'Quicksilver Activ 505 Open', 5.0, 0, 0, 5, '80 CV', 160, true ),
			array( 'moteur', 'Ryds 548 DC', 5.5, 0, 0, 6, '60 CV', 150, true ),
			// Semi-rigides.
			array( 'semi-rigide', 'Zodiac Medline 580', 5.8, 0, 0, 10, '150 CV', 220, false ),
			array( 'semi-rigide', 'Capelli Tempest 570', 5.7, 0, 0, 10, '115 CV', 200, false ),
			array( 'semi-rigide', 'Highfield Sport 560', 5.6, 0, 0, 9, '115 CV', 210, false ),
			array( 'semi-rigide', 'Bombard Explorer 550', 5.5, 0, 0, 9, '100 CV', 180, true ),
			array( 'semi-rigide', 'Joker Boat Clubman 22', 6.6, 0, 0, 12, '200 CV', 280, false ),
			array( 'semi-rigide', 'Zodiac Pro Open 650', 6.5, 0, 0, 12, '200 CV', 300, false ),
			array( 'semi-rigide', '3D Tender Lux 500', 5.0, 0, 0, 8, '60 CV', 150, true ),
			// Bateaux électriques.
			array( 'electrique', 'Ruban Bleu Lagon 520', 5.2, 0, 0, 6, '4 kW', 95, true ),
			array( 'electrique', 'Marian M 800', 8.0, 0, 0, 10, '10 kW', 140, true ),
			array( 'electrique', 'Frauscher 740 Mirage Air', 7.4, 0, 0, 8, '60 kW', 260, true ),
			array( 'electrique', 'Alfastreet 23 Cabin', 7.0, 1, 2, 8, '10 kW', 180, true ),
			// Bateaux hybrides.
			array( 'hybride', 'Greenline 33', 10.0, 2, 4, 8, '220 CV et 7 kW', 450, false ),
			array( 'hybride', 'Greenline 39', 11.9, 2, 6, 10, '2 × 220 CV et 7 kW', 520, false ),
			// Barques.
			array( 'rame', 'Rigiflex Cap 370', 3.7, 0, 0, 4, '', 45, true ),
			array( 'rame', 'Pioner 12', 3.7, 0, 0, 4, '', 40, true ),
			array( 'rame', 'Linder 410', 4.1, 0, 0, 4, '', 50, true ),
		);
		$models = array();

		foreach ( $rows as $row ) {
			$models[] = array_combine( array( 'type', 'modele', 'longueur', 'cabines', 'couchages', 'passagers', 'puissance', 'prix', 'lac' ), $row );
		}

		return $models;
	}

	/**
	 * Équipements par type (valeurs de l'attribut « Équipements ») : de série, puis au choix.
	 *
	 * @param string $type     Type.
	 * @param float  $length   Longueur.
	 * @return array{0: string[], 1: string[]}
	 */
	public static function equipment( $type, $length ) {
		$big = $length >= 11.5;

		switch ( $type ) {
			case 'voilier':
				// Petit voilier de journée : peu d'équipement.
				if ( $length < 8 ) {
					return array( array(), array( 'gps', 'taud-de-soleil', 'masques-et-tubas', 'paddle', 'enceintes-exterieures', 'gennaker' ) );
				}

				return array(
					array( 'gps', 'pilote-automatique', 'taud-de-soleil' ),
					array_merge( array( 'annexe', 'moteur-dannexe', 'douche-exterieure', 'guindeau-electrique', 'gennaker', 'panneaux-solaires', 'masques-et-tubas', 'paddle', 'enceintes-exterieures', 'canoe-kayak' ), $big ? array( 'dessalinisateur', 'propulseur-detrave', 'generateur' ) : array() ),
				);
			case 'catamaran':
				return array(
					array( 'gps', 'pilote-automatique', 'annexe', 'moteur-dannexe', 'taud-de-soleil', 'douche-exterieure', 'guindeau-electrique' ),
					array( 'panneaux-solaires', 'dessalinisateur', 'generateur', 'air-conditionne', 'masques-et-tubas', 'paddle', 'enceintes-exterieures', 'canoe-kayak', 'gennaker' ),
				);
			case 'moteur':
				return array(
					array( 'gps', 'taud-de-soleil', 'douche-exterieure' ),
					array_merge( array( 'enceintes-exterieures', 'masques-et-tubas', 'paddle', 'ski-nautique', 'wakeboard', 'bouee-tractable', 'banane-gonflable', 'guindeau-electrique' ), $length >= 8 ? array( 'propulseur-detrave', 'annexe' ) : array(), $length >= 10 ? array( 'generateur', 'air-conditionne' ) : array() ),
				);
			case 'semi-rigide':
				return array( array( 'gps' ), array( 'enceintes-exterieures', 'masques-et-tubas', 'bouee-tractable', 'ski-nautique', 'wakeboard', 'banane-gonflable', 'taud-de-soleil', 'douche-exterieure', 'paddle' ) );
			case 'electrique':
				return array( array( 'taud-de-soleil' ), array( 'enceintes-exterieures', 'paddle', 'masques-et-tubas' ) );
			case 'hybride':
				return array(
					array( 'gps', 'pilote-automatique', 'panneaux-solaires', 'annexe', 'taud-de-soleil', 'guindeau-electrique' ),
					array( 'douche-exterieure', 'propulseur-detrave', 'dessalinisateur', 'generateur', 'moteur-dannexe', 'paddle', 'masques-et-tubas', 'enceintes-exterieures' ),
				);
		}

		return array( array(), array() );
	}

	/**
	 * Prénoms (avec le genre, pour l'accord des textes) et noms de famille fictifs.
	 *
	 * @return array{0: array[], 1: string[]}
	 */
	public static function people() {
		$women = array( 'Camille', 'Léa', 'Manon', 'Chloé', 'Sarah', 'Julie', 'Emma', 'Inès', 'Clara', 'Lucie', 'Marion', 'Pauline', 'Élise', 'Anaïs', 'Juliette', 'Margaux', 'Charlotte', 'Mathilde', 'Océane', 'Laura', 'Agathe', 'Hélène', 'Sophie', 'Claire', 'Nathalie', 'Isabelle', 'Céline', 'Aurélie', 'Émilie', 'Lisa' );
		$men   = array( 'Lucas', 'Hugo', 'Louis', 'Thomas', 'Nicolas', 'Julien', 'Antoine', 'Maxime', 'Alexandre', 'Romain', 'Mathieu', 'Pierre', 'Paul', 'Baptiste', 'Clément', 'Vincent', 'Guillaume', 'Florian', 'Quentin', 'Adrien', 'Olivier', 'Stéphane', 'Laurent', 'Benoît', 'Damien', 'Yann', 'Erwan', 'Loïc', 'Marc', 'Sébastien' );
		$first = array();

		foreach ( $women as $name ) {
			$first[] = array( $name, 'f' );
		}

		foreach ( $men as $name ) {
			$first[] = array( $name, 'm' );
		}

		$last = array( 'Martin', 'Bernard', 'Dubois', 'Robert', 'Richard', 'Petit', 'Durand', 'Leroy', 'Moreau', 'Lefebvre', 'Michel', 'Garcia', 'Bertrand', 'Roux', 'Fournier', 'Morel', 'Girard', 'André', 'Mercier', 'Dupont', 'Lambert', 'Bonnet', 'François', 'Martinez', 'Legrand', 'Garnier', 'Faure', 'Rousseau', 'Blanc', 'Guérin', 'Muller', 'Henry', 'Roussel', 'Perrin', 'Morin', 'Gauthier', 'Dumont', 'Lopez', 'Fontaine', 'Chevalier', 'Robin', 'Masson', 'Gérard', 'Boyer', 'Denis', 'Lemaire', 'Duval', 'Joly', 'Roger', 'Le Gall', 'Le Bihan', 'Kervella', 'Paoli', 'Santoni', 'Albertini', 'Marchetti', 'Ferrand', 'Barbier', 'Arnaud', 'Colin' );

		return array( $first, $last );
	}

	/**
	 * Villes des locataires.
	 *
	 * @return string[]
	 */
	public static function cities() {
		return array( 'Paris', 'Lyon', 'Bordeaux', 'Nantes', 'Lille', 'Toulouse', 'Rennes', 'Strasbourg', 'Montpellier', 'Grenoble', 'Dijon', 'Tours', 'Annecy', 'Marseille', 'Nice', 'Rouen', 'Caen', 'Brest', 'Reims', 'Clermont-Ferrand', 'Angers', 'Orléans', 'Metz', 'La Rochelle', 'Pau' );
	}

	/**
	 * Noms des boutiques des propriétaires (« %1$s » prénom, « %2$s » nom).
	 *
	 * @return string[]
	 */
	public static function shop_patterns() {
		return array( 'Les bateaux de %1$s', '%1$s Nautique', 'Croisières %2$s', '%2$s Plaisance', 'Escales %2$s', 'Location %2$s', 'Voiles %2$s', 'Atelier nautique %2$s', 'Les escapades de %1$s', '%2$s Marine' );
	}

	/**
	 * Présentation d'un propriétaire.
	 *
	 * @return string[] « %1$s » prénom, « %2$s » lieu (« à Ajaccio »), « %3$d » année, « %4$s » accord
	 *                  (« e » ou rien), « %5$s » « Navigateur » ou « Navigatrice ».
	 */
	public static function owner_bios() {
		return array(
			'%1$s loue ses bateaux %2$s depuis %3$d. Passionné%4$s de navigation, %1$s connaît chaque mouillage de la région et partage volontiers ses bonnes adresses.',
			'Propriétaire installé%4$s %2$s depuis %3$d, %1$s entretient ses bateaux toute l\'année pour des locations sans mauvaise surprise.',
			'%5$s depuis l\'enfance, %1$s propose ses bateaux %2$s depuis %3$d, avec un accueil personnalisé au port.',
			'%1$s a commencé la location %2$s en %3$d avec un seul bateau. Aujourd\'hui, sa flotte accueille familles et équipages confirmés.',
		);
	}

	/**
	 * Nombres en lettres (durées des avis).
	 *
	 * @param int $days Jours.
	 * @return string
	 */
	public static function days_text( $days ) {
		$words = array( 1 => 'une journée', 2 => 'deux jours', 3 => 'trois jours', 4 => 'quatre jours', 5 => 'cinq jours', 6 => 'six jours', 7 => 'une semaine', 8 => 'huit jours', 9 => 'neuf jours', 10 => 'dix jours' );

		return isset( $words[ $days ] ) ? $words[ $days ] : $days . ' jours';
	}

	/**
	 * Paragraphes de la description (« Le mot du propriétaire »), par type.
	 *
	 * Variables : {modele}, {longueur}, {lieu}, {lieu_court}, {passagers}, {puissance},
	 * {cabines}, {couchages}, {equipements}, {rdv}.
	 *
	 * @return array[] « intro », « interieur » (avec cabines), « ouvert » (sans cabine).
	 */
	public static function descriptions() {
		return array(
			'voilier'     => array(
				'intro'     => array(
					'Ce {modele} de {longueur} est idéal pour naviguer {lieu} à la journée ou sur plusieurs jours.',
					'Je propose à la location mon {modele}, un voilier de {longueur} agréable à barrer et très stable.',
					'Voilier de croisière de {longueur}, mon {modele} est parfait pour découvrir {lieu_court} en famille ou entre amis.',
				),
				'interieur' => array(
					'L\'intérieur compte {cabines} et {couchages} couchages ; le carré lumineux accueille tout l\'équipage autour de la table.',
					'À l\'intérieur : {cabines}, une salle d\'eau avec douche chaude et une cuisine équipée (réfrigérateur, deux feux).',
				),
				'ouvert'    => array(
					'Petit voilier vif et facile, il convient à {passagers} personnes pour de belles sorties à la journée.',
					'Sa petite cabine abrite le matériel ; le cockpit spacieux accueille {passagers} personnes.',
				),
			),
			'catamaran'   => array(
				'intro'     => array(
					'Grand catamaran de {longueur}, le {modele} offre beaucoup d\'espace et de confort pour {passagers} personnes.',
					'Le {modele} est un catamaran de {longueur}, stable et spacieux, parfait pour une croisière {lieu}.',
				),
				'interieur' => array(
					'Il dispose de {cabines} avec salle d\'eau, soit {couchages} couchages, d\'un carré ouvert sur le cockpit et d\'une cuisine en U.',
					'{cabines} et {couchages} couchages : chacun trouve sa place, et le trampoline avant fait le bonheur des enfants.',
				),
				'ouvert'    => array(),
			),
			'moteur'      => array(
				'intro'     => array(
					'Le {modele} est un bateau à moteur de {longueur} ({puissance}) idéal pour rejoindre les criques {lieu}.',
					'Rapide et confortable, mon {modele} de {longueur} vous emmène en toute sécurité {lieu}.',
					'Bateau à moteur de {longueur} motorisé {puissance}, le {modele} est parfait pour une journée de baignade.',
				),
				'interieur' => array(
					'Il dispose de {cabines} avec {couchages} couchages, d\'un bain de soleil avant et d\'une table de cockpit.',
					'La cabine ({couchages} couchages) permet une sieste à l\'abri ; à l\'arrière, la plateforme de bain facilite les baignades.',
				),
				'ouvert'    => array(
					'Bateau open avec grand bain de soleil, banquette arrière et table de cockpit pour {passagers} personnes.',
					'Sa console centrale laisse beaucoup de place à bord : jusqu\'à {passagers} personnes, avec bain de soleil à l\'avant.',
				),
			),
			'semi-rigide' => array(
				'intro'     => array(
					'Semi-rigide {modele} de {longueur} motorisé {puissance} : rapide, sûr et parfait pour la baignade.',
					'Le {modele} est un semi-rigide de {longueur}, idéal pour explorer {lieu_court} à la journée.',
				),
				'interieur' => array(),
				'ouvert'    => array(
					'Jusqu\'à {passagers} personnes à bord, avec bain de soleil avant, coffre de rangement et échelle de bain.',
					'Ses boudins le rendent très stable et sûr ; il accueille {passagers} personnes confortablement.',
				),
			),
			'electrique'  => array(
				'intro'     => array(
					'Bateau électrique silencieux, le {modele} se pilote facilement pour une balade {lieu}.',
					'Le {modele} ({longueur}) glisse sans bruit ni odeur : la meilleure façon de découvrir {lieu_court}.',
				),
				'interieur' => array(
					'Sa petite cabine ({couchages} couchages) abrite du soleil ; le cockpit accueille {passagers} personnes.',
				),
				'ouvert'    => array(
					'Jusqu\'à {passagers} personnes à bord sous le taud de soleil, avec banquettes confortables.',
					'Moteur électrique de {puissance} : autonomie largement suffisante pour une journée de balade.',
				),
			),
			'hybride'     => array(
				'intro'     => array(
					'Le {modele} associe un moteur diesel et une propulsion électrique : navigation silencieuse au mouillage et grande autonomie.',
					'Bateau hybride de {longueur}, le {modele} recharge ses batteries grâce à ses panneaux solaires.',
				),
				'interieur' => array(
					'Il offre {cabines}, {couchages} couchages, un carré lumineux et un grand cockpit abrité.',
				),
				'ouvert'    => array(),
			),
			'rame'        => array(
				'intro'     => array(
					'Barque {modele} à rames, stable et légère, pour une balade tranquille {lieu}.',
					'Envie de calme ? Ma barque {modele} se manie sans effort, même pour les débutants.',
				),
				'interieur' => array(),
				'ouvert'    => array(
					'Jusqu\'à {passagers} personnes à bord ; gilets de sauvetage fournis pour toute la famille.',
				),
			),
		);
	}

	/**
	 * Dernières phrases de la description.
	 *
	 * @return array[] « rdv » (accueil), « skipper », « sans_skipper », « divers ».
	 */
	public static function description_endings() {
		return array(
			'rdv'          => array(
				'Je vous accueille {rdv} pour l\'état des lieux et la présentation du bateau.',
				'Rendez-vous {rdv} : je vous remets les clés après un tour complet du bateau.',
			),
			'skipper'      => array(
				'Le bateau est loué avec skipper : il connaît les meilleurs mouillages {lieu}.',
				'Location avec skipper, pour profiter de la navigation sans vous soucier des manœuvres.',
			),
			'sans_skipper' => array(
				'Un briefing sur les zones de navigation vous est proposé au départ.',
				'Je vous indique au départ les plus beaux coins et les zones à éviter.',
			),
			'divers'       => array(
				'Animaux acceptés sur demande.',
				'Bateau non-fumeur.',
				'Les débutants sont les bienvenus : je prends le temps d\'expliquer.',
				'Prévoyez des chaussures à semelles claires.',
				'Glacière et gilets de sauvetage fournis.',
			),
		);
	}

	/**
	 * Morceaux des avis : début selon la note, phrases par critère, phrase du type, fin.
	 *
	 * Variables : {proprietaire}, {lieu}, {lieu_court}, {duree}.
	 *
	 * @return array
	 */
	public static function reviews() {
		return array(
			'debut'    => array(
				5 => array( 'Une location parfaite du début à la fin.', 'Superbe expérience, on reviendra sans hésiter.', 'Nous avons passé {duree} {lieu} : c\'était magnifique.', 'Un vrai coup de cœur pour ce bateau.', 'Excellent séjour à bord, rien à redire.', 'Location idéale pour découvrir {lieu_court}.' ),
				4 => array( 'Très bonne location dans l\'ensemble.', 'Belle expérience, quelques détails à améliorer.', 'Bon bateau et bon accueil.', 'Nous avons passé un très bon moment {lieu}.', 'Location réussie, nous recommandons.' ),
				3 => array( 'Location correcte, sans plus.', 'Expérience mitigée.', 'Un séjour agréable malgré quelques déceptions.', 'Le bateau fait le travail, mais tout n\'était pas parfait.' ),
				2 => array( 'Déçus par cette location.', 'Expérience décevante dans l\'ensemble.', 'Nous attendions mieux pour ce prix.' ),
				1 => array( 'Très mauvaise expérience.', 'Location à éviter en l\'état.', 'Grosse déception, nous ne recommandons pas.' ),
			),
			'criteres' => array(
				'conformite'    => array(
					'+' => array( 'Le bateau correspond exactement aux photos et à la description.', 'Tout l\'équipement annoncé était bien à bord.', 'Conforme à l\'annonce, et même mieux en vrai.' ),
					'-' => array( 'Plusieurs équipements annoncés manquaient à bord.', 'Le bateau était moins bien entretenu que sur les photos.', 'L\'annonce ne correspondait pas tout à fait au bateau.' ),
				),
				'proprete'      => array(
					'+' => array( 'Bateau impeccable et très propre à notre arrivée.', 'Tout était propre et bien rangé.', 'Pont et cockpit nickel à l\'embarquement.' ),
					'-' => array( 'Le bateau n\'avait pas été nettoyé depuis la location précédente.', 'Propreté à revoir, surtout dans les coffres.', 'Nous avons dû faire un peu de ménage en arrivant.' ),
				),
				'communication' => array(
					'+' => array( '{proprietaire} a été très disponible et de bon conseil.', 'Accueil chaleureux de {proprietaire}, qui nous a présenté le bateau en détail.', 'Réponses rapides avant le départ et briefing très clair.' ),
					'-' => array( 'Difficile de joindre {proprietaire} avant le départ.', 'Rendez-vous décalé au dernier moment, sans explication.', 'Briefing expédié en quelques minutes.' ),
				),
				'qualite_prix'  => array(
					'+' => array( 'Excellent rapport qualité-prix.', 'Prix tout à fait justifié vu l\'état du bateau.', 'Tarif raisonnable pour {lieu_court}.' ),
					'-' => array( 'Un peu cher pour ce que c\'est.', 'Le prix nous a paru élevé au regard des prestations.', 'Rapport qualité-prix décevant.' ),
				),
			),
			'type'     => array(
				'voilier'     => array( 'Le bateau marche très bien, même par petit temps.', 'Belle navigation jusqu\'aux mouillages voisins.' ),
				'catamaran'   => array( 'Très spacieux, idéal à plusieurs familles.', 'Stable au mouillage, tout le monde a bien dormi.' ),
				'moteur'      => array( 'Moteur fiable et consommation raisonnable.', 'Parfait pour enchaîner les criques dans la journée.' ),
				'semi-rigide' => array( 'Parfait pour rejoindre les plages inaccessibles à pied.', 'Rapide et très stable, même avec un peu de clapot.' ),
				'electrique'  => array( 'Silencieux et très simple à piloter, idéal en famille.', 'Quel plaisir de naviguer sans bruit.' ),
				'hybride'     => array( 'Navigation silencieuse en mode électrique, très agréable.', 'Très bonne autonomie, nous avons peu utilisé le diesel.' ),
				'rame'        => array( 'Idéal pour une balade tranquille.', 'Les enfants ont adoré ramer à tour de rôle.' ),
			),
			'fin'      => array(
				'+' => array( 'Merci {proprietaire} !', 'Nous reviendrons l\'an prochain.', 'À recommander les yeux fermés.', 'Merci pour ce beau séjour.' ),
				'=' => array( 'Bon séjour malgré tout.', 'À voir pour une prochaine fois.' ),
				'-' => array( 'Nous ne renouvellerons pas.', 'Dommage, le bateau a du potentiel.' ),
			),
		);
	}

	/**
	 * Lacs connus (catégorie de port) : lieu et rendez-vous.
	 *
	 * @return array[] Par mot-clé du nom ou du slug du port.
	 */
	public static function lakes() {
		return array(
			'annecy'       => 'le lac d\'Annecy',
			'leman'        => 'le Léman',
			'geneve'       => 'le Léman',
			'bourget'      => 'le lac du Bourget',
			'aix-les-bains' => 'le lac du Bourget',
			'serre-poncon' => 'le lac de Serre-Ponçon',
			'lac'          => 'le lac',
		);
	}

	/**
	 * Pays (catégories « International ») : préposition.
	 *
	 * @return string[] Nom en minuscules sans accents → préposition.
	 */
	public static function countries() {
		return array(
			'croatie'    => 'en',
			'grece'      => 'en',
			'italie'     => 'en',
			'espagne'    => 'en',
			'turquie'    => 'en',
			'sardaigne'  => 'en',
			'sicile'     => 'en',
			'corse'      => 'en',
			'portugal'   => 'au',
			'montenegro' => 'au',
			'malte'      => 'à',
		);
	}
}
