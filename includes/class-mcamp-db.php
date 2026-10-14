<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Tabla de registros de la campaña.
 */
class MCamp_DB {

	public static function table() {
		global $wpdb;
		return $wpdb->prefix . 'macrisal_campana_leads';
	}

	public static function install() {
		global $wpdb;
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		$table   = self::table();
		$charset = $wpdb->get_charset_collate();

		dbDelta(
			"CREATE TABLE {$table} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			nombre varchar(100) NOT NULL DEFAULT '',
			apellidos varchar(150) NOT NULL DEFAULT '',
			direccion varchar(255) NOT NULL DEFAULT '',
			cp varchar(10) NOT NULL DEFAULT '',
			correo varchar(190) NOT NULL DEFAULT '',
			telefono varchar(30) NOT NULL DEFAULT '',
			acepta_privacidad tinyint(1) NOT NULL DEFAULT 0,
			pagina varchar(255) NOT NULL DEFAULT '',
			ip varchar(45) NOT NULL DEFAULT '',
			correo_enviado tinyint(1) NOT NULL DEFAULT 0,
			created_at datetime NOT NULL,
			PRIMARY KEY  (id),
			KEY correo (correo),
			KEY created_at (created_at)
			) {$charset};"
		);

		update_option( 'mcamp_db_version', MCAMP_DB_VERSION, false );
	}

	public static function maybe_upgrade() {
		if ( get_option( 'mcamp_db_version' ) !== MCAMP_DB_VERSION ) {
			self::install();
		}
	}

	public static function insert( array $data ) {
		global $wpdb;

		$ok = $wpdb->insert(
			self::table(),
			array(
				'nombre'            => $data['nombre'],
				'apellidos'         => $data['apellidos'],
				'direccion'         => $data['direccion'],
				'cp'                => $data['cp'],
				'correo'            => $data['correo'],
				'telefono'          => $data['telefono'],
				'acepta_privacidad' => 1,
				'pagina'            => $data['pagina'],
				'ip'                => $data['ip'],
				'correo_enviado'    => 0,
				'created_at'        => current_time( 'mysql' ),
			),
			array( '%s', '%s', '%s', '%s', '%s', '%s', '%d', '%s', '%s', '%d', '%s' )
		);

		return $ok ? (int) $wpdb->insert_id : 0;
	}

	public static function get( $id ) {
		global $wpdb;
		return $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ' . self::table() . ' WHERE id = %d', $id ), ARRAY_A );
	}

	public static function mark_sent( $id, $sent ) {
		global $wpdb;
		$wpdb->update( self::table(), array( 'correo_enviado' => $sent ? 1 : 0 ), array( 'id' => (int) $id ), array( '%d' ), array( '%d' ) );
	}

	public static function delete( array $ids ) {
		global $wpdb;
		$ids = array_filter( array_map( 'absint', $ids ) );
		if ( ! $ids ) {
			return 0;
		}
		$placeholders = implode( ',', array_fill( 0, count( $ids ), '%d' ) );
		return (int) $wpdb->query( $wpdb->prepare( 'DELETE FROM ' . self::table() . " WHERE id IN ($placeholders)", $ids ) );
	}

	/**
	 * Listado con búsqueda, orden y paginación.
	 */
	public static function query( $args = array() ) {
		global $wpdb;

		$args = wp_parse_args(
			$args,
			array(
				'search'   => '',
				'orderby'  => 'created_at',
				'order'    => 'DESC',
				'per_page' => 20,
				'page'     => 1,
			)
		);

		$allowed = array( 'id', 'nombre', 'apellidos', 'cp', 'correo', 'created_at', 'correo_enviado' );
		$orderby = in_array( $args['orderby'], $allowed, true ) ? $args['orderby'] : 'created_at';
		$order   = 'ASC' === strtoupper( $args['order'] ) ? 'ASC' : 'DESC';

		$where  = '1=1';
		$params = array();
		if ( '' !== $args['search'] ) {
			$like   = '%' . $wpdb->esc_like( $args['search'] ) . '%';
			$where .= ' AND (nombre LIKE %s OR apellidos LIKE %s OR direccion LIKE %s OR cp LIKE %s OR correo LIKE %s OR telefono LIKE %s)';
			$params = array_fill( 0, 6, $like );
		}

		$table = self::table();
		$sql   = "SELECT SQL_CALC_FOUND_ROWS * FROM {$table} WHERE {$where} ORDER BY {$orderby} {$order}";

		if ( $args['per_page'] > 0 ) {
			$sql     .= ' LIMIT %d OFFSET %d';
			$params[] = (int) $args['per_page'];
			$params[] = (int) ( ( max( 1, (int) $args['page'] ) - 1 ) * $args['per_page'] );
		}

		$rows  = $wpdb->get_results( $params ? $wpdb->prepare( $sql, $params ) : $sql, ARRAY_A );
		$total = (int) $wpdb->get_var( 'SELECT FOUND_ROWS()' );

		return array( $rows, $total );
	}

	/**
	 * Envíos recientes desde una IP (límite anti-abuso).
	 */
	public static function recent_count( $field, $value, $minutes ) {
		global $wpdb;
		$field = in_array( $field, array( 'ip', 'correo' ), true ) ? $field : 'ip';
		$since = gmdate( 'Y-m-d H:i:s', current_time( 'timestamp' ) - $minutes * MINUTE_IN_SECONDS );
		return (int) $wpdb->get_var(
			$wpdb->prepare( 'SELECT COUNT(*) FROM ' . self::table() . " WHERE {$field} = %s AND created_at >= %s", $value, $since )
		);
	}
}
