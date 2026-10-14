# Macrisal – Campaña de captación

Plugin de WordPress para macrisal.com: formulario de captación que guarda los datos del visitante y le envía un PDF por correo.

- Shortcode: `[macrisal_campana]`
- Panel: menú **Campaña captación** (solo administradores): registros, búsqueda, reenvío, borrado y exportación CSV; pestaña **Correo y PDF** para el PDF, remitente, asunto y mensaje.
- Etiquetas en asunto/mensaje: `{nombre}` `{apellidos}` `{direccion}` `{cp}` `{correo}` `{telefono}`
- Datos en la tabla `{prefix}macrisal_campana_leads`.
- Requiere WP Mail SMTP para el envío y reutiliza las claves de reCAPTCHA del plugin de Contact Form 7.

Instalación: copiar la carpeta `macrisal-campana` en `wp-content/plugins/` y activarla.
