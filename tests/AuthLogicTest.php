<?php

use PHPUnit\Framework\TestCase;

/**
 * Clase de pruebas unitarias para la lógica de autenticación en auth-logic.php.
 */
class AuthLogicTest extends TestCase {


    /**
     * Prueba la validación de campos vacíos en el registro.
     */
    public function testRegistrarUsuarioCamposVacios() {
        $conn = new TestMysqliConnection();

        $resultado = registrarUsuario($conn, '', 'Dágata', 'p2usbloco@gmail.com', 'c123456', [1]);
        $this->assertIsArray($resultado);
        $this->assertContains("Todos los campos son obligatorios.", $resultado);
    }

    /**
     * Prueba la validación de formato de nombre inválido en el registro.
     */
    public function testRegistrarUsuarioNombreInvalido() {
        $conn = new TestMysqliConnection();

        $resultado = registrarUsuario($conn, 'Brisa123', 'Dágata', 'p2usbloco@gmail.com', 'c123456', [1]);
        $this->assertIsArray($resultado);
        $this->assertContains("El nombre contiene caracteres no permitidos. Solo usa letras.", $resultado);
    }

    /**
     * Prueba la validación de formato de email inválido en el registro.
     */
    public function testRegistrarUsuarioEmailInvalido() {
        $conn = new TestMysqliConnection();

        $resultado = registrarUsuario($conn, 'Brisa', 'Dágata', 'email_invalido', 'c123456', [1]);
        $this->assertIsArray($resultado);
        $this->assertContains("El formato del correo electronico no es valido.", $resultado);
    }

    /**
     * Prueba el registro exitoso simulando la base de datos con un Connection Stub y Mocks de Statement.
     */
    public function testRegistrarUsuarioExitoso() {
        // 1. Crear Mock de mysqli_stmt para la inserción de usuario
        $stmtUsuario = $this->createMock(mysqli_stmt::class);
        $stmtUsuario->method('execute')->willReturn(true);

        // 2. Crear Mock de mysqli_stmt para la inserción de roles
        $stmtRol = $this->createMock(mysqli_stmt::class);
        $stmtRol->method('execute')->willReturn(true);

        // 3. Crear instancia del Connection Stub
        $conn = new TestMysqliConnection();
        $conn->insert_id = 42;
        $conn->setPrepareMap([
            'INSERT INTO usuarios (nombre, apellido, email, password) VALUES (?, ?, ?, ?)' => $stmtUsuario,
            'INSERT INTO usuario_rol (usuario_id, rol_id) VALUES (?, ?)' => $stmtRol
        ]);

        $resultado = registrarUsuario($conn, 'Rebecca', 'Ford', 'lotus@gmail.com', 'Warframe#2013', [1]);

        $this->assertTrue($resultado);
    }

    /**
     * Prueba el registro fallido cuando el email ya existe en el sistema.
     */
    public function testRegistrarUsuarioEmailDuplicado() {
        // 1. Crear Mock de mysqli_stmt que devuelve false al ejecutar (simula error de restricción UNIQUE)
        $stmtUsuario = $this->createMock(mysqli_stmt::class);
        $stmtUsuario->method('execute')->willReturn(false);

        // 2. Crear instancia del Connection Stub
        $conn = new TestMysqliConnection();
        $conn->setPrepareMap([
            'INSERT INTO usuarios (nombre, apellido, email, password) VALUES (?, ?, ?, ?)' => $stmtUsuario
        ]);

        $resultado = registrarUsuario($conn, 'Rebecca', 'Ford', 'lotus@gmail.com', 'Warframe#2013', [1]);

        $this->assertIsArray($resultado);
        $this->assertContains("Error: El email ya se encuentra registrado en el sistema.", $resultado);
    }

    /**
     * Prueba el inicio de sesión exitoso con credenciales correctas.
     */
    public function testIniciarSesionExitoso() {
        // Limpiamos la sesión antes de la prueba
        $_SESSION = [];

        $hashedPassword = password_hash('secreto123', PASSWORD_DEFAULT);
        $userData = [
            'id' => 10,
            'nombre' => 'Grace',
            'apellido' => 'Ashcroft',
            'email' => 'requiem9@gmail.com',
            'password' => $hashedPassword,
            'roles_ids' => '1,2'
        ];

        // 1. Mock de mysqli_result que retorna los datos del usuario
        $resultMock = $this->createMock(mysqli_result::class);
        $resultMock->method('fetch_assoc')->willReturn($userData);

        // 2. Mock de mysqli_stmt
        $stmtMock = $this->createMock(mysqli_stmt::class);
        $stmtMock->method('execute')->willReturn(true);
        $stmtMock->method('get_result')->willReturn($resultMock);

        // 3. Stub de la conexión
        $conn = new TestMysqliConnection();
        $conn->setPrepareMap([
            "SELECT u.*, GROUP_CONCAT(ur.rol_id) as roles_ids 
                            FROM usuarios u 
                            LEFT JOIN usuario_rol ur ON u.id = ur.usuario_id 
                            WHERE u.email = ? 
                            GROUP BY u.id" => $stmtMock
        ]);

        // Ejecutar inicio de sesión con contraseña correcta
        $resultado = iniciarSesion($conn, 'requiem9@gmail.com', 'secreto123');

        $this->assertTrue($resultado);
        
        // Verificar que las variables de sesión se establecieron correctamente
        $this->assertSame(10, $_SESSION['usuario_id']);
        $this->assertSame('Grace', $_SESSION['usuario_nombre']);
        $this->assertSame('Ashcroft', $_SESSION['usuario_apellido']);
        $this->assertSame('requiem9@gmail.com', $_SESSION['usuario_email']);
        $this->assertSame(['1', '2'], $_SESSION['usuario_roles']);
    }

    /**
     * Prueba el inicio de sesión fallido debido a contraseña incorrecta.
     */
    public function testIniciarSesionContrasenaIncorrecta() {
        $_SESSION = [];

        $hashedPassword = password_hash('secreto123', PASSWORD_DEFAULT);
        $userData = [
            'id' => 10,
            'nombre' => 'Grace',
            'apellido' => 'Ashcroft',
            'email' => 'requiem9@gmail.com',
            'password' => $hashedPassword,
            'roles_ids' => '1,2'
        ];

        $resultMock = $this->createMock(mysqli_result::class);
        $resultMock->method('fetch_assoc')->willReturn($userData);

        $stmtMock = $this->createMock(mysqli_stmt::class);
        $stmtMock->method('get_result')->willReturn($resultMock);

        $conn = new TestMysqliConnection();
        $conn->setPrepareMap([
            "SELECT u.*, GROUP_CONCAT(ur.rol_id) as roles_ids 
                            FROM usuarios u 
                            LEFT JOIN usuario_rol ur ON u.id = ur.usuario_id 
                            WHERE u.email = ? 
                            GROUP BY u.id" => $stmtMock
        ]);

        // Intentar iniciar sesión con contraseña equivocada
        $resultado = iniciarSesion($conn, 'requiem9@gmail.com', 'password_erronea');

        $this->assertIsArray($resultado);
        $this->assertContains("Credenciales incorrectas.", $resultado);
        $this->assertEmpty($_SESSION);
    }

    /**
     * Prueba el inicio de sesión cuando el correo electrónico no está registrado.
     */
    public function testIniciarSesionUsuarioNoEncontrado() {
        $_SESSION = [];

        // Retornará null indicando que el correo no existe en la base de datos
        $resultMock = $this->createMock(mysqli_result::class);
        $resultMock->method('fetch_assoc')->willReturn(null);

        $stmtMock = $this->createMock(mysqli_stmt::class);
        $stmtMock->method('get_result')->willReturn($resultMock);

        $conn = new TestMysqliConnection();
        $conn->setPrepareMap([
            "SELECT u.*, GROUP_CONCAT(ur.rol_id) as roles_ids 
                            FROM usuarios u 
                            LEFT JOIN usuario_rol ur ON u.id = ur.usuario_id 
                            WHERE u.email = ? 
                            GROUP BY u.id" => $stmtMock
        ]);

        $resultado = iniciarSesion($conn, 'noexisto@gmail.com', 'cualquiera123');

        $this->assertIsArray($resultado);
        $this->assertContains("Credenciales incorrectas.", $resultado);
        $this->assertEmpty($_SESSION);
    }
}


