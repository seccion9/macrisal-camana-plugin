<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Formulario público: shortcode [macrisal_campana] y recepción del envío.
 *
 * La página se sirve desde la caché de WP-Optimize, así que el formulario no
 * usa nonces (caducarían). La protección anti-spam es reCAPTCHA v2 (invisible si
 * hay claves propias en los ajustes; si no, la casilla con las claves del
 * formulario de contacto), un campo trampa, un tiempo mínimo de relleno y un
 * límite de envíos por IP y por correo.
 */
class MCamp_Form {

	const MIN_SECONDS      = 3;
	const MAX_PER_IP_HOUR  = 5;
	const MAX_PER_MAIL_DAY = 3;

	public static function init() {
		add_shortcode( 'macrisal_campana', array( __CLASS__, 'shortcode' ) );
		add_action( 'wp_ajax_mcamp_submit', array( __CLASS__, 'ajax_submit' ) );
		add_action( 'wp_ajax_nopriv_mcamp_submit', array( __CLASS__, 'ajax_submit' ) );
		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'register_assets' ) );
		add_filter( 'body_class', array( __CLASS__, 'body_class' ) );
	}

	public static function register_assets() {
		wp_register_style( 'mcamp-form', MCAMP_URL . 'assets/form.css', array(), MCAMP_VERSION );
		wp_register_script( 'mcamp-form', MCAMP_URL . 'assets/form.js', array(), MCAMP_VERSION, true );
		wp_localize_script( 'mcamp-form', 'mcampForm', array( 'ajaxUrl' => admin_url( 'admin-ajax.php' ) ) );

		// En la landing el CSS va en el <head> para que el menú no llegue a verse.
		if ( self::is_landing() ) {
			wp_enqueue_style( 'mcamp-form' );
		}
	}

	/**
	 * Página con el formulario y la opción "Ocultar el menú" activada.
	 */
	private static function is_landing() {
		$post = is_singular() ? get_post() : null;
		return $post && has_shortcode( $post->post_content, 'macrisal_campana' ) && mcamp_settings()['hide_menu'];
	}

	/**
	 * La clase mcamp-landing oculta el menú de la cabecera de Divi (ver form.css).
	 */
	public static function body_class( $classes ) {
		if ( self::is_landing() ) {
			$classes[] = 'mcamp-landing';
		}
		return $classes;
	}

	public static function shortcode() {
		wp_enqueue_style( 'mcamp-form' );
		wp_enqueue_script( 'mcamp-form' );

		$s         = mcamp_settings();
		$recaptcha = self::recaptcha_keys();
		if ( $recaptcha && ! $recaptcha[2] ) {
			// Script del plugin wpcf7-recaptcha: lo carga en diferido el snippet
			// "ReCAPTCHA - Lazy Load" y su recaptchaCallback pinta los .wpcf7-recaptcha.
			wp_enqueue_script( 'google-recaptcha' );
		}

		$fields = array(
			array( 'nombre', 'Nombre *', 'text', 'given-name', 100 ),
			array( 'apellidos', 'Apellidos *', 'text', 'family-name', 150 ),
			array( 'direccion', 'Dirección *', 'text', 'street-address', 255 ),
			array( 'cp', 'Código postal *', 'text', 'postal-code', 5 ),
			array( 'correo', 'Correo electrónico *', 'email', 'email', 190 ),
			array( 'telefono', 'Teléfono *', 'tel', 'tel', 20 ),
		);

		ob_start();
		?>
		<form class="mcamp-form" method="post" action="<?php echo esc_url( admin_url( 'admin-ajax.php' ) ); ?>" novalidate>
			<input type="hidden" name="action" value="mcamp_submit">
			<input type="hidden" name="mcamp_elapsed" value="">
			<input type="hidden" name="mcamp_page" value="">
			<div class="mcamp-hp" aria-hidden="true">
				<label>No rellenar este campo <input type="text" name="mcamp_web" tabindex="-1" autocomplete="off"></label>
			</div>

			<div class="mcamp-grid">
				<?php foreach ( $fields as $f ) : ?>
					<div class="mcamp-field mcamp-field--<?php echo esc_attr( $f[0] ); ?>">
						<label class="screen-reader-text" for="mcamp-<?php echo esc_attr( $f[0] ); ?>"><?php echo esc_html( rtrim( $f[1], ' *' ) ); ?></label>
						<input
							id="mcamp-<?php echo esc_attr( $f[0] ); ?>"
							class="mcamp-input"
							type="<?php echo esc_attr( $f[2] ); ?>"
							name="<?php echo esc_attr( $f[0] ); ?>"
							placeholder="<?php echo esc_attr( $f[1] ); ?>"
							autocomplete="<?php echo esc_attr( $f[3] ); ?>"
							maxlength="<?php echo (int) $f[4]; ?>"
							<?php echo 'cp' === $f[0] ? 'inputmode="numeric" pattern="[0-9]{5}"' : ''; ?>
							required aria-required="true">
						<span class="mcamp-error" role="alert"></span>
					</div>
				<?php endforeach; ?>
			</div>

			<div class="mcamp-field mcamp-field--privacidad">
				<label class="mcamp-check">
					<input type="checkbox" name="privacidad" value="1" required aria-required="true">
					<span>He leído y acepto la <a href="<?php echo esc_url( home_url( '/politica-de-privacidad/' ) ); ?>" target="_blank" rel="noopener">Política de Privacidad</a></span>
				</label>
				<span class="mcamp-error" role="alert"></span>
			</div>

			<?php if ( $recaptcha && $recaptcha[2] ) : ?>
				<div class="mcamp-field mcamp-field--recaptcha-invisible">
					<div class="mcamp-recaptcha" data-sitekey="<?php echo esc_attr( $recaptcha[0] ); ?>"></div>
					<input type="hidden" name="recaptcha" value="">
					<span class="mcamp-error" role="alert"></span>
				</div>
			<?php elseif ( $recaptcha ) : ?>
				<div class="mcamp-field mcamp-field--recaptcha">
					<span class="wpcf7-recaptcha g-recaptcha" data-sitekey="<?php echo esc_attr( $recaptcha[0] ); ?>"></span>
					<input type="hidden" name="recaptcha" value="">
					<span class="mcamp-error" role="alert"></span>
				</div>
			<?php endif; ?>

			<p class="mcamp-submit-wrap">
				<button type="submit" class="mcamp-submit"><?php echo esc_html( $s['button_text'] ); ?></button>
				<span class="mcamp-spinner" aria-hidden="true"></span>
			</p>
			<div class="mcamp-response" role="status" aria-live="polite"></div>
		</form>
		<?php
		return ob_get_clean();
	}

	/**
	 * Valida y limpia los datos recibidos. Devuelve [datos, errores].
	 */
	public static function validate( array $in ) {
		$get = static function ( $key ) use ( $in ) {
			return isset( $in[ $key ] ) ? trim( sanitize_text_field( wp_unslash( $in[ $key ] ) ) ) : '';
		};

		$data = array(
			'nombre'    => $get( 'nombre' ),
			'apellidos' => $get( 'apellidos' ),
			'direccion' => $get( 'direccion' ),
			'cp'        => preg_replace( '/\s+/', '', $get( 'cp' ) ),
			'correo'    => $get( 'correo' ),
			'telefono'  => $get( 'telefono' ),
		);

		$errors   = array();
		$required = 'Este campo es obligatorio.';

		foreach ( array( 'nombre' => 100, 'apellidos' => 150, 'direccion' => 255 ) as $key => $max ) {
			if ( '' === $data[ $key ] ) {
				$errors[ $key ] = $required;
			} elseif ( mb_strlen( $data[ $key ] ) > $max ) {
				$errors[ $key ] = 'El texto es demasiado largo.';
			}
		}

		if ( '' === $data['cp'] ) {
			$errors['cp'] = $required;
		} elseif ( ! preg_match( '/^[0-9]{5}$/', $data['cp'] ) ) {
			$errors['cp'] = 'El código postal debe tener 5 cifras.';
		}

		if ( '' === $data['correo'] ) {
			$errors['correo'] = $required;
		} elseif ( ! is_email( $data['correo'] ) ) {
			$errors['correo'] = 'La dirección de correo electrónico no es válida.';
		} else {
			$data['correo'] = sanitize_email( $data['correo'] );
		}

		$digits = preg_replace( '/\D/', '', $data['telefono'] );
		if ( '' === $data['telefono'] ) {
			$errors['telefono'] = $required;
		} elseif ( ! preg_match( '/^\+?[0-9 ().-]+$/', $data['telefono'] ) || strlen( $digits ) < 9 || strlen( $digits ) > 15 ) {
			$errors['telefono'] = 'El número de teléfono no es válido.';
		}

		if ( empty( $in['privacidad'] ) ) {
			$errors['privacidad'] = 'Debes aceptar la Política de Privacidad.';
		}

		return array( $data, $errors );
	}

	public static function ajax_submit() {
		$fail = static function ( $message, $errors = array(), $code = 400 ) {
			wp_send_json( array( 'success' => false, 'message' => $message, 'errors' => (object) $errors ), $code );
		};

		// Campo trampa relleno o envío demasiado rápido: respondemos "ok" sin guardar.
		$elapsed = isset( $_POST['mcamp_elapsed'] ) ? (int) $_POST['mcamp_elapsed'] : 0;
		if ( ! empty( $_POST['mcamp_web'] ) || $elapsed < self::MIN_SECONDS * 1000 ) {
			wp_send_json( array( 'success' => true, 'message' => mcamp_settings()['success_msg'] ) );
		}

		list( $data, $errors ) = self::validate( $_POST ); // phpcs:ignore WordPress.Security.NonceVerification
		if ( $errors ) {
			$fail( 'Uno o más campos tienen un error. Por favor revísalos e inténtalo de nuevo.', $errors );
		}

		$ip = self::client_ip();

		$captcha = self::verify_recaptcha( isset( $_POST['g-recaptcha-response'] ) ? sanitize_text_field( wp_unslash( $_POST['g-recaptcha-response'] ) ) : '', $ip );
		if ( true !== $captcha ) {
			$fail( $captcha, array( 'recaptcha' => $captcha ) );
		}

		if ( MCamp_DB::recent_count( 'ip', $ip, 60 ) >= self::MAX_PER_IP_HOUR
			|| MCamp_DB::recent_count( 'correo', $data['correo'], 24 * 60 ) >= self::MAX_PER_MAIL_DAY ) {
			$fail( 'Has realizado demasiados envíos. Por favor inténtalo más tarde.', array(), 429 );
		}

		$page          = isset( $_POST['mcamp_page'] ) ? esc_url_raw( wp_unslash( $_POST['mcamp_page'] ) ) : '';
		$data['pagina'] = substr( $page, 0, 255 );
		$data['ip']     = $ip;

		$id = MCamp_DB::insert( $data );
		if ( ! $id ) {
			$fail( 'Hubo un error al guardar tus datos. Por favor inténtalo de nuevo más tarde.', array(), 500 );
		}

		$sent = MCamp_Mailer::send_to_lead( $data );
		MCamp_DB::mark_sent( $id, (bool) $sent );
		MCamp_Mailer::notify_team( $data, (bool) $sent );

		wp_send_json( array( 'success' => true, 'message' => mcamp_settings()['success_msg'] ) );
	}

	/**
	 * Claves de reCAPTCHA v2: [clave del sitio, clave secreta, invisible].
	 *
	 * Las claves propias de los ajustes son de tipo invisible; si no hay, se usan
	 * las de Contact Form 7 (tipo casilla "No soy un robot").
	 */
	public static function recaptcha_keys() {
		$s = mcamp_settings();
		if ( '' !== $s['recaptcha_site'] && '' !== $s['recaptcha_secret'] ) {
			return array( $s['recaptcha_site'], $s['recaptcha_secret'], true );
		}

		$cf7 = get_option( 'wpcf7' );
		if ( empty( $cf7['recaptcha'] ) || ! is_array( $cf7['recaptcha'] ) ) {
			return array();
		}
		$site = (string) array_key_first( $cf7['recaptcha'] );
		return array( $site, (string) $cf7['recaptcha'][ $site ], false );
	}

	/**
	 * Devuelve true si el reCAPTCHA es válido (o no está configurado) o el mensaje de error.
	 */
	private static function verify_recaptcha( $token, $ip ) {
		$keys = self::recaptcha_keys();
		if ( ! $keys ) {
			return true;
		}
		if ( '' === $token ) {
			return 'Por favor, verifica que no eres un robot.';
		}

		$res = wp_remote_post(
			'https://www.google.com/recaptcha/api/siteverify',
			array(
				'timeout' => 10,
				'body'    => array(
					'secret'   => $keys[1],
					'response' => $token,
					'remoteip' => $ip,
				),
			)
		);
		$json = is_wp_error( $res ) ? null : json_decode( wp_remote_retrieve_body( $res ), true );

		if ( ! empty( $json['success'] ) ) {
			return true;
		}
		return 'No se ha podido verificar el reCAPTCHA. Por favor, inténtalo de nuevo.';
	}

	private static function client_ip() {
		$ip = isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : '';
		return filter_var( $ip, FILTER_VALIDATE_IP ) ? $ip : '';
	}
}
