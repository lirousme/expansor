<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
// buscar_mensagens.php
header('Content-Type: application/json');

require 'db.php';

// receber parâmetros via POST
$id_conjunto = $_POST['id_conjunto'] ?? 'no';
$id_usuario  = $_POST['id_usuario']  ?? null;

if ($id_conjunto !== 'no') {

  $sql = "
SELECT c.*

FROM conjuntos c

INNER JOIN conjuntos_relacoes cr ON c.id = cr.id_conjunto_filho

WHERE cr.id_conjunto_pai = :id_conjunto

ORDER BY cr.ordem ASC, c.id ASC
  ";

  $stmt = $pdo->prepare($sql);
  $stmt->bindValue(':id_conjunto', $id_conjunto, PDO::PARAM_INT);
  $stmt->execute();

} else {

  $sql = "
SELECT c.*

FROM conjuntos c

LEFT JOIN conjuntos_relacoes cr ON c.id = cr.id_conjunto_filho

WHERE cr.id_conjunto_filho IS NULL

ORDER BY c.ordem ASC, c.id ASC
  ";

  $stmt = $pdo->prepare($sql);
  $stmt->execute();
}

$conjuntos = $stmt->fetchAll(PDO::FETCH_ASSOC);

// resposta
echo json_encode([
  'success'   => true,
  'id_chat'   => $id_conjunto,
  'mensagens' => $conjuntos
]);
