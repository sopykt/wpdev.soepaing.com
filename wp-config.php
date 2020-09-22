<?php
define( 'DB_NAME', 'wpdev' );

define( 'DB_USER', 'wpdevuser' );

define( 'DB_PASSWORD', '014369929&sOE' );

define( 'DB_HOST', 'localhost' );

define( 'DB_CHARSET', 'utf8' );

define( 'DB_COLLATE', '' );

define('FS_METHOD', 'direct');

define('AUTH_KEY',         '2JxaX:r{D;|_29otB+ BdR=D>euz.X5)1|^.,0q#?*Jr7xg|F3%6pRFEF$`7X!x!');
define('SECURE_AUTH_KEY',  ':J@:^y0-pPsEw)L6xt9Raq]_b??u%a^n);1Hk3v6-*QeEuGG,r70!g`#E]knMBBA');
define('LOGGED_IN_KEY',    'aEC.>eg``QY?3|4BWG.~-aQOf`XeyfVp(gvN6~6flQhHwp.[5W`vq 4EJt]Zyf9K');
define('NONCE_KEY',        '_6s(^V@i|-}5ZI^YG1s}2FG^r.2Q$@- zcj2TmSJ^yh=oN3|:,|;YxgnWsIAd:v0');
define('AUTH_SALT',        'V-EG@Mws7{K-#dzcn~o.OO}@5Pd7vK1F-@YZB0[xkU.g}QN2#d5Vwas,iH`J0D1a');
define('SECURE_AUTH_SALT', '7(yjS}Dm2=]ke)y^7n981rX*s`8*f)|zv,Umo,y3ndN}Jmo#pgN0S[TX>1@6~d&v');
define('LOGGED_IN_SALT',   '$4~So=7ehhq!}4o-xkk{}L9f^UJ]qGBCF!A!8dFsS_]<nR2a4bJd|Uw+xVik7C&4');
define('NONCE_SALT',       'p&&7#]d&N+]3p~i3}>zoP%-@XR6Gwy,X<S c/Wl$jE7P;5.=X6e]^g)G{py:&6Pa');

$table_prefix = 'wp_';

define( 'WP_DEBUG', true);

if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', __DIR__ . '/' );
}

require_once ABSPATH . 'wp-settings.php';
