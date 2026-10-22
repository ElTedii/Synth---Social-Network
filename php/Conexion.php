<?php
class Conexion {
    private $host = "localhost";
    private $db_name = "red_social_bdm";
    private $username = "root"; // Cambia esto por tu usuario de MySQL
    private $password = "";     // Cambia esto por tu contraseña de MySQL
    public $conn;

    // Método para obtener la conexión a la base de datos
    public function getConexion() {
        $this->conn = null;

        try {
            // Se establece la conexión utilizando PDO
            $this->conn = new PDO("mysql:host=" . $this->host . ";dbname=" . $this->db_name, $this->username, $this->password);
            
            // Configurar el modo de error de PDO para que lance excepciones
            $this->conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            
            // Forzar el uso de caracteres UTF-8
            $this->conn->exec("set names utf8");
            
        } catch(PDOException $exception) {
            echo "Error de conexión: " . $exception->getMessage();
        }

        return $this->conn;
    }
}
?>