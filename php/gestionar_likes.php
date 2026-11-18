
<?php

session_start();

require_once 'Conexion.php';

// Se establece que la respuesta de este archivo será en formato JSON.
// Esto permite que JavaScript pueda recibir los datos y utilizarlos
// para actualizar los likes en la página sin tener que recargarla.
header('Content-Type: application/json; charset=utf-8');

// ==========================================
// 1. VERIFICAR QUE EL USUARIO HAYA INICIADO SESIÓN
// ==========================================

// Primero compruebo que exista el ID del usuario en la sesión.
// Esto es necesario porque solamente los usuarios que iniciaron sesión
// pueden dar like, quitarlo o consultar quién dio like.
if (!isset($_SESSION['id_usuario'])) {

    // El código 401 indica que el usuario no está autorizado.
    http_response_code(401);

    // Envío el mensaje de error en formato JSON para que JavaScript lo reciba.
    echo json_encode(['error' => 'Debes iniciar sesión.']);

    exit;
}

// ==========================================
// 2. COMPROBAR EL MÉTODO DE LA PETICIÓN
// ==========================================

// Verifico que la solicitud se haya enviado mediante POST.
// Este archivo recibe los datos desde JavaScript usando este método.
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {

    // El código 405 indica que el método utilizado no está permitido.
    http_response_code(405);

    echo json_encode(['error' => 'Método no permitido.']);

    exit;
}

// ==========================================
// 3. OBTENER LOS DATOS ENVIADOS DESDE JAVASCRIPT
// ==========================================

// Obtengo la acción que se quiere realizar.
// Puede ser "toggle", para dar o quitar un like,
// o "listar", para consultar los usuarios que dieron like.
$accion = $_POST['accion'] ?? '';

// Obtengo el ID de la publicación sobre la que se realizará la operación.
$idPublicacion = $_POST['id_publicacion'] ?? '';

// Compruebo que el ID recibido contenga únicamente números
// y que sea mayor que cero. Así evito procesar identificadores inválidos.
if (!ctype_digit((string)$idPublicacion) || (int)$idPublicacion <= 0) {

    // El código 400 indica que los datos enviados no son válidos.
    http_response_code(400);

    echo json_encode(['error' => 'Publicación no válida.']);

    exit;
}

// Verifico que la acción recibida sea una de las dos permitidas.
// Esto evita que se intente ejecutar una operación diferente
// a las que están contempladas en el procedimiento almacenado.
if (!in_array($accion, ['toggle', 'listar'], true)) {

    http_response_code(400);

    echo json_encode(['error' => 'Acción no válida.']);

    exit;
}

try {

    // ==========================================
    // 4. CONECTAR CON LA BASE DE DATOS
    // ==========================================

    // Creo un objeto de la clase Conexion para establecer
    // la conexión con la base de datos red_social_bdm.
    $conexion = new Conexion();

    // Obtengo la conexión PDO para poder ejecutar el procedimiento almacenado.
    $db = $conexion->getConexion();

    // ==========================================
    // 5. EJECUTAR EL PROCEDIMIENTO ALMACENADO
    // ==========================================

    // Utilizo el procedimiento sp_gestion_likes para realizar
    // las operaciones relacionadas con los likes.
    // Los parámetros permiten indicar qué acción se realizará,
    // sobre qué publicación y qué usuario está haciendo la petición.
    $sql = "CALL sp_gestion_likes(
        :accion,
        :id_publicacion,
        :id_usuario
    )";

    // Preparo la llamada al procedimiento almacenado.
    // Los parámetros se dejan como marcadores para enviar sus valores
    // de forma segura mediante bindValue().
    $stmt = $db->prepare($sql);

    // Envío la acción que se va a realizar: toggle o listar.
    $stmt->bindValue(':accion', $accion, PDO::PARAM_STR);

    // Envío el ID de la publicación que se quiere modificar o consultar.
    $stmt->bindValue(':id_publicacion', (int)$idPublicacion, PDO::PARAM_INT);

    // Envío el ID del usuario que inició sesión.
    // Este dato se obtiene directamente de la sesión para identificar
    // quién está dando o consultando los likes.
    $stmt->bindValue(':id_usuario', (int)$_SESSION['id_usuario'], PDO::PARAM_INT);

    // Ejecuto el procedimiento almacenado con los parámetros anteriores.
    $stmt->execute();

    // ==========================================
    // 6. PROCESAR EL RESULTADO SEGÚN LA ACCIÓN
    // ==========================================

    // Si la acción es toggle, significa que el usuario presionó
    // el botón de like. El procedimiento revisa si ya había dado like:
    // si ya existía, lo quita; si no existía, lo registra.
    if ($accion === 'toggle') {

        // Recupero el resultado que devuelve el procedimiento almacenado.
        // Este resultado contiene si el usuario tiene like en la publicación
        // y el total de likes que tiene después de realizar la operación.
        $resultado = $stmt->fetch(PDO::FETCH_ASSOC);

        // Envío los datos a JavaScript en formato JSON.
        // "usuario_dio_like" permite actualizar el aspecto del botón
        // y "total_likes" permite mostrar el contador actualizado.
        echo json_encode([
            'success' => true,
            'usuario_dio_like' => (bool)$resultado['usuario_dio_like'],
            'total_likes' => (int)$resultado['total_likes']
        ]);

    } else {

        // Si la acción es listar, obtengo todos los usuarios
        // que dieron like a la publicación seleccionada.
        // El procedimiento obtiene esta información utilizando
        // la vista vw_likes_publicaciones.
        $usuarios = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Envío la lista de usuarios a JavaScript en formato JSON.
        // Después, JavaScript utiliza estos datos para mostrar
        // sus nombres y la fecha en que dieron like.
        echo json_encode([
            'success' => true,
            'usuarios' => $usuarios
        ]);

    }

    // Cierro el cursor para liberar los resultados de la consulta
    // y permitir que la conexión pueda utilizarse nuevamente.
    $stmt->closeCursor();

// ==========================================
// 7. MANEJAR POSIBLES ERRORES
// ==========================================

} catch (PDOException $e) {

    // Si ocurre un error relacionado con la base de datos,
    // guardo el detalle en el registro de errores de PHP.
    // Esto permite revisar qué ocurrió sin mostrar información
    // interna de la base de datos al usuario.
    error_log('Error al gestionar likes: ' . $e->getMessage());

    // El código 500 indica que ocurrió un error interno del servidor.
    http_response_code(500);

    // Envío un mensaje general para informar que la operación no se pudo realizar.
    echo json_encode([
        'error' => 'No se pudo completar la operación.'
    ]);
}
