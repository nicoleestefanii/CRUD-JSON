<?php
// El header va al inicio y el archivo solo emite JSON (sin echo/var_dump extra).
header("Content-Type: application/json; charset=utf-8");
ini_set('display_errors', '0');
error_reporting(E_ALL);

require_once __DIR__ . '/Modelo/Productos.php';

$accion   = $_POST['Accion'] ?? '';
$response = [
    'success' => false,
    'message' => 'Acción no válida.',
    'accion'  => $accion,
    'errors'  => [],
];

try {
    $producto = new Producto($_POST);

    // Lógica centralizada de operaciones CRUD
    switch ($accion) {
        case 'Guardar':
            $response = ['accion' => 'Guardar'] + $producto->guardar();
            break;

        case 'Modificar':
            $response = ['accion' => 'Modificar'] + $producto->editar();
            break;

        case 'Buscar':
            $datos = $producto->buscar($_POST['Termino'] ?? '');
            $response = [
                'success' => true,
                'message' => count($datos) . ' resultado(s) encontrado(s).',
                'accion'  => 'Buscar',
                'errors'  => [],
                'data'    => $datos,
            ];
            break;

        case 'Listar':
            $datos = $producto->listar();
            $response = [
                'success' => true,
                'message' => 'Listado cargado.',
                'accion'  => 'Listar',
                'errors'  => [],
                'data'    => $datos,
            ];
            break;

        default:
            http_response_code(400);
            $response['message'] = 'Acción no válida o no enviada.';
            break;
    }
} catch (Throwable $e) {
    http_response_code(500);
    error_log($e->getMessage());
    $response = [
        'success' => false,
        'message' => 'Error interno del servidor. Verifique la conexión a la base de datos.',
        'accion'  => $accion,
        'errors'  => [],
    ];
}

echo json_encode($response, JSON_UNESCAPED_UNICODE);
exit;
