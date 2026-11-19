<?php
class Conexion {
    private $host = "localhost";
    private $db_name = "red_social_bdm";
    private $username = "root";
    private $password = "";
    private $port = 3306;

    public $conn;

    // Método para obtener la conexión a la base de datos
    public function getConexion() {
        $this->conn = null;

        try {
            // Conexión mediante PDO usando el puerto 3307
            $this->conn = new PDO(
                "mysql:host=" . $this->host .
                ";port=" . $this->port .
                ";dbname=" . $this->db_name .
                ";charset=utf8mb4",
                $this->username,
                $this->password
            );

            // Configurar el manejo de errores
            $this->conn->setAttribute(
                PDO::ATTR_ERRMODE,
                PDO::ERRMODE_EXCEPTION
            );

        } catch (PDOException $exception) {
            error_log("Error de conexión: " . $exception->getMessage());
            echo "Error al conectar con la base de datos.";
        }

        return $this->conn;
    }
}
?>
