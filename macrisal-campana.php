<?php
/**
 * Plugin Name: Macrisal Campaña de captación
 * Description: Formulario de campaña ([macrisal_campana]), guarda los datos en una tabla propia, envía al usuario un correo personalizable con un PDF y muestra los registros en el escritorio.
 * Version: 1.3.0
 * Author: Macrisal
 * Text Domain: macrisal-campana
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'MCAMP_VERSION', '1.3.0' );
define( 'MCAMP_DB_VERSION', '1' );
define( 'MCAMP_DIR', plugin_dir_path( __FILE__ ) );
define( 'MCAMP_URL', plugin_dir_url( __FILE__ ) );
define( 'MCAMP_OPTION', 'mcamp_settings' );

require_once MCAMP_DIR . 'includes/class-mcamp-db.php';
require_once MCAMP_DIR . 'includes/class-mcamp-mailer.php';
require_once MCAMP_DIR . 'includes/class-mcamp-form.php';

if ( is_admin() ) {
	require_once MCAMP_DIR . 'includes/class-mcamp-admin.php';
}

register_activation_hook( __FILE__, array( 'MCamp_DB', 'install' ) );

add_action( 'plugins_loaded', array( 'MCamp_DB', 'maybe_upgrade' ) );
add_action( 'init', array( 'MCamp_Form', 'init' ) );

if ( is_admin() ) {
	add_action( 'init', array( 'MCamp_Admin', 'init' ) );
}

/**
 * Ajustes con valores por defecto.
 */
function mcamp_settings() {
	$defaults = array(
		'pdf_id'           => 0,
		'from_name'        => 'Macrisal',
		'from_email'       => '',
		'subject'          => 'Aquí tienes tu documento de Macrisal',
		'body'             => "<p>Hola {nombre},</p>\n<p>Gracias por tu interés en Macrisal. Te adjuntamos el documento que has solicitado.</p>\n<p>Si tienes cualquier duda, puedes llamarnos al <strong>+34 93 300 50 55</strong> o responder a este correo.</p>\n<p>Un saludo,<br>El equipo de Macrisal</p>",
		'notify_email'     => '',
		'success_msg'      => '¡Gracias! Te hemos enviado un correo con la información.',
		'button_text'      => 'Enviar',
		'recaptcha_site'   => '',
		'recaptcha_secret' => '',
		'hide_menu'        => 1,
	);

	$saved = get_option( MCAMP_OPTION, array() );

	return wp_parse_args( is_array( $saved ) ? $saved : array(), $defaults );
}
