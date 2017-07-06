<?php
/**
 * La configuration de base de votre installation WordPress.
 *
 * Ce fichier contient les réglages de configuration suivants : réglages MySQL,
 * préfixe de table, clés secrètes, langue utilisée, et ABSPATH.
 * Vous pouvez en savoir plus à leur sujet en allant sur
 * {@link http://codex.wordpress.org/fr:Modifier_wp-config.php Modifier
 * wp-config.php}. C’est votre hébergeur qui doit vous donner vos
 * codes MySQL.
 *
 * Ce fichier est utilisé par le script de création de wp-config.php pendant
 * le processus d’installation. Vous n’avez pas à utiliser le site web, vous
 * pouvez simplement renommer ce fichier en "wp-config.php" et remplir les
 * valeurs.
 *
 * @package WordPress
 */

// ** Réglages MySQL - Votre hébergeur doit vous fournir ces informations. ** //
/** Nom de la base de données de WordPress. */
define('DB_NAME', 'gaiaappfkhgaia');

/** Utilisateur de la base de données MySQL. */
define('DB_USER', 'gaiaappfkhgaia');

/** Mot de passe de la base de données MySQL. */
define('DB_PASSWORD', 'Q229Csv8qCrR');

/** Adresse de l’hébergement MySQL. */
define('DB_HOST', 'gaiaappfkhgaia.mysql.db');

/** Jeu de caractères à utiliser par la base de données lors de la création des tables. */
define('DB_CHARSET', 'utf8mb4');

/** Type de collation de la base de données.
  * N’y touchez que si vous savez ce que vous faites.
  */
define('DB_COLLATE', '');

/**#@+
 * Clés uniques d’authentification et salage.
 *
 * Remplacez les valeurs par défaut par des phrases uniques !
 * Vous pouvez générer des phrases aléatoires en utilisant
 * {@link https://api.wordpress.org/secret-key/1.1/salt/ le service de clefs secrètes de WordPress.org}.
 * Vous pouvez modifier ces phrases à n’importe quel moment, afin d’invalider tous les cookies existants.
 * Cela forcera également tous les utilisateurs à se reconnecter.
 *
 * @since 2.6.0
 */
define('AUTH_KEY',         'Xc_DT~$6;L0[kFuwdC#JPW9L$2biYdzl,q5DW)GU*dsBb/k*CXZg4}4QfV:!OsYV');
define('SECURE_AUTH_KEY',  'O&n$00<o:m)YDrTN)U<7OF$JHXfjyrm$Iiaw4LRA]Cl*MrSM/e6B-jcP^D0[;g;(');
define('LOGGED_IN_KEY',    'T^ K2im3&b MJ*1sP#:nH/*F!/h;jYUW}KK$(-60iD+Vy,&OO ^@@F:..5edDwU?');
define('NONCE_KEY',        'o9H).X3XQn8-@(Y]bd I4G=aW[&:FXtNCe5v#I#~2wbExN,R$v?Tc[ei)R8Do^{W');
define('AUTH_SALT',        'wsU+u8c}Hw|o0(_pMTSy~A*q2y*}sih-sDo|;vYm2Ns#,~1V:w|bJA!hb^H;-T9y');
define('SECURE_AUTH_SALT', '0kpUn66#WKL$i_Z7Ki:>%o=&uGY*`Bd8Gg,uyz[VWSY^G:Rd.=?_anS/:F=}9)h&');
define('LOGGED_IN_SALT',   'u?y}9wfVyt&wbo(p(}~dflA1K9]N2aC3mET]gAFYd)tY!W!DD`Xc~,C49.v!1d+{');
define('NONCE_SALT',       'a58[hP;BmtuBjVg!U_BybRN~)Xs~5GUJ##>PUXmR2M!&OvT$sdT`{zSM6f2L-z[:');
/**#@-*/

/**
 * Préfixe de base de données pour les tables de WordPress.
 *
 * Vous pouvez installer plusieurs WordPress sur une seule base de données
 * si vous leur donnez chacune un préfixe unique.
 * N’utilisez que des chiffres, des lettres non-accentuées, et des caractères soulignés !
 */
$table_prefix  = 'wp_';

/**
 * Pour les développeurs : le mode déboguage de WordPress.
 *
 * En passant la valeur suivante à "true", vous activez l’affichage des
 * notifications d’erreurs pendant vos essais.
 * Il est fortemment recommandé que les développeurs d’extensions et
 * de thèmes se servent de WP_DEBUG dans leur environnement de
 * développement.
 *
 * Pour plus d’information sur les autres constantes qui peuvent être utilisées
 * pour le déboguage, rendez-vous sur le Codex.
 *
 * @link https://codex.wordpress.org/Debugging_in_WordPress
 */
define('WP_DEBUG', false);

/* C’est tout, ne touchez pas à ce qui suit ! */

/** Chemin absolu vers le dossier de WordPress. */
if ( !defined('ABSPATH') )
	define('ABSPATH', dirname(__FILE__) . '/');

/** Réglage des variables de WordPress et de ses fichiers inclus. */
require_once(ABSPATH . 'wp-settings.php');