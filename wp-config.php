<?php
/**
 * The base configuration for WordPress
 *
 * The wp-config.php creation script uses this file during the
 * installation. You don't have to use the web site, you can
 * copy this file to "wp-config.php" and fill in the values.
 *
 * This file contains the following configurations:
 *
 * * MySQL settings
 * * Secret keys
 * * Database table prefix
 * * ABSPATH
 *
 * @link https://codex.wordpress.org/Editing_wp-config.php
 *
 * @package WordPress
 */

// ** MySQL settings - You can get this info from your web host ** //
/** The name of the database for WordPress */
define( 'WPCACHEHOME', '/home/clients/4a4e3969b110f2d886bb8cd89bb39b84/sites/icademie.simon-delaporte.fr/wp-content/plugins/wp-super-cache/' );
define('WP_CACHE', true);
define('DB_NAME', 'i59smk_WP1494238');

/** MySQL database username */
define('DB_USER', 'i59smk_WP1494238');

/** MySQL database password */
define('DB_PASSWORD', 'kKtAlqg0tI');

/** MySQL hostname */
define('DB_HOST', 'i59smk.myd.infomaniak.com');

/** Database Charset to use in creating database tables. */
define('DB_CHARSET', 'utf8');

/** The Database Collate type. Don't change this if in doubt. */
define('DB_COLLATE', '');

/**#@+
 * Authentication Unique Keys and Salts.
 *
 * Change these to different unique phrases!
 * You can generate these using the {@link https://api.wordpress.org/secret-key/1.1/salt/ WordPress.org secret-key service}
 * You can change these at any point in time to invalidate all existing cookies. This will force all users to have to log in again.
 *
 * @since 2.6.0
 */
define('AUTH_KEY',         '=k@6i^w|m@s|g4eWl^~HD2?>7h=|y2}HAjzow.xyvL`.qr,;daw8p=BU5lpCljD.');
define('SECURE_AUTH_KEY',  'DO(;va^pIe:nlFk+bWghWru<iA0^Q;9Q&g{5IPNe4tR`,Ro1UOeA_>,~AUmb5c(F');
define('LOGGED_IN_KEY',    'e|gV>E!73@?m@kIrZ%e>v<csAk3d7+lAifJ*5p>M/,>D=~(j>o<U*`E2zUZBHYvf');
define('NONCE_KEY',        'R&YD_l@DEzzXAqobgekTKzS}.}LoF=f|piwUuVf?QX{kOO?L2RtF;L<_.abuHC@n');
define('AUTH_SALT',        'x?J=u,9rp.D-Us1l_wRr!}!S{^L=/rXA=H.I|DXASaL,xtY#?qECmt}Z*(G7FdAL');
define('SECURE_AUTH_SALT', '*y^1-@|FP,m5FI@>A{q9-G#C*&vJ^_,%p0nxiLt=28=i^FY+-v)F`B>{.<Kx|hNM');
define('LOGGED_IN_SALT',   'T0IprkRL;^DX2{@j^L&4b>z>Q(x^T7Q<QHm_@EU(gd4;~oj^{|TSCA.k)0M^:_P.');
define('NONCE_SALT',       '%;0vmT)by0==!aSqfBw85AfVMR;GZH)n`C*!{aj6@DGTT(eL)INL!=FiWpFs-elp');

/**#@-*/

/**
 * WordPress Database Table prefix.
 *
 * You can have multiple installations in one database if you give each
 * a unique prefix. Only numbers, letters, and underscores please!
 */
$table_prefix  = 'wp_1494238_';

/**
 * WordPress Localized Language, defaults to English.
 *
 * Change this to localize WordPress. A corresponding MO file for the chosen
 * language must be installed to wp-content/languages. For example, install
 * de_DE.mo to wp-content/languages and set WPLANG to 'de_DE' to enable German
 * language support.
 */
//define('WPLANG', 'fr_FR');

/**
 * For developers: WordPress debugging mode.
 *
 * Change this to true to enable the display of notices during development.
 * It is strongly recommended that plugin and theme developers use WP_DEBUG
 * in their development environments.
 *
 * For information on other constants that can be used for debugging,
 * visit the Codex.
 *
 * @link https://codex.wordpress.org/Debugging_in_WordPress
 */
define('WP_DEBUG',          false);
define('WP_DEBUG_LOG',      false);
define('WP_DEBUG_DISPLAY',  false);

/* That's all, stop editing! Happy blogging. */

/** Absolute path to the WordPress directory. */
if ( !defined('ABSPATH') )
	define('ABSPATH', dirname(__FILE__) . '/');

/** Sets up WordPress vars and included files. */
require_once(ABSPATH . 'wp-settings.php');
