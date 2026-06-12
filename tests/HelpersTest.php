<?php

use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * Clase de pruebas unitarias para las funciones de utilidad y validación de helpers.php.
 */
class HelpersTest extends TestCase {

    /**
     * Prueba la sanitización de HTML para evitar XSS.
     */
    public function testEscaparHtml() {
        $dirty = "<script>alert('xss');</script>";
        $clean = e($dirty);
        
        $this->assertStringNotContainsString("<script>", $clean);
        $this->assertStringContainsString("&lt;script&gt;", $clean);
        
        // Comprobar manejo de null
        $this->assertSame("", e(null));
    }

    /**
     * Provee datos válidos e inválidos para probar la función esNombreValido.
     */
    public static function nombresProvider(): array {
        return [
            // Nombres válidos
            ['Brisa', true],
            ['Brisa Dágata', true],
            ["O'Connor", true],
            ['Juan Ramón', true],
            ['치피', true], // Letras en coreano (Hangul) son válidas
            
            // Nombres inválidos
            ['Juan123', false], // Contiene números
            ['Juan@UNSL', false], // Contiene caracteres especiales no permitidos
            ['   ', false], // Solo espacios (no contiene letras)
            ['', false], // Vacío
        ];
    }

    #[DataProvider('nombresProvider')]
    public function testEsNombreValido(string $nombre, bool $esperado) {
        $this->assertSame($esperado, esNombreValido($nombre));
    }

    /**
     * Provee datos válidos e inválidos para probar la función esTextoNoticiaValido.
     */
    public static function textosProvider(): array {
        return [
            // Textos válidos
            ['Esta es una noticia institucional válida.', true],
            ['¡Gran noticia! Se inaugura un nuevo bloque.', true],
            ['Chipi News - Noticias de la UNSL.', true], // Sin símbolos/emojis, solo letras latinas y puntuación
            ['Coreano: 한국어 es genial.', true], // Letras en coreano son válidas
            
            // Textos inválidos
            ['Chipi News 치피 📰 - Noticias de la UNSL.', false], // Contiene emoji (Categoría Symbol \p{S}), no permitido por la regex
            ['1234567890', false], // Solo números (debe tener al menos una letra)
            ['!!!???', false], // Solo signos (debe tener al menos una letra)
            ['', false], // Vacío
        ];
    }


    #[DataProvider('textosProvider')]
    public function testEsTextoNoticiaValido(string $texto, bool $esperado) {
        $this->assertSame($esperado, esTextoNoticiaValido($texto));
    }

    /**
     * Provee datos válidos e inválidos para probar la función esEmailValido.
     */
    public static function emailsProvider(): array {
        return [
            // Emails válidos
            ['requiem9@gmail.com', true],
            ['raccoon@gmail.com', true],
            ['lotus@gmail.com', true],
            ['test.user+tag@unsl.edu.ar', true],
            
            // Emails inválidos
            ['email_invalido', false],
            ['email@', false],
            ['@domain.com', false],
            ['email@domain.', false],
            ['', false],
        ];
    }

    #[DataProvider('emailsProvider')]
    public function testEsEmailValido(string $email, bool $esperado) {
        $this->assertSame($esperado, esEmailValido($email));
    }
}

