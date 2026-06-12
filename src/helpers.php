<?php
// src/helpers.php

/**
 * Escapa caracteres especiales de HTML para evitar vulnerabilidades de Cross-Site Scripting (XSS).
 * 
 * Esta función es un atajo seguro y rápido para `htmlspecialchars`.
 *
 * @param string|null $string La cadena de texto cruda que se desea sanitizar.
 * @return string La cadena sanitizada y segura para ser impresa en plantillas HTML.
 */
function e($string) {
    return htmlspecialchars($string ?? '', ENT_QUOTES, 'UTF-8');
}

/**
 * Imprime el contenido de una variable de forma estructurada y detiene la ejecución del script.
 * 
 * Útil para tareas de depuración (debugging) rápida durante el desarrollo.
 *
 * @param mixed $data La variable, arreglo u objeto que se desea inspeccionar.
 * @return void
 */
function dd($data) {
    echo "<pre>";
    print_r($data);
    echo "</pre>";
    die();
}

/**
 * Valida que un nombre o apellido solo contenga letras (incluye acentos, diéresis y la ñ) y espacios.
 * 
 * Utiliza expresiones regulares con el modificador Unicode `/u` para asegurar compatibilidad internacional.
 *
 * @param string $cadena El nombre o apellido a validar.
 * @return bool True si la cadena cumple con el formato permitido, False en caso contrario.
 */
function esNombreValido($cadena) {
    // 1. Verificamos que tenga al menos una letra (para evitar nombres compuestos solo por espacios o comillas)
    if (preg_match('/[\p{L}]/u', $cadena) !== 1) {
        return false;
    }
    // 2. Verificamos que la cadena contenga únicamente letras, espacios o comillas simples
    return preg_match('/^[\p{L}\s\']+$/u', $cadena) === 1;
}


/**
 * Valida que el texto de una noticia (título, resumen o contenido) sea correcto y admita soporte Hangul.
 * 
 * El texto debe contener obligatoriamente al menos una letra (no puede ser solo números o símbolos)
 * y estar compuesto únicamente por caracteres permitidos: letras (latinas o coreanas), números,
 * espacios, saltos de línea y signos de puntuación estándar.
 *
 * @param string $cadena El texto a validar.
 * @return bool True si el texto es válido y seguro, False en caso contrario.
 */
function esTextoNoticiaValido($cadena) {
    // 1. Verificamos que tenga al menos una letra (latina, coreana, etc.) para evitar contenido vacío de texto
    if (preg_match('/[\p{L}]/u', $cadena) !== 1) {
        return false;
    }
    
    // 2. Verificamos que solo contenga caracteres permitidos:
    // \p{L} -> Letras (cualquier idioma, incluye Hangul coreano)
    // \p{N} -> Números
    // \s -> Espacios en blanco y saltos de línea (\r, \n, tabulaciones)
    // \p{P} -> Signos de puntuación estándar (?, !, ., ,, (, ), -, etc.)
    // El modificador /u habilita el tratamiento correcto de cadenas multibyte (UTF-8).
    return preg_match('/^[\p{L}\p{N}\s\p{P}]+$/u', $cadena) === 1;
}


/**
 * Valida si un correo electrónico posee un formato estándar correcto.
 * 
 * Emplea la función nativa `filter_var` de PHP con el filtro `FILTER_VALIDATE_EMAIL`.
 *
 * @param string $email El correo electrónico a validar.
 * @return bool True si el correo tiene un formato sintácticamente válido, False en caso contrario.
 */
function esEmailValido($email) {
    // filter_var es una función interna de PHP altamente optimizada y segura
    return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
}