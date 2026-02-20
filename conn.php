<?php
// conn.php

$host = 'localhost';     // servidor do MySQL
$user = 'u205629180_expansor';       // usuário do banco
$pass = 'naotemsenhaA1@75351595';         // senha do banco
$db   = 'u205629180_expansor'; // nome do banco

// Cria a conexão
$conn = new mysqli($host, $user, $pass, $db);

// Verifica se deu certo
if ($conn->connect_error) {
    die("Erro de conexão: " . $conn->connect_error);
}

// Opcional: define charset
$conn->set_charset("utf8mb4");
