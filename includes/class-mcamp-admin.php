<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'WP_List_Table' ) ) {
	require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
}

/**
 * Sección del escritorio: listado de registros y ajustes del correo/PDF.
 */
class MCamp_Admin {

	const CAP  = 'manage_options';
	const SLUG = 'macrisal-campana';

	public static function init() {
		add_action( 'admin_menu', array( __CLASS__, 'menu' ) );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'assets' ) );
		add_action( 'admin_post_mcamp_save_settings', array( __CLASS__, 'save_settings' ) );
		add_action( 'admin_post_mcamp_export', array( __CLASS__, 'export_csv' ) );
		add_action( 'admin_post_mcamp_resend', array( __CLASS__, 'resend' ) );
		add_action( 'admin_post_mcamp_test_mail', array( __CLASS__, 'test_mail' ) );
	}

	public static function menu() {
		add_menu_page(
			'Campaña de captación',
			'Campaña captación',
			self::CAP,
			self::SLUG,
			array( __CLASS__, 'render' ),
			'dashicons-megaphone',
			26
		);
	}

	public static function assets( $hook ) {
		if ( 'toplevel_page_' . self::SLUG !== $hook ) {
			return;
		}
		wp_enqueue_media();
		wp_enqueue_script( 'mcamp-admin', MCAMP_URL . 'assets/admin.js', array( 'jquery' ), MCAMP_VERSION, true );
	}

	private static function url( $args = array() ) {
		return add_query_arg( $args, admin_url( 'admin.php?page=' . self::SLUG ) );
	}

	public static function render() {
		if ( ! current_user_can( self::CAP ) ) {
			return;
		}
		$tab = isset( $_GET['tab'] ) && 'ajustes' === $_GET['tab'] ? 'ajustes' : 'registros';
		?>
		<div class="wrap">
			<h1 class="wp-heading-inline">Campaña de captación</h1>
			<?php if ( 'registros' === $tab ) : ?>
				<a href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=mcamp_export' ), 'mcamp_export' ) ); ?>" class="page-title-action">Exportar CSV</a>
			<?php endif; ?>
			<hr class="wp-header-end">

			<?php self::notices(); ?>

			<nav class="nav-tab-wrapper">
				<a href="<?php echo esc_url( self::url() ); ?>" class="nav-tab <?php echo 'registros' === $tab ? 'nav-tab-active' : ''; ?>">Registros</a>
				<a href="<?php echo esc_url( self::url( array( 'tab' => 'ajustes' ) ) ); ?>" class="nav-tab <?php echo 'ajustes' === $tab ? 'nav-tab-active' : ''; ?>">Correo y PDF</a>
			</nav>

			<?php
			if ( 'ajustes' === $tab ) {
				self::render_settings();
			} else {
				self::render_list();
			}
			?>
		</div>
		<?php
	}

	private static function notices() {
		$map = array(
			'saved'     => array( 'success', 'Ajustes guardados.' ),
			'resent'    => array( 'success', 'Correo reenviado.' ),
			'resend_ko' => array( 'error', 'No se pudo reenviar el correo. Revisa la configuración de WP Mail SMTP.' ),
			'test_ok'   => array( 'success', 'Correo de prueba enviado.' ),
			'test_fb'   => array( 'warning', 'Correo de prueba enviado, pero el servidor de correo (IONOS) no permite enviar desde el remitente elegido: ha salido desde la cuenta de WP Mail SMTP con "Responder a" la dirección elegida. Para usarla como remitente, créala como alias de esa cuenta en IONOS.' ),
			'test_ko'   => array( 'error', 'No se pudo enviar el correo de prueba. Revisa la configuración de WP Mail SMTP.' ),
		);
		$key = isset( $_GET['mcamp_msg'] ) ? sanitize_key( $_GET['mcamp_msg'] ) : '';
		if ( isset( $map[ $key ] ) ) {
			printf( '<div class="notice notice-%s is-dismissible"><p>%s</p></div>', esc_attr( $map[ $key ][0] ), esc_html( $map[ $key ][1] ) );
		}

		if ( ! MCamp_Mailer::pdf_path( mcamp_settings() ) ) {
			printf(
				'<div class="notice notice-warning"><p>Todavía no hay ningún PDF configurado: el correo se enviará sin adjunto. <a href="%s">Subir el PDF</a>.</p></div>',
				esc_url( self::url( array( 'tab' => 'ajustes' ) ) )
			);
		}
	}

	private static function render_list() {
		$table = new MCamp_List_Table();
		$table->process_bulk_action();
		$table->prepare_items();
		?>
		<p>Inserta el formulario en cualquier página con el shortcode <code>[macrisal_campana]</code>.</p>
		<form method="get">
			<input type="hidden" name="page" value="<?php echo esc_attr( self::SLUG ); ?>">
			<?php
			$table->search_box( 'Buscar', 'mcamp-search' );
			$table->display();
			?>
		</form>
		<?php
	}

	private static function render_settings() {
		$s        = mcamp_settings();
		$pdf_id   = (int) $s['pdf_id'];
		$pdf_url  = $pdf_id ? wp_get_attachment_url( $pdf_id ) : '';
		$pdf_name = $pdf_id ? basename( (string) get_attached_file( $pdf_id ) ) : '';
		?>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
			<input type="hidden" name="action" value="mcamp_save_settings">
			<?php wp_nonce_field( 'mcamp_save_settings' ); ?>

			<h2>PDF que recibe el usuario</h2>
			<table class="form-table" role="presentation">
				<tr>
					<th scope="row">Archivo PDF</th>
					<td>
						<input type="hidden" name="pdf_id" id="mcamp-pdf-id" value="<?php echo esc_attr( $pdf_id ); ?>">
						<p id="mcamp-pdf-current">
							<?php if ( $pdf_url ) : ?>
								<span class="dashicons dashicons-pdf"></span>
								<a href="<?php echo esc_url( $pdf_url ); ?>" target="_blank" rel="noopener"><?php echo esc_html( $pdf_name ); ?></a>
							<?php else : ?>
								<em>Ningún PDF seleccionado.</em>
							<?php endif; ?>
						</p>
						<p>
							<button type="button" class="button" id="mcamp-pdf-select">Subir o elegir PDF</button>
							<button type="button" class="button-link button-link-delete" id="mcamp-pdf-remove" <?php echo $pdf_id ? '' : 'style="display:none"'; ?>>Quitar PDF</button>
						</p>
						<p class="description">Se adjunta al correo que recibe cada persona que rellena el formulario. Máximo recomendado: 5 MB.</p>
					</td>
				</tr>
			</table>

			<h2>Correo al usuario</h2>
			<table class="form-table" role="presentation">
				<tr>
					<th scope="row"><label for="mcamp-from-name">Nombre del remitente</label></th>
					<td><input type="text" class="regular-text" id="mcamp-from-name" name="from_name" value="<?php echo esc_attr( $s['from_name'] ); ?>"></td>
				</tr>
				<tr>
					<th scope="row"><label for="mcamp-from-email">Correo del remitente</label></th>
					<td><input type="email" class="regular-text" id="mcamp-from-email" name="from_email" value="<?php echo esc_attr( $s['from_email'] ); ?>" placeholder="<?php echo esc_attr( self::smtp_from() ); ?>">
						<p class="description">Vacío = se envía desde <?php echo esc_html( self::smtp_from() ); ?> (la cuenta de WP Mail SMTP).<br>
						Usa una dirección de @macrisal.com que sea alias de esa cuenta en IONOS. Si IONOS no la acepta, el correo sale igualmente desde <?php echo esc_html( self::smtp_from() ); ?> con "Responder a" esta dirección. Compruébalo con "Enviar prueba".</p></td>
				</tr>
				<tr>
					<th scope="row"><label for="mcamp-subject">Asunto</label></th>
					<td><input type="text" class="large-text" id="mcamp-subject" name="subject" value="<?php echo esc_attr( $s['subject'] ); ?>"></td>
				</tr>
				<tr>
					<th scope="row">Mensaje</th>
					<td>
						<?php
						wp_editor(
							$s['body'],
							'mcamp_body',
							array(
								'textarea_name' => 'body',
								'textarea_rows' => 12,
								'media_buttons' => false,
							)
						);
						?>
						<p class="description">Puedes usar estas etiquetas en el asunto y el mensaje: <code>{nombre}</code> <code>{apellidos}</code> <code>{empresa}</code> <code>{direccion}</code> <code>{cp}</code> <code>{correo}</code> <code>{telefono}</code></p>
					</td>
				</tr>
			</table>

			<h2>Formulario</h2>
			<table class="form-table" role="presentation">
				<tr>
					<th scope="row"><label for="mcamp-button">Texto del botón</label></th>
					<td><input type="text" class="regular-text" id="mcamp-button" name="button_text" value="<?php echo esc_attr( $s['button_text'] ); ?>"></td>
				</tr>
				<tr>
					<th scope="row"><label for="mcamp-success">Mensaje tras enviar</label></th>
					<td><input type="text" class="large-text" id="mcamp-success" name="success_msg" value="<?php echo esc_attr( $s['success_msg'] ); ?>"></td>
				</tr>
				<tr>
					<th scope="row"><label for="mcamp-notify">Avisar al equipo</label></th>
					<td><input type="text" class="regular-text" id="mcamp-notify" name="notify_email" value="<?php echo esc_attr( $s['notify_email'] ); ?>" placeholder="marketing@macrisal.com">
						<p class="description">Opcional. Correo(s), separados por comas, que reciben un aviso con los datos de cada inscripción. Vacío = sin aviso.</p></td>
				</tr>
			</table>

			<h2>Página</h2>
			<table class="form-table" role="presentation">
				<tr>
					<th scope="row">Menú, migas, pie y WhatsApp</th>
					<td><label><input type="checkbox" name="hide_menu" value="1" <?php checked( (int) $s['hide_menu'], 1 ); ?>> Ocultar el menú, las migas de pan, el pie de página y el botón de WhatsApp en las páginas con el formulario</label>
						<p class="description">Se mantiene el logo de la cabecera y, abajo, una línea con Aviso legal, Privacidad y Cookies. El resto de la web no cambia.</p></td>
				</tr>
			</table>

			<h2>reCAPTCHA invisible</h2>
			<table class="form-table" role="presentation">
				<tr>
					<th scope="row"><label for="mcamp-rc-site">Clave del sitio</label></th>
					<td><input type="text" class="regular-text code" id="mcamp-rc-site" name="recaptcha_site" value="<?php echo esc_attr( $s['recaptcha_site'] ); ?>" autocomplete="off"></td>
				</tr>
				<tr>
					<th scope="row"><label for="mcamp-rc-secret">Clave secreta</label></th>
					<td><input type="password" class="regular-text code" id="mcamp-rc-secret" name="recaptcha_secret" value="<?php echo esc_attr( $s['recaptcha_secret'] ); ?>" autocomplete="new-password">
						<p class="description">Claves de reCAPTCHA v2 de tipo <strong>invisible</strong> (se crean en <a href="https://www.google.com/recaptcha/admin/create" target="_blank" rel="noopener">google.com/recaptcha/admin</a> con el dominio macrisal.com).<br>
						Mientras estén vacías, el formulario usa la casilla "No soy un robot" con las claves del formulario de contacto.</p></td>
				</tr>
			</table>

			<?php submit_button( 'Guardar cambios' ); ?>
		</form>

		<hr>
		<h2>Probar el correo</h2>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
			<input type="hidden" name="action" value="mcamp_test_mail">
			<?php wp_nonce_field( 'mcamp_test_mail' ); ?>
			<p>
				<input type="email" class="regular-text" name="test_to" value="<?php echo esc_attr( wp_get_current_user()->user_email ); ?>" required>
				<?php submit_button( 'Enviar prueba', 'secondary', 'submit', false ); ?>
			</p>
			<p class="description">Envía el correo guardado (con el PDF) usando datos de ejemplo. Guarda los cambios antes de probar.</p>
		</form>
		<?php
	}

	private static function smtp_from() {
		$smtp = get_option( 'wp_mail_smtp' );
		return ! empty( $smtp['mail']['from_email'] ) ? $smtp['mail']['from_email'] : get_option( 'admin_email' );
	}

	public static function save_settings() {
		if ( ! current_user_can( self::CAP ) ) {
			wp_die( 'No tienes permisos.' );
		}
		check_admin_referer( 'mcamp_save_settings' );

		$pdf_id = isset( $_POST['pdf_id'] ) ? absint( $_POST['pdf_id'] ) : 0;
		if ( $pdf_id && 'application/pdf' !== get_post_mime_type( $pdf_id ) ) {
			$pdf_id = 0;
		}

		$notify = isset( $_POST['notify_email'] ) ? sanitize_text_field( wp_unslash( $_POST['notify_email'] ) ) : '';
		$notify = implode( ', ', array_filter( array_map( 'trim', explode( ',', $notify ) ), 'is_email' ) );

		$settings = array(
			'pdf_id'           => $pdf_id,
			'from_name'        => sanitize_text_field( wp_unslash( $_POST['from_name'] ?? '' ) ),
			'from_email'       => sanitize_email( wp_unslash( $_POST['from_email'] ?? '' ) ),
			'subject'          => sanitize_text_field( wp_unslash( $_POST['subject'] ?? '' ) ),
			'body'             => wp_kses_post( wp_unslash( $_POST['body'] ?? '' ) ),
			'notify_email'     => $notify,
			'success_msg'      => sanitize_text_field( wp_unslash( $_POST['success_msg'] ?? '' ) ),
			'button_text'      => sanitize_text_field( wp_unslash( $_POST['button_text'] ?? '' ) ),
			'recaptcha_site'   => sanitize_text_field( wp_unslash( $_POST['recaptcha_site'] ?? '' ) ),
			'recaptcha_secret' => sanitize_text_field( wp_unslash( $_POST['recaptcha_secret'] ?? '' ) ),
			'hide_menu'        => empty( $_POST['hide_menu'] ) ? 0 : 1,
		);

		// Un campo vacío vuelve al valor por defecto.
		$settings = array_filter(
			$settings,
			static function ( $v ) {
				return '' !== $v;
			}
		);

		update_option( MCAMP_OPTION, $settings, false );

		wp_safe_redirect( self::url( array( 'tab' => 'ajustes', 'mcamp_msg' => 'saved' ) ) );
		exit;
	}

	public static function test_mail() {
		if ( ! current_user_can( self::CAP ) ) {
			wp_die( 'No tienes permisos.' );
		}
		check_admin_referer( 'mcamp_test_mail' );

		$to   = isset( $_POST['test_to'] ) ? sanitize_email( wp_unslash( $_POST['test_to'] ) ) : '';
		$sent = is_email( $to ) ? MCamp_Mailer::send_to_lead(
			array(
				'nombre'    => 'María',
				'apellidos' => 'García López',
				'empresa'   => 'Reformas García, S.L.',
				'direccion' => 'C/ Ca n’Oliva, 74',
				'cp'        => '08020',
				'correo'    => $to,
				'telefono'  => '600 000 000',
			)
		) : false;

		$msg = 'fallback' === $sent ? 'test_fb' : ( $sent ? 'test_ok' : 'test_ko' );
		wp_safe_redirect( self::url( array( 'tab' => 'ajustes', 'mcamp_msg' => $msg ) ) );
		exit;
	}

	public static function resend() {
		$id = isset( $_GET['id'] ) ? absint( $_GET['id'] ) : 0;
		if ( ! current_user_can( self::CAP ) ) {
			wp_die( 'No tienes permisos.' );
		}
		check_admin_referer( 'mcamp_resend_' . $id );

		$lead = MCamp_DB::get( $id );
		$sent = $lead && MCamp_Mailer::send_to_lead( $lead );
		if ( $lead ) {
			MCamp_DB::mark_sent( $id, (bool) $sent || $lead['correo_enviado'] );
		}

		wp_safe_redirect( self::url( array( 'mcamp_msg' => $sent ? 'resent' : 'resend_ko' ) ) );
		exit;
	}

	public static function export_csv() {
		if ( ! current_user_can( self::CAP ) ) {
			wp_die( 'No tienes permisos.' );
		}
		check_admin_referer( 'mcamp_export' );

		list( $rows ) = MCamp_DB::query( array( 'per_page' => 0, 'order' => 'ASC' ) );

		nocache_headers();
		header( 'Content-Type: text/csv; charset=UTF-8' );
		header( 'Content-Disposition: attachment; filename=campana-macrisal-' . gmdate( 'Y-m-d' ) . '.csv' );

		$out = fopen( 'php://output', 'w' );
		fwrite( $out, "\xEF\xBB\xBF" ); // BOM para que Excel lea bien los acentos.
		fputcsv( $out, array( 'Fecha', 'Nombre', 'Apellidos', 'Empresa', 'Dirección', 'CP', 'Correo', 'Teléfono', 'Acepta privacidad', 'Correo enviado', 'Página', 'IP' ), ';' );
		foreach ( $rows as $r ) {
			$line = array(
				$r['created_at'],
				$r['nombre'],
				$r['apellidos'],
				$r['empresa'],
				$r['direccion'],
				$r['cp'],
				$r['correo'],
				$r['telefono'],
				$r['acepta_privacidad'] ? 'Sí' : 'No',
				$r['correo_enviado'] ? 'Sí' : 'No',
				$r['pagina'],
				$r['ip'],
			);
			// Evita que Excel interprete los valores como fórmulas.
			$line = array_map(
				static function ( $v ) {
					return preg_match( '/^[=+\-@\t\r]/', (string) $v ) ? "'" . $v : $v;
				},
				$line
			);
			fputcsv( $out, $line, ';' );
		}
		fclose( $out );
		exit;
	}
}

/**
 * Tabla de registros en el escritorio.
 */
class MCamp_List_Table extends WP_List_Table {

	public function __construct() {
		parent::__construct(
			array(
				'singular' => 'registro',
				'plural'   => 'registros',
				'ajax'     => false,
			)
		);
	}

	public function get_columns() {
		return array(
			'cb'             => '<input type="checkbox">',
			'created_at'     => 'Fecha',
			'nombre'         => 'Nombre',
			'apellidos'      => 'Apellidos',
			'empresa'        => 'Empresa',
			'direccion'      => 'Dirección',
			'cp'             => 'CP',
			'correo'         => 'Correo',
			'telefono'       => 'Teléfono',
			'correo_enviado' => 'PDF enviado',
		);
	}

	protected function get_sortable_columns() {
		return array(
			'created_at'     => array( 'created_at', true ),
			'nombre'         => array( 'nombre', false ),
			'apellidos'      => array( 'apellidos', false ),
			'empresa'        => array( 'empresa', false ),
			'cp'             => array( 'cp', false ),
			'correo'         => array( 'correo', false ),
			'correo_enviado' => array( 'correo_enviado', false ),
		);
	}

	protected function get_bulk_actions() {
		return array( 'delete' => 'Eliminar' );
	}

	protected function column_cb( $item ) {
		return sprintf( '<input type="checkbox" name="ids[]" value="%d">', (int) $item['id'] );
	}

	protected function column_created_at( $item ) {
		$ts     = strtotime( $item['created_at'] );
		$resend = wp_nonce_url( admin_url( 'admin-post.php?action=mcamp_resend&id=' . (int) $item['id'] ), 'mcamp_resend_' . (int) $item['id'] );
		return esc_html( date_i18n( 'd/m/Y H:i', $ts ) )
			. $this->row_actions( array( 'resend' => '<a href="' . esc_url( $resend ) . '">Reenviar correo</a>' ) );
	}

	protected function column_correo( $item ) {
		return '<a href="mailto:' . esc_attr( $item['correo'] ) . '">' . esc_html( $item['correo'] ) . '</a>';
	}

	protected function column_telefono( $item ) {
		return '<a href="tel:' . esc_attr( preg_replace( '/[^0-9+]/', '', $item['telefono'] ) ) . '">' . esc_html( $item['telefono'] ) . '</a>';
	}

	protected function column_correo_enviado( $item ) {
		return $item['correo_enviado']
			? '<span style="color:#008a20">Sí</span>'
			: '<span style="color:#d63638">No</span>';
	}

	protected function column_default( $item, $column ) {
		return esc_html( $item[ $column ] ?? '' );
	}

	public function no_items() {
		echo 'Todavía no hay registros.';
	}

	public function process_bulk_action() {
		if ( 'delete' !== $this->current_action() || empty( $_GET['ids'] ) ) {
			return;
		}
		check_admin_referer( 'bulk-' . $this->_args['plural'] );
		if ( ! current_user_can( MCamp_Admin::CAP ) ) {
			return;
		}
		MCamp_DB::delete( (array) wp_unslash( $_GET['ids'] ) );
		echo '<div class="notice notice-success is-dismissible"><p>Registros eliminados.</p></div>';
	}

	public function prepare_items() {
		$per_page = 25;
		$search   = isset( $_GET['s'] ) ? sanitize_text_field( wp_unslash( $_GET['s'] ) ) : '';

		list( $rows, $total ) = MCamp_DB::query(
			array(
				'search'   => $search,
				'orderby'  => isset( $_GET['orderby'] ) ? sanitize_key( $_GET['orderby'] ) : 'created_at',
				'order'    => isset( $_GET['order'] ) ? sanitize_key( $_GET['order'] ) : 'DESC',
				'per_page' => $per_page,
				'page'     => $this->get_pagenum(),
			)
		);

		$this->items           = $rows;
		$this->_column_headers = array( $this->get_columns(), array(), $this->get_sortable_columns() );
		$this->set_pagination_args(
			array(
				'total_items' => $total,
				'per_page'    => $per_page,
			)
		);
	}
}
