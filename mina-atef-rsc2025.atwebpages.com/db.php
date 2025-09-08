<?php
/**
 * Database connection for Asset Manager
 * Compatible with PHP 8.2+
 */

$config = [
    'host'     => '127.0.0.1',
    'username' => 'root',
    'password' => '',
    'database' => 'AssetManager',
    'charset'  => 'utf8mb4',
    'port'     => 3306
];

/**
 * Establish database connection
 */
function establishDatabaseConnection(array $config, bool $isDevelopment = false): mysqli {
    try {
        $conn = new mysqli(
            $config['host'],
            $config['username'],
            $config['password'],
            $config['database'],
            $config['port']
        );

        if ($conn->connect_error) {
            throw new Exception("Connection failed: " . $conn->connect_error);
        }

        if (!$conn->set_charset($config['charset'])) {
            throw new Exception("Error setting charset: " . $conn->error);
        }

        return $conn;

    } catch (Exception $e) {
        if ($isDevelopment) {
            die('Database connection failed: ' . $e->getMessage());
        } else {
            die('Database connection failed. Please try again later.');
        }
    }
}

// Create global connection
$conn = establishDatabaseConnection($config, true);
