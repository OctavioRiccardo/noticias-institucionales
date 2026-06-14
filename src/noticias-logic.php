<?php
// src/noticias-logic.php

/**
 * Obtiene todas las noticias cuyo estado es 'Publicada' ordenadas por su fecha de publicación.
 * 
 * Se realiza un `JOIN` con la tabla `usuarios` para obtener el nombre completo del autor.
 *
 * @param mysqli $conn Objeto de conexión a la base de datos MySQL.
 * @return mysqli_result|bool Resultado de la consulta con la lista de noticias públicas o false en caso de error.
 */
function obtenerNoticiasPublicas($conn) {
    $sql = "SELECT n.*, u.nombre, u.apellido 
            FROM noticias n 
            JOIN usuarios u ON n.autor_id = u.id 
            WHERE n.estado = 'Publicada' 
            ORDER BY n.fecha_publicacion DESC";
    return mysqli_query($conn, $sql);
}

/**
 * Obtiene el detalle de una noticia específica por su ID.
 * 
 * Incluye la información del autor que la redactó mediante un `JOIN`.
 *
 * @param mysqli $conn Objeto de conexión a la base de datos MySQL.
 * @param int $id El identificador único de la noticia.
 * @return array|null Fila con los datos de la noticia o `null` si no se encuentra.
 */
function obtenerNoticiaPorId($conn, $id) {
    $stmt = $conn->prepare("SELECT n.*, u.nombre, u.apellido 
                            FROM noticias n 
                            JOIN usuarios u ON n.autor_id = u.id 
                            WHERE n.id = ?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    return $stmt->get_result()->fetch_assoc();
}

/**
 * Inserta una nueva noticia en la base de datos, gestionando la carga de imágenes.
 * 
 * Si se incluye un archivo de imagen válido, la función genera un nombre único
 * utilizando `uniqid()`, crea el directorio `uploads` si no existe, y guarda el archivo.
 *
 * @param mysqli $conn Objeto de conexión a la base de datos MySQL.
 * @param string $titulo Título de la noticia.
 * @param string $resumen Resumen o bajada breve de la noticia.
 * @param string $contenido Contenido completo de la noticia.
 * @param array|null $imagen_file Arreglo que representa el archivo subido en `$_FILES['imagen']`.
 * @param string $estado Estado inicial ('Borrador' o 'Lista para Validación').
 * @param int $autor_id ID del usuario (editor) que está redactando la noticia.
 * @return bool True si se insertó con éxito, False de lo contrario.
 */
function insertarNoticiaCompleta($conn, $titulo, $resumen, $contenido, $imagen_file, $estado, $autor_id) {
    // REGLA DE NEGOCIO: No puede existir más de una noticia con el mismo título en estado Publicada
    if ($estado === 'Publicada') {
        $stmt_check = $conn->prepare("SELECT COUNT(*) FROM noticias WHERE titulo = ? AND estado = 'Publicada'");
        $stmt_check->bind_param("s", $titulo);
        $stmt_check->execute();
        $res = $stmt_check->get_result()->fetch_row();
        if ($res && $res[0] > 0) {
            return false; // Ya existe una noticia publicada con ese mismo título
        }
    }

    $nombre_imagen = null;
    // Verificar si se subió un archivo y no contiene errores
    if (isset($imagen_file) && $imagen_file['error'] === 0) {
        $ext = pathinfo($imagen_file['name'], PATHINFO_EXTENSION);
        $nombre_imagen = time() . "_" . uniqid() . "." . $ext;
        if (!is_dir('uploads')) { 
            mkdir('uploads', 0777, true); 
        }
        move_uploaded_file($imagen_file['tmp_name'], "uploads/" . $nombre_imagen);
    }
    $stmt = $conn->prepare("INSERT INTO noticias (titulo, resumen, descripcion, imagen, estado, autor_id) VALUES (?, ?, ?, ?, ?, ?)");
    $stmt->bind_param("sssssi", $titulo, $resumen, $contenido, $nombre_imagen, $estado, $autor_id);
    return $stmt->execute();
}


/**
 * Obtiene los borradores creados por un editor específico.
 * 
 * Retorna las noticias que pertenecen al autor y cuyo estado sea 'Borrador' o 'Para Corrección'
 * (devueltas por el validador).
 *
 * @param mysqli $conn Objeto de conexión a la base de datos MySQL.
 * @param int $autor_id ID del usuario editor dueño de las noticias.
 * @return mysqli_result Resultado de la consulta con la lista de borradores del usuario.
 */
function obtenerMisBorradores($conn, $autor_id) {
    $stmt = $conn->prepare("SELECT * FROM noticias 
                            WHERE autor_id = ? 
                            AND (estado = 'Borrador' OR estado = 'Para Corrección') 
                            ORDER BY fecha_creacion DESC");
    $stmt->bind_param("i", $autor_id);
    $stmt->execute();
    return $stmt->get_result();
}

/**
 * Obtiene una noticia para su edición.
 * 
 * Si se provee `$autor_id` mayor a 0, la consulta se restringe estrictamente a que el autor coincida.
 * Esto previene que editores modifiquen borradores de otros editores alterando el ID en la URL.
 *
 * @param mysqli $conn Objeto de conexión a la base de datos MySQL.
 * @param int $id ID de la noticia a recuperar.
 * @param int $autor_id Opcional. ID del autor para restringir el acceso.
 * @return array|null Fila con los datos de la noticia o `null` si no existe o no pertenece al autor.
 */
function obtenerNoticiaParaEditar($conn, $id, $autor_id = 0) {
    if ($autor_id > 0) {
        $stmt = $conn->prepare("SELECT n.*, u.nombre, u.apellido FROM noticias n JOIN usuarios u ON n.autor_id = u.id WHERE n.id = ? AND n.autor_id = ?");
        $stmt->bind_param("ii", $id, $autor_id);
    } else {
        $stmt = $conn->prepare("SELECT n.*, u.nombre, u.apellido FROM noticias n JOIN usuarios u ON n.autor_id = u.id WHERE n.id = ?");
        $stmt->bind_param("i", $id);
    }
    $stmt->execute();
    return $stmt->get_result()->fetch_assoc();
}

/**
 * Obtiene las noticias en espera de validación.
 * 
 * Retorna todas las noticias que tienen el estado 'Lista para Validación'.
 *
 * @param mysqli $conn Objeto de conexión a la base de datos MySQL.
 * @return mysqli_result|bool Resultado de la consulta con las noticias pendientes de revisión.
 */
function obtenerNoticiasPendientes($conn) {
    $sql = "SELECT n.*, u.nombre, u.apellido FROM noticias n JOIN usuarios u ON n.autor_id = u.id WHERE n.estado = 'Lista para Validación' ORDER BY n.fecha_creacion DESC";
    return mysqli_query($conn, $sql);
}

/**
 * Cambia el estado de una noticia, aplicando una regla de seguridad de no auto-validación.
 * 
 * Si un validador intenta aprobar o rechazar (cambiar a 'Publicada' o 'Para Corrección')
 * una noticia de la cual él mismo es el autor, la función deniega la operación.
 * Si el estado pasa a 'Publicada', se actualiza adicionalmente el campo `fecha_publicacion` con la hora actual.
 *
 * @param mysqli $conn Objeto de conexión a la base de datos MySQL.
 * @param int $id ID de la noticia a cambiar.
 * @param string $nuevo_estado El estado destino ('Publicada', 'Para Corrección', 'Anulada', etc.).
 * @param int $usuario_id ID del usuario logueado que realiza el cambio.
 * @return bool True si la operación se realizó con éxito, False si falló la validación de seguridad o la inserción en BD.
 */
function cambiarEstadoNoticia($conn, $id, $nuevo_estado, $usuario_id) {
    /*
     * BLOQUEO DE SEGURIDAD EN SERVIDOR
     * Se comprueba la autoría antes de permitir un cambio de estado propio de un validador.
     * Si el nuevo estado es 'Publicada' o 'Para Corrección', consultamos quién es el dueño.
     */
    if ($nuevo_estado === 'Publicada' || $nuevo_estado === 'Para Corrección') {
        $stmt_autor = $conn->prepare("SELECT autor_id FROM noticias WHERE id = ?");
        $stmt_autor->bind_param("i", $id);
        $stmt_autor->execute();
        $resultado = $stmt_autor->get_result()->fetch_assoc();
        
        if ($resultado && $resultado['autor_id'] == $usuario_id) {
            // Si el ID del autor coincide con el ID del usuario en sesión, se rechaza la operación.
            return false; 
        }
    }

    // REGLA DE NEGOCIO: No puede existir más de una noticia con el mismo título en estado Publicada
    if ($nuevo_estado === 'Publicada') {
        // 1. Obtener el título de la noticia actual
        $stmt_titulo = $conn->prepare("SELECT titulo FROM noticias WHERE id = ?");
        $stmt_titulo->bind_param("i", $id);
        $stmt_titulo->execute();
        $noticia_actual = $stmt_titulo->get_result()->fetch_assoc();
        
        if ($noticia_actual) {
            $titulo = $noticia_actual['titulo'];
            // 2. Verificar si ya existe otra noticia con el mismo título en estado 'Publicada'
            $stmt_check = $conn->prepare("SELECT COUNT(*) FROM noticias WHERE titulo = ? AND estado = 'Publicada' AND id != ?");
            $stmt_check->bind_param("si", $titulo, $id);
            $stmt_check->execute();
            $res = $stmt_check->get_result()->fetch_row();
            if ($res && $res[0] > 0) {
                return false; // Ya existe otra noticia publicada con ese mismo título
            }
        }
    }

    /*
     * EJECUCIÓN DEL CAMBIO DE ESTADO
     * Si supera la barrera de seguridad, procede con la actualización normal en la base de datos.
     * Si el nuevo estado es 'Publicada', establecemos la fecha de publicación con NOW().
     */
    $stmt = $conn->prepare("UPDATE noticias SET estado = ?, fecha_publicacion = IF(? = 'Publicada', NOW(), fecha_publicacion) WHERE id = ?");
    $stmt->bind_param("ssi", $nuevo_estado, $nuevo_estado, $id);
    return $stmt->execute();
}


/**
 * Obtiene el historial reciente de las noticias creadas por un usuario.
 * 
 * Limita el resultado a las últimas 5 publicaciones ordenadas cronológicamente.
 *
 * @param mysqli $conn Objeto de conexión a la base de datos MySQL.
 * @param int $usuario_id ID del usuario editor.
 * @return mysqli_result Resultado de la consulta con el historial reciente.
 */
function obtenerHistorialUsuario($conn, $usuario_id) {
    $stmt = $conn->prepare("SELECT * FROM noticias WHERE autor_id = ? ORDER BY fecha_creacion DESC LIMIT 5");
    $stmt->bind_param("i", $usuario_id);
    $stmt->execute();
    return $stmt->get_result();
}

/**
 * Actualiza una noticia existente en la base de datos gestionando imágenes.
 * 
 * Soporta tres flujos posibles para la imagen de portada:
 * 1. El usuario subió una imagen nueva (reemplaza la existente).
 * 2. El usuario solicitó eliminar la imagen existente (pone a NULL en BD) mediante `$borrar_imagen === '1'`.
 * 3. El usuario mantuvo la imagen actual sin cambios (no subió nueva ni solicitó eliminar).
 *
 * @param mysqli $conn Objeto de conexión a la base de datos MySQL.
 * @param int $id ID de la noticia a actualizar.
 * @param string $titulo Título de la noticia.
 * @param string $resumen Resumen breve.
 * @param string $contenido Contenido completo (descripción).
 * @param array|null $imagen_file Arreglo de $_FILES['imagen'] subida.
 * @param string $estado Estado destino ('Borrador' o 'Lista para Validación').
 * @param string $borrar_imagen Indicador string ('1' para borrar la imagen actual, '0' en caso contrario).
 * @return bool True si se actualizó con éxito, False de lo contrario.
 */
function actualizarNoticiaCompleta($conn, $id, $titulo, $resumen, $contenido, $imagen_file, $estado, $borrar_imagen = '0') {
    
    // 1. ¿Subió una foto nueva? (Prioridad alta)
    if (isset($imagen_file) && $imagen_file['error'] === 0) {
        $ext = pathinfo($imagen_file['name'], PATHINFO_EXTENSION);
        $nombre_imagen = time() . "_" . uniqid() . "." . $ext;
        move_uploaded_file($imagen_file['tmp_name'], "uploads/" . $nombre_imagen);
        
        $stmt = $conn->prepare("UPDATE noticias SET titulo = ?, resumen = ?, descripcion = ?, imagen = ?, estado = ? WHERE id = ?");
        $stmt->bind_param("sssssi", $titulo, $resumen, $contenido, $nombre_imagen, $estado, $id);
    } 
    // 2. ¿NO subió nada pero apretó el TACHITO (el mensajero mandó '1')?
    elseif ($borrar_imagen === '1') {
        $stmt = $conn->prepare("UPDATE noticias SET titulo = ?, resumen = ?, descripcion = ?, imagen = NULL, estado = ? WHERE id = ?");
        $stmt->bind_param("ssssi", $titulo, $resumen, $contenido, $estado, $id);
    } 
    // 3. ¿NO subió nada y NO tocó el tachito? (Mantenemos la foto vieja)
    else {
        $stmt = $conn->prepare("UPDATE noticias SET titulo = ?, resumen = ?, descripcion = ?, estado = ? WHERE id = ?");
        $stmt->bind_param("ssssi", $titulo, $resumen, $contenido, $estado, $id);
    }
    
    return $stmt->execute();
}