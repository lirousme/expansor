<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
// buscar_conjuntos.php
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

// ANINHAMENTO DOS DADOS
// Varre cada conjunto para buscar suas mensagens (se for chat) ou contar os filhos
foreach ($conjuntos as &$c) {
    if ($c['tipo'] == 1) {
        // Se for tipo 1 (Chat), busca todas as mensagens dele
        $sqlMsg = "SELECT * FROM mensagens WHERE id_chat_conjunto = :id ORDER BY ordem ASC, id ASC";
        $stmtMsg = $pdo->prepare($sqlMsg);
        $stmtMsg->execute(['id' => $c['id']]);
        
        $c['mensagens'] = $stmtMsg->fetchAll(PDO::FETCH_ASSOC);
        $c['qtd_filhos'] = 0;
    } else {
        // Se NÃO for tipo 1, conta quantos filhos ele possui
        $sqlCount = "SELECT COUNT(*) FROM conjuntos_relacoes WHERE id_conjunto_pai = :id";
        $stmtCount = $pdo->prepare($sqlCount);
        $stmtCount->execute(['id' => $c['id']]);
        
        $c['qtd_filhos'] = (int) $stmtCount->fetchColumn();
        $c['mensagens'] = [];
    }
}
unset($c); // Quebra a referência da variável

// resposta
echo json_encode([
  'success'   => true,
  'id_chat'   => $id_conjunto,
  'mensagens' => $conjuntos // Continua chamando 'mensagens' para manter compatibilidade com seu JS atual
]);
