<?php
/**
 * Dynamic wp-config.php for Dockerized Bitnami WordPress
 */

// 1. Database settings (pulled directly from environment variables)
define( 'DB_NAME',     getenv('WORDPRESS_DATABASE_NAME') ?: 'bitnami_wordpress' );
define( 'DB_USER',     getenv('WORDPRESS_DATABASE_USER') ?: 'bn_wordpress' );
define( 'DB_PASSWORD', getenv('WORDPRESS_DATABASE_PASSWORD') ?: '' );
define( 'DB_HOST',     getenv('WORDPRESS_DATABASE_HOST') ?: 'mariadb:3306' );
define( 'DB_CHARSET',  'utf8' );
define( 'DB_COLLATE',  '' );

// 2. Authentication Unique Keys and Salts
// In production, inject these via environment variables or a secrets manager.
define( 'AUTH_KEY',         getenv('WORDPRESS_AUTH_KEY')         ?: 'put your unique phrase here' );
define( 'SECURE_AUTH_KEY',  getenv('WORDPRESS_SECURE_AUTH_KEY')  ?: 'put your unique phrase here' );
define( 'LOGGED_IN_KEY',    getenv('WORDPRESS_LOGGED_IN_KEY')    ?: 'put your unique phrase here' );
define( 'NONCE_KEY',        getenv('WORDPRESS_NONCE_KEY')        ?: 'put your unique phrase here' );
define( 'AUTH_SALT',        getenv('WORDPRESS_AUTH_SALT')        ?: 'put your unique phrase here' );
define( 'SECURE_AUTH_SALT', getenv('WORDPRESS_SECURE_AUTH_SALT') ?: 'put your unique phrase here' );
define( 'LOGGED_IN_SALT',   getenv('WORDPRESS_LOGGED_IN_SALT')   ?: 'put your unique phrase here' );
define( 'NONCE_SALT',       getenv('WORDPRESS_NONCE_SALT')       ?: 'put your unique phrase here' );

$table_prefix = getenv('WORDPRESS_TABLE_PREFIX') ?: 'wp_';

// 3. Reverse Proxy / SSL Termination Fix
// Essential when running behind AWS ALB, Cloudflare, or an external Nginx proxy.
if ( isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && strpos($_SERVER['HTTP_X_FORWARDED_PROTO'], 'https') !== false ) {
    $_SERVER['HTTPS'] = 'on';
}

// 4. Dynamic Site URL Handling
if ( isset($_SERVER['HTTP_HOST']) ) {
    $http_protocol = ( isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ) ? 'https://' : 'http://';
    define( 'WP_HOME',    $http_protocol . $_SERVER['HTTP_HOST'] );
    define( 'WP_SITEURL', $http_protocol . $_SERVER['HTTP_HOST'] );
}

// 5. Security and Tweak Configurations
define( 'WP_DEBUG',         getenv('WORDPRESS_DEBUG') === 'true' );
define( 'WP_DEBUG_LOG',     getenv('WORDPRESS_DEBUG_LOG') === 'true' );
define( 'WP_DEBUG_DISPLAY', false );
define( 'SCRIPT_DEBUG',     false );

// Disable file editing via the WP Admin Panel (Ensures Container Integrity)
define( 'DISALLOW_FILE_EDIT', true );

// Prevent automatic background core updates (handled by your Docker pipeline instead)
define( 'WP_AUTO_UPDATE_CORE', false );

/** Absolute path to the WordPress directory. */
if ( ! defined( 'ABSOLUTE_PATH' ) ) {
	define( 'ABSOLUTE_PATH', __DIR__ . '/' );
}

/** Sets up WordPress vars and included files. */
require_once ABSOLUTE_PATH . 'wp-settings.php';