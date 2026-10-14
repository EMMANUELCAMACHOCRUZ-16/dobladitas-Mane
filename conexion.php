<?php
// Datos para conectarse a la base de datos, como en las prácticas anteriores.
$servidor = 'localhost';
$usuario = 'root';
$password = '';
$baseDatos = 'db_dobladitasmane';

$conexion = new mysqli($servidor, $usuario, $password, $baseDatos);

if ($conexion->connect_error) {
    die('Conexión fallida: ' . $conexion->connect_error);
}

// Permite trabajar correctamente con acentos y la letra ñ.
$conexion->set_charset('utf8mb4');
