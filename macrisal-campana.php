<?php
/**
 * Plugin Name: Macrisal Campaña de captación
 * Description: Formulario de campaña ([macrisal_campana]), guarda los datos en una tabla propia, envía al usuario un correo personalizable con un PDF y muestra los registros en el escritorio.
 * Version: 1.1.0
 * Author: Macrisal
 * Text Domain: macrisal-campana
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'MCAMP_VERSION', '1.1.0' );
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
		'pdf_id'       => 0,
		'from_name'    => 'Macrisal',
		'from_email'   => '',
		'subject'      => 'Aquí tienes tu documento de Macrisal',
		'body'         => "<p>Hola {nombre},</p>\n<p>Gracias por tu interés en Macrisal. Te adjuntamos el documento que has solicitado.</p>\n<p>Si tienes cualquier duda, puedes llamarnos al <strong>+34 93 300 50 55</strong> o responder a este correo.</p>\n<p>Un saludo,<br>El equipo de Macrisal</p>",
		'notify_email' => '',
		'success_msg'  => '¡Gracias! Te hemos enviado un correo con la información.',
		'button_text'  => 'Enviar',
		'legal_text'   => mcamp_default_legal_text(),
	);

	$saved = get_option( MCAMP_OPTION, array() );

	return wp_parse_args( is_array( $saved ) ? $saved : array(), $defaults );
}

/**
 * Texto legal estándar (el mismo del formulario de contacto de la web).
 */
function mcamp_default_legal_text() {
	return '<ol>
<li><strong>Responsable de los datos</strong>: FINESTRES MACRISAL, S.L.</li>
<li><strong>Finalidad</strong>: Gestionar las solicitudes de información que se realizan a través de la página web.</li>
<li><strong>Legitimación</strong>: Consentimiento del interesado</li>
<li><strong>Destinatarios</strong>: No se realizarán cesiones de datos.</li>
<li><strong>Derechos</strong>: Acceder, rectificar y suprimir los datos, portabilidad de los datos, limitación u oposición a su tratamiento, transparencia y derecho a no ser objeto de decisiones automatizadas, como se explica en la información adicional.</li>
<li><strong>Información adicional</strong>: Puede consultar la información adicional y detallada sobre Protección de Datos en el siguiente <a href="https://www.macrisal.com/politica-de-privacidad/">link</a>.</li>
</ol>';
}
