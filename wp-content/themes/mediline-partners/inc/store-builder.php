<?php
/**
 * PAP-embeddable Store Builder, secure bootstrap sessions and package generation.
 *
 * @package Mediline_Partners
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

const MEDILINE_BUILDER_COOKIE = 'mediline_builder_session';

function mediline_partners_builder_option( $key, $fallback = '' ) {
	$options = get_option( 'mediline_builder_options', array() );
	return isset( $options[ $key ] ) && '' !== $options[ $key ] ? $options[ $key ] : $fallback;
}

function mediline_partners_builder_bridge_secret() {
	$secret = mediline_partners_builder_option( 'bridge_secret' );
	if ( $secret ) {
		return $secret;
	}
	$secret  = wp_generate_password( 64, false, false );
	$options = get_option( 'mediline_builder_options', array() );
	$options['bridge_secret'] = $secret;
	update_option( 'mediline_builder_options', $options, false );
	return $secret;
}

function mediline_partners_builder_hash( $value ) {
	return hash_hmac( 'sha256', (string) $value, wp_salt( 'auth' ) );
}

function mediline_partners_builder_set_cookie( $value, $expires ) {
	$max_age = max( 0, $expires - time() );
	$cookie  = MEDILINE_BUILDER_COOKIE . '=' . rawurlencode( $value ) . '; Path=/; Max-Age=' . $max_age . '; Secure; HttpOnly; SameSite=None; Partitioned';
	header( 'Set-Cookie: ' . $cookie, false );
}

function mediline_partners_builder_session() {
	if ( is_user_logged_in() && current_user_can( 'edit_theme_options' ) ) {
		$user = wp_get_current_user();
		return array(
			'affiliate_id' => 'wp-admin-' . $user->ID,
			'email'        => $user->user_email,
			'refid'        => 'ADMIN-PREVIEW',
			'csrf'         => wp_create_nonce( 'mediline_builder_admin' ),
			'admin_preview'=> true,
		);
	}

	$cookie = isset( $_COOKIE[ MEDILINE_BUILDER_COOKIE ] ) ? sanitize_text_field( wp_unslash( $_COOKIE[ MEDILINE_BUILDER_COOKIE ] ) ) : '';
	if ( ! $cookie ) {
		return false;
	}
	$session = get_transient( 'mp_builder_session_' . mediline_partners_builder_hash( $cookie ) );
	return is_array( $session ) ? $session : false;
}

function mediline_partners_builder_authorized() {
	return (bool) mediline_partners_builder_session();
}

function mediline_partners_builder_verify_csrf( WP_REST_Request $request ) {
	$session = mediline_partners_builder_session();
	if ( ! $session ) {
		return false;
	}
	$token = (string) $request->get_header( 'x-mediline-csrf' );
	return $token && isset( $session['csrf'] ) && hash_equals( (string) $session['csrf'], $token );
}

function mediline_partners_builder_create_bootstrap( $affiliate ) {
	$token = bin2hex( random_bytes( 32 ) );
	$hash  = mediline_partners_builder_hash( $token );
	set_transient(
		'mp_builder_boot_' . $hash,
		array(
			'affiliate_id' => sanitize_text_field( $affiliate['affiliate_id'] ?? '' ),
			'email'        => sanitize_email( $affiliate['email'] ?? '' ),
			'refid'        => sanitize_text_field( $affiliate['refid'] ?? '' ),
			'created_at'   => time(),
		),
		MINUTE_IN_SECONDS
	);
	return $token;
}

function mediline_partners_builder_claim_bootstrap() {
	if ( ! get_query_var( 'mediline_builder' ) || empty( $_GET['mbt'] ) ) {
		return;
	}

	$token = sanitize_text_field( wp_unslash( $_GET['mbt'] ) );
	$hash  = mediline_partners_builder_hash( $token );
	$data  = get_transient( 'mp_builder_boot_' . $hash );
	delete_transient( 'mp_builder_boot_' . $hash );

	if ( ! is_array( $data ) || empty( $data['affiliate_id'] ) ) {
		wp_safe_redirect( add_query_arg( 'builder_error', 'expired', mediline_partners_builder_url() ) );
		exit;
	}

	$session_token = bin2hex( random_bytes( 32 ) );
	$session_hash  = mediline_partners_builder_hash( $session_token );
	$data['csrf']  = bin2hex( random_bytes( 24 ) );
	$data['expires_at'] = time() + 12 * HOUR_IN_SECONDS;
	set_transient( 'mp_builder_session_' . $session_hash, $data, 12 * HOUR_IN_SECONDS );
	mediline_partners_builder_set_cookie( $session_token, $data['expires_at'] );

	wp_safe_redirect( mediline_partners_builder_url() );
	exit;
}
add_action( 'template_redirect', 'mediline_partners_builder_claim_bootstrap', 2 );

function mediline_partners_builder_url() {
	return home_url( '/store-builder/' );
}

function mediline_partners_builder_register_rest_routes() {
	register_rest_route(
		'mediline/v1',
		'/builder/bootstrap',
		array(
			'methods'             => 'POST',
			'callback'            => 'mediline_partners_builder_rest_bootstrap',
			'permission_callback' => 'mediline_partners_builder_bridge_permission',
		)
	);

	register_rest_route(
		'mediline/v1',
		'/builder/package',
		array(
			'methods'             => 'POST',
			'callback'            => 'mediline_partners_builder_rest_package',
			'permission_callback' => 'mediline_partners_builder_package_permission',
		)
	);

	register_rest_route(
		'mediline/v1',
		'/provision/claim',
		array(
			'methods'             => 'POST',
			'callback'            => 'mediline_partners_builder_rest_provision_claim',
			'permission_callback' => '__return_true',
		)
	);
}
add_action( 'rest_api_init', 'mediline_partners_builder_register_rest_routes' );

function mediline_partners_builder_package_permission() {
	if ( mediline_partners_builder_authorized() ) {
		return true;
	}

	return new WP_Error(
		'builder_session_required',
		'Your Store Builder session is missing or expired. Reopen Store Builder from WordPress admin or your PAP partner panel.',
		array( 'status' => 401 )
	);
}

function mediline_partners_builder_bridge_permission( WP_REST_Request $request ) {
	$provided = (string) $request->get_header( 'x-mediline-bridge-secret' );
	$expected = mediline_partners_builder_bridge_secret();
	return $provided && $expected && hash_equals( $expected, $provided );
}

function mediline_partners_builder_rest_bootstrap( WP_REST_Request $request ) {
	$affiliate_id = sanitize_text_field( (string) $request->get_param( 'affiliate_id' ) );
	if ( ! $affiliate_id ) {
		return new WP_Error( 'missing_affiliate', 'affiliate_id is required.', array( 'status' => 400 ) );
	}
	$token = mediline_partners_builder_create_bootstrap(
		array(
			'affiliate_id' => $affiliate_id,
			'email'        => sanitize_email( (string) $request->get_param( 'email' ) ),
			'refid'        => sanitize_text_field( (string) $request->get_param( 'refid' ) ),
		)
	);
	return rest_ensure_response(
		array(
			'iframe_url' => add_query_arg( 'mbt', rawurlencode( $token ), mediline_partners_builder_url() ),
			'expires_in' => 60,
		)
	);
}

function mediline_partners_builder_encryption_key() {
	return hash( 'sha256', wp_salt( 'secure_auth' ) . 'mediline-builder-v1', true );
}

function mediline_partners_builder_encrypt( $payload ) {
	$plain = wp_json_encode( $payload );
	$key   = mediline_partners_builder_encryption_key();
	if ( function_exists( 'sodium_crypto_secretbox' ) ) {
		$nonce = random_bytes( SODIUM_CRYPTO_SECRETBOX_NONCEBYTES );
		$box   = sodium_crypto_secretbox( $plain, $nonce, $key );
		return 's1:' . base64_encode( $nonce . $box );
	}
	if ( function_exists( 'openssl_encrypt' ) ) {
		$iv  = random_bytes( 12 );
		$tag = '';
		$box = openssl_encrypt( $plain, 'aes-256-gcm', $key, OPENSSL_RAW_DATA, $iv, $tag );
		return 'o1:' . base64_encode( $iv . $tag . $box );
	}
	return false;
}

function mediline_partners_builder_decrypt( $value ) {
	$key = mediline_partners_builder_encryption_key();
	if ( 0 === strpos( $value, 's1:' ) && function_exists( 'sodium_crypto_secretbox_open' ) ) {
		$raw   = base64_decode( substr( $value, 3 ), true );
		$nonce = substr( $raw, 0, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES );
		$box   = substr( $raw, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES );
		$plain = sodium_crypto_secretbox_open( $box, $nonce, $key );
		return $plain ? json_decode( $plain, true ) : false;
	}
	if ( 0 === strpos( $value, 'o1:' ) && function_exists( 'openssl_decrypt' ) ) {
		$raw   = base64_decode( substr( $value, 3 ), true );
		$iv    = substr( $raw, 0, 12 );
		$tag   = substr( $raw, 12, 16 );
		$box   = substr( $raw, 28 );
		$plain = openssl_decrypt( $box, 'aes-256-gcm', $key, OPENSSL_RAW_DATA, $iv, $tag );
		return $plain ? json_decode( $plain, true ) : false;
	}
	return false;
}

function mediline_partners_builder_clean_domain( $domain ) {
	$domain = strtolower( trim( (string) $domain ) );
	$domain = preg_replace( '#^https?://#', '', $domain );
	$domain = trim( $domain, "/ \t\n\r\0\x0B" );
	if ( filter_var( $domain, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4 ) ) { return $domain; }
	if ( '' === $domain || strlen( $domain ) > 253 || preg_match( '/^[0-9.]+$/', $domain ) ) { return ''; }
	foreach ( explode( '.', $domain ) as $label ) {
		if ( strlen( $label ) > 63 || ! preg_match( '/^[a-z0-9](?:[a-z0-9-]*[a-z0-9])?$/', $label ) ) { return ''; }
	}
	return $domain;
}

function mediline_partners_builder_rollback_store( $catalog_credentials ) {
	if ( ! is_array( $catalog_credentials ) || empty( $catalog_credentials['store_id'] ) || ! class_exists( 'Mediline_Catalog_DB' ) ) { return; }
	global $wpdb;
	$wpdb->delete(
		Mediline_Catalog_DB::stores_table(),
		array( 'store_uuid' => sanitize_text_field( $catalog_credentials['store_id'] ) ),
		array( '%s' )
	);
}

function mediline_partners_builder_add_directory_to_zip( ZipArchive $zip, $source_dir, $prefix = '' ) {
	$source_dir = untrailingslashit( $source_dir );
	if ( ! is_dir( $source_dir ) ) { return false; }
	$iterator = new RecursiveIteratorIterator( new RecursiveDirectoryIterator( $source_dir, FilesystemIterator::SKIP_DOTS ), RecursiveIteratorIterator::SELF_FIRST );
	foreach ( $iterator as $item ) {
		$relative = ltrim( str_replace( '\\', '/', substr( $item->getPathname(), strlen( $source_dir ) ) ), '/' );
		$name = trim( $prefix, '/' ) . ( $prefix ? '/' : '' ) . $relative;
		if ( $item->isDir() ) {
			$zip->addEmptyDir( $name );
		} else {
			if ( ! $zip->addFile( $item->getPathname(), $name ) ) { return false; }
		}
	}
	return true;
}

function mediline_partners_builder_add_directory_to_phar( PharData $zip, $source_dir, $prefix = '' ) {
	$source_dir = untrailingslashit( $source_dir );
	if ( ! is_dir( $source_dir ) ) { return false; }
	try {
		$iterator = new RecursiveIteratorIterator( new RecursiveDirectoryIterator( $source_dir, FilesystemIterator::SKIP_DOTS ), RecursiveIteratorIterator::SELF_FIRST );
		foreach ( $iterator as $item ) {
			$relative = ltrim( str_replace( '\\', '/', substr( $item->getPathname(), strlen( $source_dir ) ) ), '/' );
			$name = trim( $prefix, '/' ) . ( $prefix ? '/' : '' ) . $relative;
			if ( $item->isDir() ) {
				$zip->addEmptyDir( $name );
			} else {
				$zip->addFile( $item->getPathname(), $name );
			}
		}
		return true;
	} catch ( Exception $e ) {
		return false;
	}
}

function mediline_partners_builder_generated_packages_dir() {
	$dir = trailingslashit( WP_CONTENT_DIR ) . 'mediline-private-installations';
	if ( ! is_dir( $dir ) ) {
		wp_mkdir_p( $dir );
	}
	if ( is_dir( $dir ) ) {
		@file_put_contents( trailingslashit( $dir ) . 'index.php', "<?php\n// Silence is golden.\n" );
		@file_put_contents( trailingslashit( $dir ) . '.htaccess', "Deny from all\n" );
		@file_put_contents( trailingslashit( $dir ) . 'web.config', '<configuration><system.webServer><authorization><deny users="*" /></authorization></system.webServer></configuration>' );
	}
	return $dir;
}

function mediline_partners_builder_cleanup_generated_packages() {
	$dir = mediline_partners_builder_generated_packages_dir();
	if ( ! is_dir( $dir ) ) {
		return;
	}
	$cutoff = time() - ( 2 * DAY_IN_SECONDS );
	foreach ( glob( trailingslashit( $dir ) . 'mediline-store-*.zip' ) ?: array() as $file ) {
		if ( is_file( $file ) && filemtime( $file ) < $cutoff ) {
			@unlink( $file );
		}
	}
}

function mediline_partners_builder_installer_url( $installation_id, $bootstrap_token, $mode = 'bootstrap' ) {
	return add_query_arg(
		array(
			'mediline_installer' => $mode,
			'i'                  => rawurlencode( $installation_id ),
			't'                  => rawurlencode( $bootstrap_token ),
		),
		home_url( '/' )
	);
}

function mediline_partners_builder_validate_install_access( $installation_id, $bootstrap_token ) {
	$installation_id = sanitize_text_field( (string) $installation_id );
	$bootstrap_token  = sanitize_text_field( (string) $bootstrap_token );
	if ( ! $installation_id || ! $bootstrap_token ) {
		return new WP_Error( 'installer_access_missing', 'Installation access token is missing.', array( 'status' => 400 ) );
	}
	$key  = 'mp_install_' . mediline_partners_builder_hash( $installation_id );
	$data = get_transient( $key );
	if ( ! is_array( $data ) || empty( $data['bootstrap_hash'] ) || ! hash_equals( $data['bootstrap_hash'], mediline_partners_builder_hash( $bootstrap_token ) ) || (int) ( $data['bootstrap_expires_at'] ?? 0 ) < time() ) {
		return new WP_Error( 'installer_access_invalid', 'Installation command is invalid or expired.', array( 'status' => 401 ) );
	}
	return array( $key, $data );
}

function mediline_partners_builder_bootstrap_shell( $installation_id, $bootstrap_token ) {
	$package_url = mediline_partners_builder_installer_url( $installation_id, $bootstrap_token, 'package' );
	$install_dir = '/opt/mediline-store';
	$script = <<<'BASH'
#!/usr/bin/env bash
set -Eeuo pipefail

GREEN='\033[0;32m'; YELLOW='\033[1;33m'; RED='\033[0;31m'; NC='\033[0m'
info(){ printf "%b\n" "${GREEN}✓${NC} $*"; }
warn(){ printf "%b\n" "${YELLOW}!${NC} $*"; }
die(){ printf "%b\n" "${RED}✕${NC} $*" >&2; exit 1; }

[ "${EUID:-$(id -u)}" -eq 0 ] || die "Run this command with sudo/root privileges."

PACKAGE_URL='__PACKAGE_URL__'
INSTALL_DIR="${MEDILINE_INSTALL_DIR:-__INSTALL_DIR__}"
ARCHIVE="$(mktemp /tmp/mediline-store.XXXXXX.zip)"
trap 'rm -f "$ARCHIVE"' EXIT

install_base_tools(){
  if command -v apt-get >/dev/null 2>&1; then
    export DEBIAN_FRONTEND=noninteractive
    apt-get update -y >/dev/null
    apt-get install -y ca-certificates curl unzip >/dev/null
  elif command -v dnf >/dev/null 2>&1; then
    dnf install -y ca-certificates curl unzip >/dev/null
  elif command -v yum >/dev/null 2>&1; then
    yum install -y ca-certificates curl unzip >/dev/null
  else
    command -v curl >/dev/null 2>&1 || die "curl is required."
    command -v unzip >/dev/null 2>&1 || die "unzip is required. Install it and run the command again."
  fi
}

start_docker(){
  docker info >/dev/null 2>&1 && return
  if command -v systemctl >/dev/null 2>&1; then
    systemctl enable --now docker >/dev/null 2>&1 || true
  fi
  if ! docker info >/dev/null 2>&1 && command -v service >/dev/null 2>&1; then
    service docker start >/dev/null 2>&1 || true
  fi
  for _ in $(seq 1 30); do
    docker info >/dev/null 2>&1 && return
    sleep 2
  done
  return 1
}

install_docker(){
  if ! command -v docker >/dev/null 2>&1 || ! docker compose version >/dev/null 2>&1; then
  info "Installing Docker Engine and Compose…"
    curl -fsSL https://get.docker.com -o /tmp/mediline-get-docker.sh
    sh /tmp/mediline-get-docker.sh >/dev/null
    rm -f /tmp/mediline-get-docker.sh
  fi
  start_docker || die "Docker daemon could not be started."
}

info "Preparing server…"
install_base_tools
install_docker
command -v docker >/dev/null 2>&1 || die "Docker installation failed."
docker info >/dev/null 2>&1 || die "Docker daemon is not available."
docker compose version >/dev/null 2>&1 || die "Docker Compose plugin is unavailable."

if [ -f "$INSTALL_DIR/.mediline-installed" ]; then
  info "Mediline Store is already installed in $INSTALL_DIR."
  cat "$INSTALL_DIR/.mediline-installed"
  exit 0
fi

info "Downloading your Mediline Store package…"
curl -fL --retry 4 --retry-delay 2 --connect-timeout 20 "$PACKAGE_URL" -o "$ARCHIVE"
[ -s "$ARCHIVE" ] || die "Downloaded package is empty. Generate a fresh installation command."

mkdir -p "$INSTALL_DIR"
if [ -n "$(find "$INSTALL_DIR" -mindepth 1 -maxdepth 1 -print -quit 2>/dev/null)" ] && [ ! -f "$INSTALL_DIR/.mediline-runtime/installation.json" ]; then
  warn "$INSTALL_DIR is not empty; preserving existing files and overwriting installer files only."
fi
unzip -oq "$ARCHIVE" -d "$INSTALL_DIR"
[ -f "$INSTALL_DIR/install.sh" ] || die "install.sh was not found in the downloaded package."

# Self-heal packages generated by installer <= 1.1.0. The official
# wordpress:cli image expects WP-CLI to be its container entrypoint. Older
# packages omitted it, so Docker tried to execute `core`, `theme`, etc. as
# Linux binaries instead of `wp core`, `wp theme`, ... .
if [ -f "$INSTALL_DIR/docker-compose.yml" ] && grep -q 'image:[[:space:]]*wordpress:cli' "$INSTALL_DIR/docker-compose.yml" && ! grep -A3 'image:[[:space:]]*wordpress:cli' "$INSTALL_DIR/docker-compose.yml" | grep -q 'entrypoint:[[:space:]]*\["wp"\]'; then
  sed -i '/image:[[:space:]]*wordpress:cli/a\    entrypoint: ["wp"]' "$INSTALL_DIR/docker-compose.yml"
  info "Applied WP-CLI compatibility fix to the downloaded package."
fi

chmod +x "$INSTALL_DIR/install.sh" "$INSTALL_DIR/status.sh" "$INSTALL_DIR/update.sh" 2>/dev/null || true

info "Starting unattended Mediline Store installation…"
cd "$INSTALL_DIR"
bash ./install.sh
BASH;
	return str_replace(
		array( '__PACKAGE_URL__', '__INSTALL_DIR__' ),
		array( esc_url_raw( $package_url ), $install_dir ),
		$script
	);
}

function mediline_partners_builder_installer_dispatch() {
	if ( empty( $_GET['mediline_installer'] ) ) {
		return;
	}
	$mode            = sanitize_key( wp_unslash( $_GET['mediline_installer'] ) );
	$installation_id = isset( $_GET['i'] ) ? sanitize_text_field( wp_unslash( $_GET['i'] ) ) : '';
	$bootstrap_token  = isset( $_GET['t'] ) ? sanitize_text_field( wp_unslash( $_GET['t'] ) ) : '';
	$access           = mediline_partners_builder_validate_install_access( $installation_id, $bootstrap_token );
	if ( is_wp_error( $access ) ) {
		$error_data = $access->get_error_data();
		$status = is_array( $error_data ) && isset( $error_data['status'] ) ? (int) $error_data['status'] : 401;
		status_header( $status );
		header( 'Content-Type: text/plain; charset=utf-8' );
		echo esc_html( $access->get_error_message() );
		exit;
	}
	list( $key, $data ) = $access;

	if ( 'bootstrap' === $mode ) {
		nocache_headers();
		header( 'Content-Type: text/x-shellscript; charset=utf-8' );
		header( 'Content-Disposition: inline; filename="mediline-install.sh"' );
		echo mediline_partners_builder_bootstrap_shell( $installation_id, $bootstrap_token ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		exit;
	}

	if ( 'package' === $mode ) {
		$file = $data['package_path'] ?? '';
		if ( ! $file || ! is_file( $file ) ) {
			status_header( 404 );
			header( 'Content-Type: text/plain; charset=utf-8' );
			exit( 'Installation package not found. Generate a fresh command.' );
		}
		nocache_headers();
		header( 'Content-Type: application/zip' );
		header( 'Content-Disposition: attachment; filename="mediline-store-' . sanitize_file_name( $installation_id ) . '.zip"' );
		header( 'Content-Length: ' . filesize( $file ) );
		readfile( $file ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_readfile
		exit;
	}

	status_header( 404 );
	exit( 'Unknown installer action.' );
}
add_action( 'template_redirect', 'mediline_partners_builder_installer_dispatch', -10 );

function mediline_partners_builder_build_install_package( $zip_path, $theme_zip, $manifest ) {
	$installer_dir = MEDILINE_PARTNERS_DIR . '/bundles/store-installer';
	$core_bundle   = MEDILINE_PARTNERS_DIR . '/bundles/mediline-store-core.zip';
	if ( ! is_dir( $installer_dir ) || ! file_exists( $core_bundle ) || ! file_exists( $theme_zip ) ) { return false; }

	if ( class_exists( 'ZipArchive' ) ) {
		$zip = new ZipArchive();
		if ( true !== $zip->open( $zip_path, ZipArchive::CREATE | ZipArchive::OVERWRITE ) ) { return false; }
		$result = mediline_partners_builder_add_directory_to_zip( $zip, $installer_dir );
		$result = $result && $zip->addFile( $theme_zip, 'packages/storefront-theme.zip' );
		$result = $result && $zip->addFile( $core_bundle, 'packages/mediline-store-core.zip' );
		$result = $result && $zip->addFromString( 'mediline-installation.json', wp_json_encode( $manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES ) );
		if ( $result && method_exists( $zip, 'setExternalAttributesName' ) ) {
			foreach ( array( 'install.sh', 'update.sh', 'status.sh', 'installer/bootstrap.py', 'installer/runtime.py' ) as $executable ) {
				$zip->setExternalAttributesName( $executable, ZipArchive::OPSYS_UNIX, 0100755 << 16 );
			}
		}
		$zip->close();
		return (bool) $result;
	}

	/*
	 * PharData can write ZIP archives even when the optional ext-zip module is
	 * unavailable. This keeps Store Builder usable on common managed hosts.
	 * Generated installers are invoked with `bash install.sh`, so executable ZIP
	 * attributes are not required in this fallback path.
	 */
	if ( class_exists( 'PharData' ) ) {
		try {
			@unlink( $zip_path );
			$zip = new PharData( $zip_path );
			$result = mediline_partners_builder_add_directory_to_phar( $zip, $installer_dir );
			if ( $result ) {
				$zip->addFile( $theme_zip, 'packages/storefront-theme.zip' );
				$zip->addFile( $core_bundle, 'packages/mediline-store-core.zip' );
				$zip->addFromString( 'mediline-installation.json', wp_json_encode( $manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES ) );
			}
			unset( $zip );
			return $result && file_exists( $zip_path ) && filesize( $zip_path ) > 0;
		} catch ( Exception $e ) {
			@unlink( $zip_path );
			return false;
		}
	}

	return false;
}

function mediline_partners_builder_rest_package( WP_REST_Request $request ) {
	if ( ! mediline_partners_builder_verify_csrf( $request ) ) {
		return new WP_Error( 'builder_csrf', 'Invalid builder session token.', array( 'status' => 403 ) );
	}

	$template_id = absint( $request->get_param( 'template_id' ) );
	$post        = get_post( $template_id );
	if ( ! $post || 'mp_store_template' !== $post->post_type || 'publish' !== $post->post_status ) {
		return new WP_Error( 'template_missing', 'Template is unavailable.', array( 'status' => 404 ) );
	}
	$package = mediline_partners_template_package( $template_id );
	if ( empty( $package['file'] ) || ! file_exists( $package['file'] ) ) {
		return new WP_Error( 'package_missing', 'This template has no installation ZIP yet.', array( 'status' => 409 ) );
	}
	if ( ! class_exists( 'ZipArchive' ) && ! class_exists( 'PharData' ) ) {
		return new WP_Error( 'zip_unavailable', 'The server needs ZipArchive or PharData support to build one-command storefront packages.', array( 'status' => 500 ) );
	}

	$available_languages = array_keys( mediline_partners_languages() );
	$primary_language    = sanitize_key( (string) $request->get_param( 'primary_language' ) );
	if ( ! in_array( $primary_language, $available_languages, true ) ) {
		$primary_language = mediline_partners_default_language();
	}
	$languages = $request->get_param( 'languages' );
	$languages = is_array( $languages ) ? array_values( array_intersect( $available_languages, array_map( 'sanitize_key', $languages ) ) ) : array();
	if ( ! in_array( $primary_language, $languages, true ) ) {
		array_unshift( $languages, $primary_language );
	}

	$domain = mediline_partners_builder_clean_domain( $request->get_param( 'domain' ) );
	if ( ! $domain ) {
		return new WP_Error( 'invalid_domain', 'Enter a valid domain.', array( 'status' => 400 ) );
	}
	$admin_email = sanitize_email( (string) $request->get_param( 'admin_email' ) );
	if ( ! is_email( $admin_email ) ) {
		return new WP_Error( 'invalid_email', 'Enter a valid WordPress admin email.', array( 'status' => 400 ) );
	}
	$admin_user = sanitize_user( (string) $request->get_param( 'admin_username' ), true );
	if ( ! $admin_user ) {
		return new WP_Error( 'invalid_username', 'Enter a valid WordPress admin username.', array( 'status' => 400 ) );
	}
	$admin_password = (string) $request->get_param( 'admin_password' );
	if ( strlen( $admin_password ) < 10 || preg_match( '/[\r\n]/', $admin_password ) ) {
		return new WP_Error( 'weak_password', 'Use an admin password with at least 10 characters and no line breaks.', array( 'status' => 400 ) );
	}
	$store_name = sanitize_text_field( (string) $request->get_param( 'store_name' ) );
	if ( '' === $store_name ) {
		return new WP_Error( 'invalid_store_name', 'Enter a store name.', array( 'status' => 400 ) );
	}
	$region = strtoupper( sanitize_key( (string) $request->get_param( 'region' ) ) );
	$supported_markets = function_exists( 'mediline_catalog_supported_markets' ) ? mediline_catalog_supported_markets() : array( 'EU', 'FR', 'DE', 'IT', 'ES', 'US' );
	if ( ! in_array( $region, $supported_markets, true ) ) {
		return new WP_Error( 'invalid_region', 'Choose a supported storefront region.', array( 'status' => 400 ) );
	}

	$session         = mediline_partners_builder_session();
	$installation_id = 'inst_' . wp_generate_password( 18, false, false );
	$install_token   = bin2hex( random_bytes( 32 ) );
	$download_token  = bin2hex( random_bytes( 24 ) );
	$bootstrap_token = bin2hex( random_bytes( 32 ) );
	if ( ! function_exists( 'mediline_catalog_register_store' ) ) {
		return new WP_Error( 'catalog_core_missing', 'Mediline Catalog Core must be active before generating storefront packages.', array( 'status' => 503 ) );
	}

	$catalog_credentials = mediline_catalog_register_store(
		array(
			'installation_id'  => $installation_id,
			'template_key'     => sanitize_title( get_the_title( $template_id ) ),
			'template_version' => $package['version'],
			'affiliate_id'     => sanitize_text_field( $session['affiliate_id'] ?? '' ),
			'affiliate_refid'  => sanitize_text_field( $session['refid'] ?? '' ),
			'domain'           => $domain,
			'market'           => $region,
			'currency'         => sanitize_text_field( (string) $request->get_param( 'currency' ) ),
			'primary_language' => $primary_language,
			'languages'        => $languages,
		)
	);
	if ( is_wp_error( $catalog_credentials ) ) {
		return $catalog_credentials;
	}

	$integration_settings = function_exists( 'mediline_integrations_settings' ) ? mediline_integrations_settings() : array();
	$pap_tracking_url = ! empty( $integration_settings['pap_click_enabled'] ) ? esc_url_raw( (string) ( $integration_settings['pap_click_script_url'] ?? '' ), array( 'https' ) ) : '';
	$pap_account_id = preg_replace( '/[^A-Za-z0-9_-]/', '', (string) ( $integration_settings['pap_account_id'] ?? 'default1' ) );
	$config = array(
		'installation_id' => $installation_id,
		'affiliate_id'    => sanitize_text_field( $session['affiliate_id'] ?? '' ),
		'affiliate_refid' => sanitize_text_field( $session['refid'] ?? '' ),
		'template_id'     => $template_id,
		'template_name'   => get_the_title( $template_id ),
		'template_version'=> $package['version'],
		'store_name'      => $store_name,
		'domain'          => $domain,
		'primary_language'=> $primary_language,
		'languages'       => $languages,
		'region'          => $region,
		'currency'        => sanitize_text_field( (string) $request->get_param( 'currency' ) ),
		'admin_email'     => $admin_email,
		'admin_username'  => $admin_user,
		'admin_password'  => $admin_password,
		'catalog'         => array(
			'api_url'          => esc_url_raw( $catalog_credentials['api_url'] ?? '' ),
			'store_id'         => sanitize_text_field( $catalog_credentials['store_id'] ?? '' ),
			'store_secret'     => sanitize_text_field( $catalog_credentials['store_secret'] ?? '' ),
			'primary_language' => $primary_language,
			'languages'        => $languages,
			'pap_tracking_script_url' => $pap_tracking_url,
			'pap_account_id'          => substr( $pap_account_id ?: 'default1', 0, 64 ),
		),
		'created_at'      => gmdate( 'c' ),
	);
	$encrypted = mediline_partners_builder_encrypt( $config );
	if ( ! $encrypted ) {
		mediline_partners_builder_rollback_store( $catalog_credentials );
		return new WP_Error( 'encryption_unavailable', 'Server encryption support is required.', array( 'status' => 500 ) );
	}

	mediline_partners_builder_cleanup_generated_packages();
	$generated_dir = mediline_partners_builder_generated_packages_dir();
	if ( ! is_dir( $generated_dir ) || ! is_writable( $generated_dir ) ) {
		mediline_partners_builder_rollback_store( $catalog_credentials );
		return new WP_Error( 'package_storage_unavailable', 'Private installation package storage is not writable.', array( 'status' => 500 ) );
	}
	$tmp = trailingslashit( $generated_dir ) . 'mediline-store-' . sanitize_file_name( $installation_id ) . '-' . bin2hex( random_bytes( 16 ) ) . '.zip';
	$manifest = array(
		'installation_id' => $installation_id,
		'token'           => $install_token,
		'provision_url'   => rest_url( 'mediline/v1/provision/claim' ),
		'generated_at'    => gmdate( 'c' ),
		'installer_version'=> '1.1.5',
	);
	if ( ! mediline_partners_builder_build_install_package( $tmp, $package['file'], $manifest ) ) {
		@unlink( $tmp );
		mediline_partners_builder_rollback_store( $catalog_credentials );
		return new WP_Error( 'package_build_failed', 'Could not build the Mediline one-command storefront package.', array( 'status' => 500 ) );
	}

	$transient_saved = set_transient(
		'mp_install_' . mediline_partners_builder_hash( $installation_id ),
		array(
			'token_hash'     => mediline_partners_builder_hash( $install_token ),
			'download_hash'  => mediline_partners_builder_hash( $download_token ),
			'bootstrap_hash' => mediline_partners_builder_hash( $bootstrap_token ),
			'bootstrap_expires_at' => time() + HOUR_IN_SECONDS,
			'affiliate_id'   => $config['affiliate_id'],
			'config'         => $encrypted,
			'package_path'   => $tmp,
			'provision_used' => false,
			'expires_at'     => time() + DAY_IN_SECONDS,
		),
		DAY_IN_SECONDS
	);
	if ( ! $transient_saved ) {
		@unlink( $tmp );
		mediline_partners_builder_rollback_store( $catalog_credentials );
		return new WP_Error( 'installation_state_failed', 'Could not save the one-command installation state.', array( 'status' => 500 ) );
	}

	$bootstrap_url = mediline_partners_builder_installer_url( $installation_id, $bootstrap_token, 'bootstrap' );
	$install_command = "curl -fsSL '" . esc_url_raw( $bootstrap_url ) . "' | sudo bash";

	return rest_ensure_response(
		array(
			'installation_id' => $installation_id,
			'install_command' => $install_command,
			'bootstrap_url'   => $bootstrap_url,
			'download_url'    => add_query_arg(
				array( 'mediline_download' => rawurlencode( $installation_id ), 'd' => rawurlencode( $download_token ) ),
				home_url( '/' )
			),
			'expires_in'      => DAY_IN_SECONDS,
			'command_expires_in' => HOUR_IN_SECONDS,
		)
	);
}

function mediline_partners_builder_download() {
	if ( empty( $_GET['mediline_download'] ) || empty( $_GET['d'] ) ) {
		return;
	}
	$session = mediline_partners_builder_session();
	if ( ! $session ) {
		status_header( 401 );
		exit( 'Unauthorized' );
	}
	$installation_id = sanitize_text_field( wp_unslash( $_GET['mediline_download'] ) );
	$download_token  = sanitize_text_field( wp_unslash( $_GET['d'] ) );
	$key              = 'mp_install_' . mediline_partners_builder_hash( $installation_id );
	$data             = get_transient( $key );
	if ( ! is_array( $data ) || empty( $data['download_hash'] ) || ! hash_equals( $data['download_hash'], mediline_partners_builder_hash( $download_token ) ) || (string) $data['affiliate_id'] !== (string) ( $session['affiliate_id'] ?? '' ) ) {
		status_header( 403 );
		exit( 'Invalid or expired download.' );
	}
	$file = $data['package_path'] ?? '';
	if ( ! $file || ! file_exists( $file ) ) {
		status_header( 404 );
		exit( 'Package not found.' );
	}

	nocache_headers();
	header( 'Content-Type: application/zip' );
	header( 'Content-Disposition: attachment; filename="mediline-store-' . sanitize_file_name( $installation_id ) . '.zip"' );
	header( 'Content-Length: ' . filesize( $file ) );
	readfile( $file ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_readfile
	exit;
}
add_action( 'template_redirect', 'mediline_partners_builder_download', 0 );

function mediline_partners_builder_rest_provision_claim( WP_REST_Request $request ) {
	$installation_id = sanitize_text_field( (string) $request->get_param( 'installation_id' ) );
	$token           = sanitize_text_field( (string) $request->get_param( 'token' ) );
	if ( ! $installation_id || ! $token ) {
		return new WP_Error( 'missing_claim', 'installation_id and token are required.', array( 'status' => 400 ) );
	}
	$key  = 'mp_install_' . mediline_partners_builder_hash( $installation_id );
	$data = get_transient( $key );
	if ( ! is_array( $data ) || empty( $data['token_hash'] ) || ! hash_equals( $data['token_hash'], mediline_partners_builder_hash( $token ) ) || ! empty( $data['provision_used'] ) || (int) ( $data['expires_at'] ?? 0 ) < time() ) {
		return new WP_Error( 'invalid_claim', 'Installation token is invalid, used or expired.', array( 'status' => 401 ) );
	}
	$config = mediline_partners_builder_decrypt( $data['config'] ?? '' );
	if ( ! is_array( $config ) ) {
		return new WP_Error( 'claim_decrypt', 'Could not decrypt installation configuration.', array( 'status' => 500 ) );
	}
	$data['provision_used'] = true;
	$data['config']         = '';
	/* Keep the generated package/bootstrap URL alive briefly so the same one-line
	 * command can safely be re-run on the same server after an interrupted install.
	 * The provisioning token itself is already single-use, and the local runtime
	 * configuration is reused by install.sh. */
	$consumed = set_transient( $key, $data, max( MINUTE_IN_SECONDS, (int) $data['expires_at'] - time() ) );
	if ( ! $consumed ) {
		delete_transient( $key );
		return new WP_Error( 'claim_state_failed', 'Could not securely consume the one-time installation token.', array( 'status' => 500 ) );
	}
	return rest_ensure_response( array( 'installation' => $config ) );
}

function mediline_partners_builder_admin_menu() {
	add_theme_page( 'Mediline Store Builder', 'Store Builder', 'edit_theme_options', 'mediline-store-builder', 'mediline_partners_builder_admin_page' );
}
add_action( 'admin_menu', 'mediline_partners_builder_admin_menu' );

function mediline_partners_builder_security_headers() {
	$is_builder = get_query_var( 'mediline_builder' );
	$is_bridge  = get_query_var( 'mediline_pap_builder_bridge' );
	if ( ! $is_builder && ! $is_bridge ) {
		return;
	}
	if ( $is_bridge ) {
		header( "Content-Security-Policy: default-src 'none'; script-src 'self'; style-src 'self'; connect-src 'self'; img-src 'self' data:; frame-ancestors 'self' https://mediline.postaffiliatepro.com; base-uri 'none'; form-action 'none'" );
	} else {
		header( "Content-Security-Policy: frame-ancestors 'self' https://mediline.postaffiliatepro.com" );
	}
	header( 'Referrer-Policy: no-referrer' );
	header( 'X-Content-Type-Options: nosniff' );
	header( 'X-Robots-Tag: noindex, nofollow, noarchive' );
	header( 'Permissions-Policy: camera=(), microphone=(), geolocation=(), payment=()' );
}
add_action( 'send_headers', 'mediline_partners_builder_security_headers' );

function mediline_partners_builder_admin_page() {
	if ( ! current_user_can( 'edit_theme_options' ) ) {
		return;
	}
	$secret = mediline_partners_builder_bridge_secret();
	?>
	<div class="wrap">
		<h1>Mediline Store Builder</h1>
		<p>The public Builder UI is protected. WordPress administrators can preview it directly; PAP access should call the bootstrap endpoint server-to-server and use the returned one-time iframe URL.</p>
		<table class="widefat striped" style="max-width:980px"><tbody>
		<tr><th>Builder preview</th><td><a class="button button-primary" target="_blank" href="<?php echo esc_url( mediline_partners_builder_url() ); ?>">Open Store Builder</a></td></tr>
		<tr><th>PAP URL page</th><td><code style="word-break:break-all"><?php echo esc_html( mediline_partners_pap_builder_bridge_url() . '#session={$session}' ); ?></code><p class="description">Use this template in PAP Configuration &gt; Affiliate panel &gt; Menu &amp; screens, and open it in Page. Affiliate identity and refid are resolved server-to-server.</p></td></tr>
		<tr><th>PAP API client</th><td><?php echo is_readable( MEDILINE_PARTNERS_DIR . '/vendor/pap/PapApi.class.php' ) ? '<strong style="color:#16794b">Ready</strong> &mdash; affiliate sessions are verified server-to-server.' : '<strong style="color:#b32d2e">Missing</strong>'; ?></td></tr>
		<tr><th>Bootstrap endpoint</th><td><code><?php echo esc_html( rest_url( 'mediline/v1/builder/bootstrap' ) ); ?></code></td></tr>
		<tr><th>Provision endpoint</th><td><code><?php echo esc_html( rest_url( 'mediline/v1/provision/claim' ) ); ?></code></td></tr>
		<tr><th>Catalog Core</th><td><?php if ( function_exists( 'mediline_catalog_register_store' ) ) : ?><strong style="color:#16794b">Connected</strong> — generated packages receive a store ID, secret and Catalog API URL.<?php else : ?><strong style="color:#b32d2e">Not active</strong> — install and activate <code>Mediline Catalog Core</code> before generating packages.<?php endif; ?></td></tr>
		<tr><th>Universal installer</th><td><?php echo is_dir( MEDILINE_PARTNERS_DIR . '/bundles/store-installer' ) ? '<strong style="color:#16794b">Ready</strong> — generated downloads include <code>install.sh</code>, Docker Compose and Caddy.' : '<strong style="color:#b32d2e">Missing</strong>'; ?></td></tr>
		<tr><th>Store Core bundle</th><td><?php echo file_exists( MEDILINE_PARTNERS_DIR . '/bundles/mediline-store-core.zip' ) ? '<strong style="color:#16794b">Bundled</strong> as <code>packages/mediline-store-core.zip</code>' : '<strong style="color:#b32d2e">Missing</strong>'; ?></td></tr>
		<tr><th>Bridge secret</th><td><code style="word-break:break-all"><?php echo esc_html( $secret ); ?></code><p class="description">Keep this server-side only. Never put it in PAP browser JavaScript.</p></td></tr>
		</tbody></table>
	</div>
	<?php
}
