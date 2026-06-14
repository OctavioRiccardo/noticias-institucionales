<?php

use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;

// Este atributo indica a PHPUnit 13 que permita el uso de createMock()
// sin configurar expectativas obligatorias (como expects()), silenciando las advertencias.
#[AllowMockObjectsWithoutExpectations]
class NoticiasTest extends TestCase {

    // Prueba que falle si se quiere publicar una noticia con un título ya existente en estado Publicada
    public function testInsertarNoticiaConTituloDuplicadoEnEstadoPublicadaFalla() {
        // Simula que la consulta SELECT COUNT devuelva 1 (que el título ya existe publicado)
        $resultMock = $this->createMock(mysqli_result::class);
        $resultMock->method('fetch_row')->willReturn([1]);

        // Simula la sentencia de verificación
        $stmtCheckMock = $this->createMock(mysqli_stmt::class);
        $stmtCheckMock->method('execute')->willReturn(true);
        $stmtCheckMock->method('get_result')->willReturn($resultMock);

        // Crea la conexión ficticia y le asocia la consulta de verificación
        $connStub = new TestMysqliConnection();
        $connStub->setPrepareMap([
            "SELECT COUNT(*) FROM noticias WHERE titulo = ? AND estado = 'Publicada'" => $stmtCheckMock
        ]);

        // Ejecuta la inserción con estado Publicada
        $resultado = insertarNoticiaCompleta($connStub, "Becas Estudiantiles 2026", "Resumen", "Contenido", null, "Publicada", 10);

        // Verifica que retorne false debido al título duplicado
        $this->assertFalse($resultado);
    }

    // Prueba que funcione si publicamos una noticia con un título único (no duplicado)
    public function testInsertarNoticiaConTituloUnicoEnEstadoPublicadaExito() {
        // Simula que la consulta SELECT COUNT devuelva 0 (título no existe publicado)
        $resultMock = $this->createMock(mysqli_result::class);
        $resultMock->method('fetch_row')->willReturn([0]);

        // Simula la sentencia de verificación
        $stmtMock = $this->createMock(mysqli_stmt::class);
        $stmtMock->method('execute')->willReturn(true);
        $stmtMock->method('get_result')->willReturn($resultMock);

        // Simula la sentencia de inserción final
        $stmtInsertMock = $this->createMock(mysqli_stmt::class);
        $stmtInsertMock->method('execute')->willReturn(true);

        // Crea la conexión ficticia y asocia ambas consultas
        $connStub = new TestMysqliConnection();
        $connStub->setPrepareMap([
            "SELECT COUNT(*) FROM noticias WHERE titulo = ? AND estado = 'Publicada'" => $stmtMock,
            "INSERT INTO noticias (titulo, resumen, descripcion, imagen, estado, autor_id) VALUES (?, ?, ?, ?, ?, ?)" => $stmtInsertMock
        ]);

        // Ejecuta la inserción
        $resultado = insertarNoticiaCompleta($connStub, "Becas Estudiantiles 2026 - Único", "Resumen", "Contenido", null, "Publicada", 10);

        // Verifica que retorne true indicando éxito
        $this->assertTrue($resultado);
    }
}
