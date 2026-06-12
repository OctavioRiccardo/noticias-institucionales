<?php
// src/auth-logic.php

/**
 * Registra un nuevo usuario en el sistema junto con los roles seleccionados.
 * 
 * Realiza múltiples validaciones sobre los datos del usuario (campos requeridos,
 * formatos de nombre, apellido y correo electrónico) antes de proceder al registro.
 * La contraseña es hasheada mediante `password_hash` con el algoritmo por defecto de PHP.
 * Las relaciones de roles se insertan en la tabla intermedia `usuario_rol`.
 *
 * @param mysqli $conn Objeto de conexión activa a la base de datos MySQL.
 * @param string $nombre Nombre del usuario.
 * @param string $apellido Apellido del usuario.
 * @param string $email Correo electrónico único del usuario.
 * @param string $password Contraseña en texto plano.
 * @param array $roles_seleccionados Arreglo conteniendo los IDs de los roles asociados (ej. [1, 2]).
 * @return bool|array Retorna `true` si el registro fue exitoso o un `array` con los mensajes de error de validación/inserción.
 */
function registrarUsuario($conn, $nombre, $apellido, $email, $password, $roles_seleccionados) {
    $errores = [];
    $nombre = trim($nombre); $apellido = trim($apellido); $email = trim($email);

    // 1. Validar campos vacios
    if (empty($nombre) || empty($apellido) || empty($email) || empty($password) || empty($roles_seleccionados)) {
        $errores[] = "Todos los campos son obligatorios.";
    }

    // 2. Validar formato de nombres y apellidos
    if (!empty($nombre) && !esNombreValido($nombre)) {
        $errores[] = "El nombre contiene caracteres no permitidos. Solo usa letras.";
    }
    if (!empty($apellido) && !esNombreValido($apellido)) {
        $errores[] = "El apellido contiene caracteres no permitidos. Solo usa letras.";
    }

    // 3. Validar formato de email
    if (!empty($email) && !esEmailValido($email)) {
        $errores[] = "El formato del correo electronico no es valido.";
    }

    // Si no existen errores de formato, procedemos con el registro
    if (empty($errores)) {
        // Encriptar la contraseña usando el algoritmo por defecto (bcrypt)
        $passwordHash = password_hash($password, PASSWORD_DEFAULT);
        
        // Preparar sentencia SQL para prevenir inyección de SQL
        $stmt = $conn->prepare("INSERT INTO usuarios (nombre, apellido, email, password) VALUES (?, ?, ?, ?)");
        $stmt->bind_param("ssss", $nombre, $apellido, $email, $passwordHash);
        
        if ($stmt->execute()) {
            $usuario_id = $conn->insert_id;
            
            // Registrar los roles asociados al usuario en la tabla relacional
            foreach ($roles_seleccionados as $rol_id) {
                $stmt_rol = $conn->prepare("INSERT INTO usuario_rol (usuario_id, rol_id) VALUES (?, ?)");
                $stmt_rol->bind_param("ii", $usuario_id, $rol_id);
                $stmt_rol->execute();
            }
            return true;
        } else { 
            // Esto ocurre habitualmente por la restricción UNIQUE del email en la BD
            $errores[] = "Error: El email ya se encuentra registrado en el sistema."; 
        }
    }
    return $errores;
}

/**
 * Autentica un usuario en el sistema validando sus credenciales y configurando la sesión.
 * 
 * Busca al usuario por su email y obtiene sus roles mediante un `GROUP_CONCAT` en una sola consulta.
 * Valida la contraseña comparando el hash almacenado utilizando `password_verify`.
 * Si es correcto, almacena los datos básicos y los roles del usuario en la sesión (`$_SESSION`).
 *
 * @param mysqli $conn Objeto de conexión activa a la base de datos MySQL.
 * @param string $email Correo electrónico del usuario.
 * @param string $password Contraseña ingresada por el usuario.
 * @return bool|array Retorna `true` si las credenciales son correctas y se inicia la sesión, o un `array` con un mensaje de error.
 */
function iniciarSesion($conn, $email, $password) {
    // Obtenemos los datos del usuario y concatenamos los IDs de sus roles en una sola fila
    $stmt = $conn->prepare("SELECT u.*, GROUP_CONCAT(ur.rol_id) as roles_ids 
                            FROM usuarios u 
                            LEFT JOIN usuario_rol ur ON u.id = ur.usuario_id 
                            WHERE u.email = ? 
                            GROUP BY u.id");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $user = $stmt->get_result()->fetch_assoc();

    // Verificamos si existe el usuario y si coincide la contraseña encriptada
    if ($user && password_verify($password, $user['password'])) {
        // Almacenar información de usuario en variables de sesión global
        $_SESSION['usuario_id'] = $user['id'];
        $_SESSION['usuario_nombre'] = $user['nombre'];
        $_SESSION['usuario_apellido'] = $user['apellido'];
        $_SESSION['usuario_email'] = $user['email']; 
        
        // Convertimos la lista de roles (separados por coma) a un arreglo
        $roles_raw = $user['roles_ids'] ?? '';
        $_SESSION['usuario_roles'] = !empty($roles_raw) ? explode(',', $roles_raw) : [];
        
        return true;
    }
    
    // Retornamos un arreglo de errores en caso de fallo de autenticación
    return ["Credenciales incorrectas."];
}