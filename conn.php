<?php
// conn.php

$host = 'x';     // servidor do MySQL
$user = 'x';       // usuário do banco
$pass = 'x';         // senha do banco
$db   = 'x'; // nome do banco

// Cria a conexão
$conn = new mysqli($host, $user, $pass, $db);

// Verifica se deu certo
if ($conn->connect_error) {
    die("Erro de conexão: " . $conn->connect_error);
}

// Opcional: define charset
$conn->set_charset("utf8mb4");
