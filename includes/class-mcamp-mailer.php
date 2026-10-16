<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Correos de la campaña: el del usuario (con PDF) y el aviso interno opcional.
 */
class MCamp_Mailer {

	/**
	 * Sustituye {nombre}, {apellidos}, etc. por los datos del registro.
	 */
	public static function replace_tags( $text, array $lead, $escape ) {
		$tags = array();
		foreach ( array( 'nombre', 'apellidos', 'empresa', 'direccion', 'cp', 'correo', 'telefono' ) as $key ) {
			$value                 = isset( $lead[ $key ] ) ? (string) $lead[ $key ] : '';
			$tags[ '{' . $key . '}' ] = $escape ? esc_html( $value ) : $value;
		}
		return strtr( $text, $tags );
	}

	public static function pdf_path( $settings ) {
		$id = (int) $settings['pdf_id'];
		if ( ! $id ) {
			return '';
		}
		$path = get_attached_file( $id );
		return ( $path && file_exists( $path ) ) ? $path : '';
	}

	/**
	 * Envía al usuario el correo configurado con el PDF adjunto.
	 *
	 * Si hay un correo de remitente propio se intenta enviar desde él. El SMTP de
	 * IONOS solo acepta las direcciones de la cuenta autenticada (y sus alias):
	 * si lo rechaza, se reenvía desde la dirección de WP Mail SMTP con
	 * "Responder a" la dirección elegida.
	 *
	 * @return string|false 'ok', 'fallback' (enviado desde la cuenta por defecto) o false.
	 */
	public static function send_to_lead( array $lead ) {
		$settings = mcamp_settings();

		$subject = self::replace_tags( $settings['subject'], $lead, false );
		$body    = self::replace_tags( wpautop( $settings['body'] ), $lead, true );
		$html    = self::wrap_html( $body );

		$attachments = array();
		$pdf         = self::pdf_path( $settings );
		if ( $pdf ) {
			$attachments[] = $pdf;
		}

		$from_email = is_email( $settings['from_email'] ) ? $settings['from_email'] : '';

		if ( $from_email && self::send( $lead['correo'], $subject, $html, $attachments, $settings['from_name'], $from_email ) ) {
			return 'ok';
		}

		if ( self::send( $lead['correo'], $subject, $html, $attachments, $settings['from_name'], '', $from_email ) ) {
			return $from_email ? 'fallback' : 'ok';
		}

		return false;
	}

	/**
	 * wp_mail con nombre/correo de remitente solo para este envío.
	 */
	private static function send( $to, $subject, $html, $attachments, $from_name, $from_email = '', $reply_to = '' ) {
		$headers = array( 'Content-Type: text/html; charset=UTF-8' );
		if ( $reply_to ) {
			$headers[] = 'Reply-To: ' . $reply_to;
		}

		$name_cb = static function () use ( $from_name ) {
			return $from_name;
		};
		// WP Mail SMTP fuerza su remitente en phpmailer_init y wp_mail_from;
		// esto se ejecuta después y lo sustituye solo para este correo.
		$from_cb = static function ( $phpmailer ) use ( $from_email, $from_name ) {
			$phpmailer->setFrom( $from_email, $from_name, false );
			$phpmailer->Sender = $from_email;
		};

		if ( '' !== trim( (string) $from_name ) ) {
			add_filter( 'wp_mail_from_name', $name_cb, PHP_INT_MAX );
		}
		if ( $from_email ) {
			add_action( 'phpmailer_init', $from_cb, PHP_INT_MAX );
		}

		$sent = wp_mail( $to, $subject, $html, $headers, $attachments );

		remove_filter( 'wp_mail_from_name', $name_cb, PHP_INT_MAX );
		remove_action( 'phpmailer_init', $from_cb, PHP_INT_MAX );

		return (bool) $sent;
	}

	/**
	 * Aviso interno al equipo (si hay un correo configurado) con los datos del registro.
	 */
	public static function notify_team( array $lead, $pdf_sent = true ) {
		$settings = mcamp_settings();
		$to       = array_filter( array_map( 'trim', explode( ',', (string) $settings['notify_email'] ) ), 'is_email' );
		if ( ! $to ) {
			return false;
		}

		$rows = array(
			'Fecha'       => date_i18n( 'd/m/Y H:i' ),
			'Nombre'      => $lead['nombre'],
			'Apellidos'   => $lead['apellidos'],
			'Empresa'     => '' !== ( $lead['empresa'] ?? '' ) ? $lead['empresa'] : '—',
			'Dirección'   => $lead['direccion'],
			'CP'          => $lead['cp'],
			'Correo'      => $lead['correo'],
			'Teléfono'    => $lead['telefono'],
			'PDF enviado' => $pdf_sent ? 'Sí' : 'No (se puede reenviar desde el escritorio)',
			'Página'      => $lead['pagina'] ?? '',
		);

		$table = '';
		foreach ( $rows as $label => $value ) {
			$table .= '<tr><td style="padding:6px 16px 6px 0;color:#6b6e75;white-space:nowrap;vertical-align:top;">' . esc_html( $label ) . '</td>'
				. '<td style="padding:6px 0;"><strong>' . esc_html( (string) $value ) . '</strong></td></tr>';
		}

		$body = '<p>Nueva inscripción en la campaña de captación:</p>'
			. '<table role="presentation" cellpadding="0" cellspacing="0" style="font-size:15px;">' . $table . '</table>'
			. '<p style="margin-top:24px;"><a href="' . esc_url( admin_url( 'admin.php?page=macrisal-campana' ) ) . '" style="color:#ff002b;">Ver todos los registros</a></p>';

		return wp_mail(
			$to,
			'Nueva inscripción en la campaña: ' . trim( $lead['nombre'] . ' ' . $lead['apellidos'] ),
			self::wrap_html( $body ),
			array( 'Content-Type: text/html; charset=UTF-8', 'Reply-To: ' . $lead['correo'] )
		);
	}

	private static function wrap_html( $content ) {
		return '<!doctype html><html><head><meta charset="utf-8"></head>'
			. '<body style="margin:0;padding:0;background:#EDF2F4;">'
			. '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#EDF2F4;padding:24px 0;"><tr><td align="center">'
			. '<table role="presentation" width="600" cellpadding="0" cellspacing="0" style="max-width:600px;width:100%;background:#ffffff;">'
			. '<tr><td style="background:#ff002b;height:6px;font-size:0;line-height:0;">&nbsp;</td></tr>'
			. '<tr><td style="padding:32px;font-family:Arial,Helvetica,sans-serif;font-size:15px;line-height:1.6;color:#222429;">'
			. $content
			. '</td></tr>'
			. '<tr><td style="padding:16px 32px;background:#222429;color:#ffffff;font-family:Arial,Helvetica,sans-serif;font-size:12px;">'
			. 'FINESTRES MACRISAL, S.L. · <a href="https://www.macrisal.com" style="color:#ffffff;">www.macrisal.com</a>'
			. '</td></tr></table></td></tr></table></body></html>';
	}
}
