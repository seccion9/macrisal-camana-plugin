# Macrisal – Campaña de captación

Plugin de WordPress para macrisal.com: formulario de captación que guarda los datos del visitante y le envía un PDF por correo.

- Shortcode: `[macrisal_campana]`
- Panel: menú **Campaña captación** (solo administradores): registros, búsqueda, reenvío, borrado y exportación CSV; pestaña **Correo y PDF** para el PDF, remitente, asunto, mensaje, aviso al equipo y claves de reCAPTCHA invisible.
- Aviso al equipo: en *Avisar al equipo* (p. ej. marketing@macrisal.com) llega un correo con los datos de cada inscripción.
- Landing: en las páginas con el formulario se oculta el menú de la cabecera de Divi (opción *Página → Menú de la web*).
- Etiquetas en asunto/mensaje: `{nombre}` `{apellidos}` `{direccion}` `{cp}` `{correo}` `{telefono}`
- Datos en la tabla `{prefix}macrisal_campana_leads`.
- Requiere WP Mail SMTP para el envío.
- reCAPTCHA: con claves v2 **invisible** en los ajustes usa el reCAPTCHA invisible (se carga al tocar el formulario); sin ellas, la casilla "No soy un robot" con las claves de Contact Form 7.

Instalación: copiar la carpeta `macrisal-campana` en `wp-content/plugins/` y activarla.
