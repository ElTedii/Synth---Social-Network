# Synth

Red social enfocada en música y audio: productores y artistas suben pistas, interactúan con likes y comentarios, siguen a otros usuarios y consultan estadísticas de su audiencia.

Proyecto académico de **Bases de Datos** (BDM). El foco está en el diseño de la base de datos (tablas, triggers, procedimientos almacenados y vistas) y en un front-end con estética oscura tipo "glassmorphism".

> **Estado:** avance en desarrollo. El registro, el inicio de sesión y la edición de usuario ya se conectan a MySQL mediante PHP; el resto de las pantallas son maquetas estáticas con datos de ejemplo.

---

## Tecnologías

| Capa | Tecnología |
| --- | --- |
| Front-end | HTML5, CSS3, JavaScript vanilla |
| Back-end | PHP (PDO) |
| Base de datos | MySQL / MariaDB |
| Tipografías | Inter, IBM Plex Mono, Newsreader (Google Fonts) |

---

## Estructura del proyecto

```
Synth/
├── php/
│   ├── Conexion.php            # Clase de conexión a MySQL con PDO
│   ├── procesar_registro.php   # Alta de usuario
│   ├── procesar_login.php      # Inicio de sesión
│   ├── procesar_edicion.php    # Modificación de usuario
│   └── test_conexion.php       # Vacío (pendiente)
├── Styles/
│   ├── style.css               # Estilos globales (tokens, sidebar, feed, paneles)
│   ├── login.css               # Estilos de formularios (registro / edición)
│   ├── perfil.css              # Estilos de perfil
│   └── r.css                   # Estilos adicionales
├── bdm red social.sql          # Esquema, triggers, SPs, vista y datos de prueba
├── index.html                  # Feed "Descubrir"
├── login.html                  # Inicio de sesión
├── registro.html               # Crear cuenta
├── miperfil.html               # Perfil del usuario con pistas recientes
├── perfil.html                 # Perfil (versión simple)
├── Editar.html                 # Editar datos de la cuenta
├── post_detalles.html          # Detalle de publicación y comentarios
├── notificaciones.html         # Actividad / notificaciones
├── reportes.html               # Panel de estadísticas de audiencia
└── configuracion.html          # Seguridad, privacidad y eliminación de cuenta
```

---

## Instalación y ejecución

### Requisitos

- PHP 7.4 o superior con la extensión `pdo_mysql`
- MySQL 8.0 o superior (o MariaDB equivalente; los `CHECK` requieren MySQL 8.0.16+)
- Un servidor local como XAMPP, WAMP, Laragon o MAMP

### Pasos

1. **Copia el proyecto** dentro de la carpeta pública de tu servidor (por ejemplo `htdocs/Synth`).

2. **Crea la base de datos** importando el script SQL:

   ```bash
   mysql -u root -p < "bdm red social.sql"
   ```

   También puedes abrirlo en phpMyAdmin o MySQL Workbench y ejecutarlo completo. Esto crea la base `red_social_bdm`, sus tablas, triggers, procedimientos, la vista y los datos de prueba.

3. **Configura las credenciales** en `php/Conexion.php`:

   ```php
   private $host = "localhost";
   private $db_name = "red_social_bdm";
   private $username = "root";   // tu usuario de MySQL
   private $password = "";       // tu contraseña de MySQL
   ```

4. **Abre la aplicación** en el navegador:

   ```
   http://localhost/Synth/login.html
   ```

---

## Base de datos

Base de datos: `red_social_bdm`

### Tablas

| Tabla | Descripción |
| --- | --- |
| `usuarios` | Cuentas de usuario (correo único, contraseña hasheada, foto de perfil en BLOB, estado activo/inactivo) |
| `auditoria_usuarios` | Bitácora de cambios sensibles en usuarios (alimentada por trigger) |
| `publicaciones` | Publicaciones de texto (máx. 500 caracteres) con borrado lógico (`activa` / `eliminada`) |
| `multimedia` | Imágenes o videos asociados a una publicación (BLOB o ruta de archivo) |
| `likes` | Likes por usuario y publicación (único por par) |
| `comentarios` | Comentarios en publicaciones (máx. 300 caracteres) |
| `compartidos` | Publicaciones compartidas, con comentario opcional |
| `seguidores` | Relación seguidor → seguido (único por par, no permite seguirse a sí mismo) |

Todas las llaves foráneas usan `ON DELETE CASCADE`.

### Triggers

| Trigger | Evento | Función |
| --- | --- | --- |
| `trg_auditoria_usuarios` | `AFTER UPDATE` en `usuarios` | Registra en auditoría los cambios de estado y de correo |
| `trg_validar_like` | `BEFORE INSERT` en `likes` | Impide dar like a una publicación eliminada |
| `trg_evitar_auto_seguimiento` | `BEFORE INSERT` en `seguidores` | Impide que un usuario se siga a sí mismo |

### Procedimientos almacenados

| Procedimiento | Acciones / uso |
| --- | --- |
| `sp_gestion_usuarios` | `registrar`, `iniciar_sesion`, `modificar` |
| `sp_gestion_publicaciones` | `crear` (con multimedia opcional), `eliminar` (borrado lógico) |
| `sp_reporte_estadisticas(fecha_inicio, fecha_fin)` | Devuelve total de publicaciones, likes y nuevos seguidores en un rango de fechas |

### Vista

- `vw_feed_publicaciones`: feed de publicaciones activas con autor, fecha, contenido, multimedia y total de likes, ordenado de la más reciente a la más antigua.

### Datos de prueba

El script inserta tres usuarios (`Seckoe`, `Tedss`, `Admin`), dos publicaciones, dos likes y un seguimiento para probar triggers y estadísticas.

> Los usuarios de prueba se insertan con la contraseña en texto plano (`Hash123!`) directamente por el SP, por lo que **no podrán iniciar sesión** (el login usa `password_verify`). Para probar el login, crea una cuenta desde `registro.html`.

---

## Funcionalidades

### Implementadas (con backend)

- **Registro de usuario:** `registro.html` → `php/procesar_registro.php`. Valida contraseña con patrón (mín. 8 caracteres, mayúscula, minúscula, número y símbolo), la hashea con `password_hash` y valida correo duplicado desde el SP.
- **Inicio de sesión:** `login.html` → `php/procesar_login.php`. Verifica la contraseña con `password_verify`.
- **Edición de usuario:** `Editar.html` → `php/procesar_edicion.php`.

### Maquetadas (front-end sin backend)

- Feed "Descubrir" con tarjetas de pistas, reproductor simulado y waveform animada
- Botón de like interactivo y botón play/pausa (JavaScript)
- Modal "Subir Pista"
- Perfil de usuario con estadísticas
- Detalle de publicación con comentarios
- Notificaciones de actividad
- Panel de reportes con tarjetas y tabla de rendimiento
- Configuración de cuenta (privacidad, cambio de contraseña, zona de peligro)

---

## Diseño

Interfaz oscura con paneles de cristal translúcido y fondo cinemático (imagen y video en loop). Los tokens de diseño viven en `:root` de `Styles/style.css`:

| Token | Valor | Uso |
| --- | --- | --- |
| `--ground` | `#00020a` | Fondo base |
| `--glass-nav` | `rgba(4, 10, 30, 0.7)` | Barra lateral |
| `--glass-card` | `rgba(8, 21, 62, 0.84)` | Tarjetas y paneles |
| `--accent` | `#86A2FF` | Color de acento |
| `--font-logo` | Newsreader | Logotipo y títulos |
| `--font-ui` | Inter | Interfaz |
| `--font-mono` | IBM Plex Mono | Etiquetas y metadatos |

---
