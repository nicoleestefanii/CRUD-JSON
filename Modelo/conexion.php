<?php
/**
 * Clase DB (Singleton)
 * Conexión segura a MySQL mediante PDO con sentencias preparadas.
 * Expone: insertSeguro(), updateSeguro() y query().
 */
class DB
{
    private static ?DB $instancia = null;
    private PDO $pdo;

    // --- Configuración (ajustar según WampServer / XAMPP) ---
    private const HOST = 'localhost';
    private const BD   = 'productosdb';
    private const USER = 'root';
    private const PASS = '';

    /** Constructor privado: patrón Singleton */
    private function __construct()
    {
        $dsn = 'mysql:host=' . self::HOST . ';dbname=' . self::BD . ';charset=utf8mb4';
        $this->pdo = new PDO($dsn, self::USER, self::PASS, [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false, // preparadas reales: evita inyección SQL
        ]);
    }

    /** Devuelve la única instancia de DB */
    public static function getInstancia(): DB
    {
        if (self::$instancia === null) {
            self::$instancia = new DB();
        }
        return self::$instancia;
    }

    /** Valida nombres de tabla/columna (no se pueden parametrizar en PDO) */
    private function identificador(string $nombre): string
    {
        if (!preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $nombre)) {
            throw new InvalidArgumentException("Identificador inválido: $nombre");
        }
        return '`' . $nombre . '`';
    }

    /**
     * Ejecuta una consulta preparada.
     * Retorna arreglo de filas para SELECT, o nº de filas afectadas para el resto.
     */
    public function query(string $sql, array $params = []): array|int
    {
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        if ($stmt->columnCount() > 0) {
            return $stmt->fetchAll();
        }
        return $stmt->rowCount();
    }

    /** INSERT seguro. $datos = ['columna' => valor]. Retorna el ID insertado */
    public function insertSeguro(string $tabla, array $datos): int
    {
        $columnas = array_map([$this, 'identificador'], array_keys($datos));
        $marcas   = array_map(fn($c) => ':' . $c, array_keys($datos));
        $sql = 'INSERT INTO ' . $this->identificador($tabla) .
               ' (' . implode(', ', $columnas) . ') VALUES (' . implode(', ', $marcas) . ')';

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($datos);
        return (int) $this->pdo->lastInsertId();
    }

    /** UPDATE seguro por ID. Retorna true si la sentencia se ejecutó */
    public function updateSeguro(string $tabla, array $datos, int $id): bool
    {
        $sets = array_map(fn($c) => $this->identificador($c) . ' = :' . $c, array_keys($datos));
        $sql  = 'UPDATE ' . $this->identificador($tabla) .
                ' SET ' . implode(', ', $sets) . ' WHERE `id` = :_id';

        $stmt = $this->pdo->prepare($sql);
        return $stmt->execute($datos + ['_id' => $id]);
    }
}
