<?php

use PHPUnit\Framework\TestCase;

/**
 * Clase auxiliar (Stub) que simula una conexión mysqli.
 * Esto evita los problemas de propiedades de solo lectura (como $insert_id)
 * presentes al intentar mockear la clase interna mysqli de PHP directamente.
 */
class TestMysqliConnection {
    public int $insert_id = 0;
    private array $prepareMap = [];

    public function setPrepareMap(array $map) {
        $this->prepareMap = $map;
    }

    public function prepare(string $query) {
        return $this->prepareMap[$query] ?? null;
    }
}

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
}

