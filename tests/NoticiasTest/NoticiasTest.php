<?php

use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;

#[AllowMockObjectsWithoutExpectations]
class NoticiasTest extends TestCase {

    // Prueba que la función falle (retorne false) al intentar publicar una noticia con un título ya existente en estado Publicada
    public function testCambiarEstadoNoticiaConTituloDuplicadoFalla() {
        // Simulación 1: El autor original de la noticia es ID 10
        $resultAutor = $this->createMock(mysqli_result::class);
        $resultAutor->method('fetch_assoc')->willReturn(['autor_id' => 10]);
        $stmtAutor = $this->createMock(mysqli_stmt::class);
        $stmtAutor->method('execute')->willReturn(true);
        $stmtAutor->method('get_result')->willReturn($resultAutor);

        // Simulación 2: La noticia que se quiere publicar tiene el título "Título Duplicado"
        $resultTitulo = $this->createMock(mysqli_result::class);
        $resultTitulo->method('fetch_assoc')->willReturn(['titulo' => 'Título Duplicado']);
        $stmtTitulo = $this->createMock(mysqli_stmt::class);
        $stmtTitulo->method('execute')->willReturn(true);
        $stmtTitulo->method('get_result')->willReturn($resultTitulo);

        // Simulación 3: Encontró 1 noticia ya publicada con el mismo título
        $resultCheck = $this->createMock(mysqli_result::class);
        $resultCheck->method('fetch_row')->willReturn([1]);
        $stmtCheck = $this->createMock(mysqli_stmt::class);
        $stmtCheck->method('execute')->willReturn(true);
        $stmtCheck->method('get_result')->willReturn($resultCheck);

        // Configuración de la conexión simulada
        $connStub = new TestMysqliConnection();
        $connStub->setPrepareMap([
            "SELECT autor_id FROM noticias WHERE id = ?" => $stmtAutor,
            "SELECT titulo FROM noticias WHERE id = ?" => $stmtTitulo,
            "SELECT COUNT(*) FROM noticias WHERE titulo = ? AND estado = 'Publicada' AND id != ?" => $stmtCheck
        ]);

        // Intentamos publicar la noticia (ID 5) por un validador (ID 20)
        $resultado = cambiarEstadoNoticia($connStub, 5, "Publicada", 20);

        // Debe retornar false indicando que fue bloqueado por duplicado
        $this->assertFalse($resultado);
    }

    // Prueba que la función sea exitosa (retorne true) al publicar una noticia con un título único
    public function testCambiarEstadoNoticiaConTituloUnicoExito() {
        // Simulación 1: El autor de la noticia es ID 10
        $resultAutor = $this->createMock(mysqli_result::class);
        $resultAutor->method('fetch_assoc')->willReturn(['autor_id' => 10]);
        $stmtAutor = $this->createMock(mysqli_stmt::class);
        $stmtAutor->method('execute')->willReturn(true);
        $stmtAutor->method('get_result')->willReturn($resultAutor);

        // Simulación 2: La noticia tiene el título "Título Único"
        $resultTitulo = $this->createMock(mysqli_result::class);
        $resultTitulo->method('fetch_assoc')->willReturn(['titulo' => 'Título Único']);
        $stmtTitulo = $this->createMock(mysqli_stmt::class);
        $stmtTitulo->method('execute')->willReturn(true);
        $stmtTitulo->method('get_result')->willReturn($resultTitulo);

        // Simulación 3: No hay duplicados en la base de datos (retorna 0)
        $resultCheck = $this->createMock(mysqli_result::class);
        $resultCheck->method('fetch_row')->willReturn([0]);
        $stmtCheck = $this->createMock(mysqli_stmt::class);
        $stmtCheck->method('execute')->willReturn(true);
        $stmtCheck->method('get_result')->willReturn($resultCheck);

        // Simulación 4: Sentencia de actualización del estado (UPDATE)
        $stmtUpdate = $this->createMock(mysqli_stmt::class);
        $stmtUpdate->method('execute')->willReturn(true);

        // Configuración de la conexión simulada
        $connStub = new TestMysqliConnection();
        $connStub->setPrepareMap([
            "SELECT autor_id FROM noticias WHERE id = ?" => $stmtAutor,
            "SELECT titulo FROM noticias WHERE id = ?" => $stmtTitulo,
            "SELECT COUNT(*) FROM noticias WHERE titulo = ? AND estado = 'Publicada' AND id != ?" => $stmtCheck,
            "UPDATE noticias SET estado = ?, fecha_publicacion = IF(? = 'Publicada', NOW(), fecha_publicacion) WHERE id = ?" => $stmtUpdate
        ]);

        // Intentamos publicar la noticia (ID 5) por un validador (ID 20)
        $resultado = cambiarEstadoNoticia($connStub, 5, "Publicada", 20);

        // Debe retornar true indicando que se publicó exitosamente
        $this->assertTrue($resultado);
    }

    // Prueba que la función insertarNoticiaCompleta guarde con éxito (retorne true) la noticia en la base de datos
    public function testInsertarNoticiaCompletaExito() {
        // Simula la sentencia de inserción y que su ejecución devuelva true
        $stmtInsert = $this->createMock(mysqli_stmt::class);
        $stmtInsert->method('execute')->willReturn(true);

        // Crea la conexión ficticia asociando la consulta de inserción
        $connStub = new TestMysqliConnection();
        $connStub->setPrepareMap([
            "INSERT INTO noticias (titulo, resumen, descripcion, imagen, estado, autor_id) VALUES (?, ?, ?, ?, ?, ?)" => $stmtInsert
        ]);

        // Ejecuta la inserción en la base de datos simulada
        $resultado = insertarNoticiaCompleta($connStub, "Noticia de Prueba", "Breve resumen", "Cuerpo de la noticia", null, "Borrador", 10);

        // Verifica que la función retorne true, indicando que se guardó correctamente
        $this->assertTrue($resultado);
    }

    // Prueba que la función insertarNoticiaCompleta devuelva false si ocurre un error en la base de datos
    public function testInsertarNoticiaCompletaFalla() {
        // Simula la sentencia de inserción y que su ejecución devuelva false
        $stmtInsert = $this->createMock(mysqli_stmt::class);
        $stmtInsert->method('execute')->willReturn(false);

        // Crea la conexión ficticia asociando la consulta de inserción
        $connStub = new TestMysqliConnection();
        $connStub->setPrepareMap([
            "INSERT INTO noticias (titulo, resumen, descripcion, imagen, estado, autor_id) VALUES (?, ?, ?, ?, ?, ?)" => $stmtInsert
        ]);

        // Ejecuta la inserción en la base de datos simulada
        $resultado = insertarNoticiaCompleta($connStub, "Noticia de Prueba", "Breve resumen", "Cuerpo de la noticia", null, "Borrador", 10);

        // Verifica que la función retorne false, indicando que ocurrió un error
        $this->assertFalse($resultado);
    }
}


