<?php

use PHPUnit\Framework\TestCase;

/**
 * Clase de pruebas unitarias para la lógica de noticias en noticias-logic.php.
 * Utiliza dobles de prueba (Mocks) para simular el comportamiento de la base de datos.
 */
class NoticiasLogicTest extends TestCase {

    /**
     * Prueba que un usuario NO pueda auto-validar su propia noticia (debe retornar false).
     * Esta es una regla de seguridad crítica del sistema.
     */
    public function testCambiarEstadoNoticiaAutovalidacionDenegada() {
        $idNoticia = 5;
        $idUsuarioLogueado = 10;
        $autorIdNoticia = 10; // Mismo ID de usuario (intenta auto-validarse)

        // 1. Crear Mock del resultado de la base de datos
        $resultMock = $this->createMock(mysqli_result::class);
        $resultMock->method('fetch_assoc')->willReturn(['autor_id' => $autorIdNoticia]);

        // 2. Crear Mock de stmt para la consulta de autoría
        $stmtAutor = $this->createMock(mysqli_stmt::class);
        $stmtAutor->method('execute')->willReturn(true);
        $stmtAutor->method('get_result')->willReturn($resultMock);

        // 3. Crear Mock de la conexión mysqli
        $conn = $this->createMock(mysqli::class);
        $conn->method('prepare')
             ->with("SELECT autor_id FROM noticias WHERE id = ?")
             ->willReturn($stmtAutor);

        // Ejecutar y asertar que retorne false (operación bloqueada)
        $resultado = cambiarEstadoNoticia($conn, $idNoticia, 'Publicada', $idUsuarioLogueado);
        $this->assertFalse($resultado);
    }

    /**
     * Prueba que un usuario sí pueda validar una noticia redactada por OTRO autor.
     */
    public function testCambiarEstadoNoticiaPermitidoParaOtroAutor() {
        $idNoticia = 5;
        $idUsuarioLogueado = 20; // Validador
        $autorIdNoticia = 10; // Editor original (diferente usuario)

        // 1. Mock de consulta de autoría
        $resultMock = $this->createMock(mysqli_result::class);
        $resultMock->method('fetch_assoc')->willReturn(['autor_id' => $autorIdNoticia]);

        $stmtAutor = $this->createMock(mysqli_stmt::class);
        $stmtAutor->method('execute')->willReturn(true);
        $stmtAutor->method('get_result')->willReturn($resultMock);

        // 2. Mock de stmt para la actualización de estado (UPDATE)
        $stmtUpdate = $this->createMock(mysqli_stmt::class);
        $stmtUpdate->method('execute')->willReturn(true);

        // 3. Mock de conexión
        $conn = $this->createMock(mysqli::class);
        
        // Configuramos prepare() para responder a la consulta de autoría y luego al update
        $conn->expects($this->exactly(2))
             ->method('prepare')
             ->willReturnMap([
                 ["SELECT autor_id FROM noticias WHERE id = ?", $stmtAutor],
                 ["UPDATE noticias SET estado = ?, fecha_publicacion = IF(? = 'Publicada', NOW(), fecha_publicacion) WHERE id = ?", $stmtUpdate]
             ]);

        // Ejecutar y asertar que retorne true (operación permitida y ejecutada con éxito)
        $resultado = cambiarEstadoNoticia($conn, $idNoticia, 'Publicada', $idUsuarioLogueado);
        $this->assertTrue($resultado);
    }

    /**
     * Prueba el cambio de estado simple (ej. a Anulada) que no requiere verificación de autoría.
     */
    public function testCambiarEstadoNoticiaSimple() {
        $idNoticia = 5;
        $idUsuarioLogueado = 10;

        // 1. Mock de stmt para el UPDATE
        $stmtUpdate = $this->createMock(mysqli_stmt::class);
        $stmtUpdate->method('execute')->willReturn(true);

        // 2. Mock de conexión
        $conn = $this->createMock(mysqli::class);
        $conn->method('prepare')
             ->with("UPDATE noticias SET estado = ?, fecha_publicacion = IF(? = 'Publicada', NOW(), fecha_publicacion) WHERE id = ?")
             ->willReturn($stmtUpdate);

        // Cambiar a 'Anulada' no requiere verificar autoría en noticias-logic.php
        $resultado = cambiarEstadoNoticia($conn, $idNoticia, 'Anulada', $idUsuarioLogueado);
        $this->assertTrue($resultado);
    }
}
