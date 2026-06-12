<?php
// tests/bootstrap.php

// Inicializar la superglobal de sesión para evitar advertencias de "Undefined variable" en CLI
$_SESSION = [];

// Requerir el cargador automático de Composer
require_once __DIR__ . '/../vendor/autoload.php';

/**
 * Clase auxiliar (Stub) compartida que simula una conexión mysqli.
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
        $normalizedQuery = preg_replace('/\s+/', ' ', trim($query));
        foreach ($this->prepareMap as $key => $value) {
            $normalizedKey = preg_replace('/\s+/', ' ', trim($key));
            if ($normalizedKey === $normalizedQuery) {
                return $value;
            }
        }
        if (count($this->prepareMap) === 1) {
            return reset($this->prepareMap);
        }
        return null;
    }
}

