<?php
require_once __DIR__ . '/conexion.php';

/**
 * Clase Producto
 * Propiedades + métodos: validar(), guardar(), editar(), buscar(), listar().
 */
class Producto
{
    public ?int $id = null;
    public string $codigo = '';
    public string $producto = '';
    public mixed $precio = '';
    public mixed $cantidad = '';

    private DB $db;

    /** Recibe un arreglo (normalmente $_POST) y llena las propiedades */
    public function __construct(array $datos = [])
    {
        $this->db       = DB::getInstancia();
        $this->id       = isset($datos['id']) && $datos['id'] !== '' ? (int) $datos['id'] : null;
        $this->codigo   = trim((string) ($datos['Codigo']   ?? ''));
        $this->producto = trim((string) ($datos['Producto'] ?? ''));
        $this->precio   = trim((string) ($datos['Precio']   ?? ''));
        $this->cantidad = trim((string) ($datos['Cantidad'] ?? ''));
    }

    /**
     * Validación en servidor.
     * Regla de negocio: al GUARDAR la cantidad mínima es 1; al MODIFICAR es 0
     * (un producto agotado debe poder editarse).
     * Retorna arreglo campo => mensaje (vacío si todo es válido).
     */
    public function validar(string $modo): array
    {
        $errores = [];

        if ($modo === 'Modificar' && (!$this->id || $this->id <= 0)) {
            $errores['id'] = 'ID de producto inválido.';
        }

        if ($this->codigo === '') {
            $errores['Codigo'] = 'El código es obligatorio.';
        } elseif (mb_strlen($this->codigo) > 20) {
            $errores['Codigo'] = 'El código no puede superar 20 caracteres.';
        }

        if ($this->producto === '') {
            $errores['Producto'] = 'El nombre del producto es obligatorio.';
        } elseif (mb_strlen($this->producto) > 100) {
            $errores['Producto'] = 'El producto no puede superar 100 caracteres.';
        }

        if ($this->precio === '') {
            $errores['Precio'] = 'El precio es obligatorio.';
        } elseif (!is_numeric($this->precio) || (float) $this->precio <= 0) {
            $errores['Precio'] = 'El precio debe ser un número mayor que 0.';
        } elseif ((float) $this->precio > 99999999.99) {
            $errores['Precio'] = 'El precio excede el máximo permitido.';
        }

        $minimo = ($modo === 'Guardar') ? 1 : 0;
        if ($this->cantidad === '') {
            $errores['Cantidad'] = 'La cantidad es obligatoria.';
        } elseif (filter_var($this->cantidad, FILTER_VALIDATE_INT) === false) {
            $errores['Cantidad'] = 'La cantidad debe ser un número entero.';
        } elseif ((int) $this->cantidad < $minimo) {
            $errores['Cantidad'] = "La cantidad mínima es $minimo.";
        }

        return $errores;
    }

    /** Verifica si el código ya existe (excluyendo el propio ID al editar) */
    private function codigoExiste(): bool
    {
        $sql    = 'SELECT id FROM productos WHERE codigo = ?';
        $params = [$this->codigo];
        if ($this->id) {
            $sql    .= ' AND id <> ?';
            $params[] = $this->id;
        }
        return count($this->db->query($sql, $params)) > 0;
    }

    /** INSERT. Retorna ['success'=>bool,'message'=>string,'errors'=>array] */
    public function guardar(): array
    {
        $errores = $this->validar('Guardar');
        if (!$errores && $this->codigoExiste()) {
            $errores['Codigo'] = 'Ya existe un producto con ese código.';
        }
        if ($errores) {
            return ['success' => false, 'message' => 'Producto no creado. Revise los campos.', 'errors' => $errores];
        }

        $id = $this->db->insertSeguro('productos', [
            'codigo'   => $this->codigo,
            'producto' => $this->producto,
            'precio'   => number_format((float) $this->precio, 2, '.', ''),
            'cantidad' => (int) $this->cantidad,
        ]);

        return ['success' => true, 'message' => "Producto creado correctamente (ID $id).", 'errors' => []];
    }

    /** UPDATE. Mismo formato de retorno que guardar() */
    public function editar(): array
    {
        $errores = $this->validar('Modificar');
        if (!$errores) {
            if (!$this->obtener($this->id)) {
                $errores['id'] = 'El producto no existe.';
            } elseif ($this->codigoExiste()) {
                $errores['Codigo'] = 'Ya existe otro producto con ese código.';
            }
        }
        if ($errores) {
            return ['success' => false, 'message' => 'Producto no modificado. Revise los campos.', 'errors' => $errores];
        }

        $this->db->updateSeguro('productos', [
            'codigo'   => $this->codigo,
            'producto' => $this->producto,
            'precio'   => number_format((float) $this->precio, 2, '.', ''),
            'cantidad' => (int) $this->cantidad,
        ], $this->id);

        return ['success' => true, 'message' => 'Producto actualizado correctamente.', 'errors' => []];
    }

    /** Busca por código o nombre (LIKE). Con término vacío devuelve todo */
    public function buscar(string $termino = ''): array
    {
        $termino = trim($termino);
        if ($termino === '') {
            return $this->listar();
        }
        $like = '%' . $termino . '%';
        return $this->db->query(
            'SELECT id, codigo, producto, precio, cantidad FROM productos
             WHERE codigo LIKE ? OR producto LIKE ? ORDER BY id DESC',
            [$like, $like]
        );
    }

    /** Lista todos los productos */
    public function listar(): array
    {
        return $this->db->query('SELECT id, codigo, producto, precio, cantidad FROM productos ORDER BY id DESC');
    }

    /** Obtiene un producto por ID (o null) */
    public function obtener(?int $id): ?array
    {
        if (!$id) {
            return null;
        }
        $filas = $this->db->query('SELECT id, codigo, producto, precio, cantidad FROM productos WHERE id = ?', [$id]);
        return $filas[0] ?? null;
    }
}
