<?php
class Database {
    private $mysqli;
    private $host = "localhost";
    private $user = "root";
    private $pass = ""; // Adjust if password is required
    private $dbname = "sistema_vendas";
    private $port = 3308; // Adjust if port is different (default MySQL port is 3306)

    public function __construct() {
        // Connect without database to create it
        $conn = new mysqli($this->host, $this->user, $this->pass, '', $this->port);
        if ($conn->connect_error) {
            error_log("Connection failed: " . $conn->connect_error);
            throw new Exception('Erro na conexão: ' . $conn->connect_error);
        }

        $sql = "CREATE DATABASE IF NOT EXISTS {$this->dbname}";
        if (!$conn->query($sql)) {
            error_log("Error creating database: " . $conn->error);
            throw new Exception('Erro ao criar banco: ' . $conn->error);
        }
        $conn->close();

        // Connect to the database
        $this->mysqli = new mysqli($this->host, $this->user, $this->pass, $this->dbname, $this->port);
        if ($this->mysqli->connect_error) {
            error_log("Database connection error: " . $this->mysqli->connect_error);
            throw new Exception('Erro na conexão com banco: ' . $this->mysqli->connect_error);
        }

        // Set charset to utf8mb4
        if (!$this->mysqli->set_charset('utf8mb4')) {
            error_log("Error setting charset: " . $this->mysqli->error);
            throw new Exception('Erro ao definir charset: ' . $this->mysqli->error);
        }

        // Start a transaction for table creation
        $this->mysqli->begin_transaction();
        try {
            $sql = "CREATE TABLE IF NOT EXISTS vendas (
                id_venda INT PRIMARY KEY,
                data_venda DATE,
                total_venda DECIMAL(10,2),
                cliente_id INT,
                vendedor_id INT
            )";
            if (!$this->mysqli->query($sql)) {
                throw new Exception('Erro ao criar tabela vendas: ' . $this->mysqli->error);
            }

            $sql = "CREATE TABLE IF NOT EXISTS pedidos (
                id_pedido INT AUTO_INCREMENT PRIMARY KEY,
                data_pedido DATE,
                cliente_id INT,
                total_pedido DECIMAL(10,2)
            )";
            if (!$this->mysqli->query($sql)) {
                throw new Exception('Erro ao criar tabela pedidos: ' . $this->mysqli->error);
            }

            $sql = "CREATE TABLE IF NOT EXISTS produtos (
                id_produto INT AUTO_INCREMENT PRIMARY KEY,
                nome_produto VARCHAR(255),
                preco DECIMAL(10,2)
            )";
            if (!$this->mysqli->query($sql)) {
                throw new Exception('Erro ao criar tabela produtos: ' . $this->mysqli->error);
            }

            $this->mysqli->commit();
        } catch (Exception $e) {
            $this->mysqli->rollback();
            error_log("Table creation error: " . $e->getMessage());
            throw new Exception($e->getMessage());
        }
    }

    public function prepare($sql) {
        $stmt = $this->mysqli->prepare($sql);
        if (!$stmt) {
            error_log("Prepare error: " . $this->mysqli->error);
            throw new Exception("Erro na preparação: " . $this->mysqli->error);
        }
        return $stmt;
    }

    public function getMysqli() {
        return $this->mysqli;
    }

    // Added for compatibility with potential future use
    public function getConnection() {
        return $this->getMysqli();
    }

    public function indexExists($table, $index) {
        if (!preg_match('/^[a-zA-Z0-9_]+$/', $table)) {
            error_log("Invalid table name: $table");
            throw new Exception("Nome de tabela inválido.");
        }

        // Simplified for performance since $index is system-generated
        $sql = "SHOW INDEX FROM `$table` WHERE Key_name = '$index'";
        $result = $this->mysqli->query($sql);
        if ($result === false) {
            error_log("Index check error: " . $this->mysqli->error);
            throw new Exception("Erro ao verificar índice: " . $this->mysqli->error);
        }
        $exists = $result->num_rows > 0;
        $result->close();
        return $exists;
    }

    public function dropAllCustomIndexes($table, $prefix = 'idx_') {
        $sql = "SHOW INDEX FROM `$table`";
        $result = $this->mysqli->query($sql);
        if (!$result) {
            throw new Exception("Erro ao listar índices: " . $this->mysqli->error);
        }
    
        while ($row = $result->fetch_assoc()) {
            if (strpos($row['Key_name'], $prefix) === 0) {
                $this->query("DROP INDEX `{$row['Key_name']}` ON `$table`");
            }
        }
    
        $result->close();
    }
    

    public function query($sql) {
        $result = $this->mysqli->query($sql);
        if (!$result) {
            error_log("Query error: " . $this->mysqli->error);
            throw new Exception("Erro na consulta: " . $this->mysqli->error);
        }
        return $result;
    }

    public function close() {
        $this->mysqli->close();
    }
}