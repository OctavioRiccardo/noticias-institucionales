<?php

use PHPUnit\Framework\TestCase;

/**
 * Clase de pruebas unitarias para la lógica de noticias en noticias-logic.php.
 * 
 * Explicación general de Dobles de Prueba en PHPUnit:
 * 1. Mock/Stub de mysqli_stmt: Creamos un objeto simulado (doble) de la clase nativa
 *    mysqli_stmt para poder definir cómo responderá cuando la función ejecute sus métodos,
 *    sin necesidad de conectarse realmente al servidor MySQL.
 * 2. TestMysqliConnection: Es una clase "Stub" de conexión (definida en tests/bootstrap.php)
 *    que le pasamos a la función. Su trabajo es interceptar la consulta SQL de inserción
 *    y devolver nuestro mysqli_stmt simulado.
 */
class NoticiasTest extends TestCase {

    /**
     * Prueba el comportamiento de la función insertarNoticiaCompleta al guardar
     * una noticia que no incluye archivo de imagen adjunto.
     */
    public function testInsertarNoticiaCompletaSinImagen() {
        
      
        // PASO 1: PREPARACIÓN (Arrange)
       
        // Datos de ejemplo para simular la noticia que el editor desea guardar
        $titulo = "Estudiantes de la UNSL crean satélite educativo";
        $resumen = "Un hito histórico para la Tecnicatura Web.";
        $contenido = "El proyecto fue desarrollado utilizando microprocesadores avanzados y tecnología de vanguardia.";
        $imagenFile = null; // No se sube ninguna imagen en esta prueba
        $estado = "Borrador";
        $autorId = 10;


        // PASO 2: CREACIÓN DE MOCKS / STUBS (Dobles)
        // A. Creamos un "Mock" (objeto simulado) de la clase mysqli_stmt
        // Este objeto reemplaza a la sentencia preparada real de MySQL
        $stmtMock = $this->createMock(mysqli_stmt::class);

        // B. Configuramos el comportamiento del Mock:
        // Le indicamos que cuando se llame a su método 'execute()',
        // retorne inmediatamente true (simulando que la inserción en la BD fue exitosa).
        $stmtMock->method('execute')->willReturn(true);

        // C. Creamos un "Stub" de nuestra conexión mysqli simulada (TestMysqliConnection)
        // Esta clase está definida en el archivo bootstrap de pruebas.
        $connStub = new TestMysqliConnection();

        // D. Configuramos el mapa de consultas de la conexión simulada:
        // Le decimos que cuando prepare la consulta SQL exacta de inserción de noticias,
        // nos devuelva el objeto $stmtMock que acabamos de configurar en el paso A y B.
        $connStub->setPrepareMap([
            "INSERT INTO noticias (titulo, resumen, descripcion, imagen, estado, autor_id) VALUES (?, ?, ?, ?, ?, ?)" => $stmtMock
        ]);

   
        // PASO 3: EJECUCIÓN (Act)
        // Llamamos a la función original que queremos testear, pasándole nuestra
        // conexión simulada y los datos de prueba
        $resultado = insertarNoticiaCompleta($connStub,$titulo,$resumen,$contenido,$imagenFile,$estado,$autorId);

      
        // PASO 4: ASERCIÓN (Assert)
        // Verificamos que la función nos retorne true, confirmando que la lógica interna 
        // procesó los parámetros e invocó execute() correctamente en nuestro statement simulado
        $this->assertTrue($resultado,"La función insertarNoticiaCompleta debería retornar true si la base de datos confirma el éxito.");
    }


    
}


