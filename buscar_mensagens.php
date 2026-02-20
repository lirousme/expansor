<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

header('Content-Type: application/json');

require 'db.php';

/*
OBJETIVO CORRETO:
- Descobrir a FAMÍLIA de conjuntos
- Dentro dessa família, escolher APENAS UM conjunto (chat)
- Critério: conjunto com menos quantidade de revisões
- Buscar mensagens SOMENTE desse conjunto escolhido
*/

$id_chat = $_POST['id_chat'] ?? null;

/*
1. Definir conjunto de referência
*/
if ($id_chat) {
  $id_referencia = $id_chat;
} else {
$sqlRef = "
  SELECT id_chat_conjunto
  FROM revisoes
  ORDER BY quantidade_revisoes ASC
  LIMIT 1
";
$stmtRef = $pdo->prepare($sqlRef);
$stmtRef->execute();
$ref = $stmtRef->fetch(PDO::FETCH_ASSOC);

if (!$ref) {
  // criar novo conjunto
  $pdo->exec("INSERT INTO conjuntos () VALUES ()");
  $id_referencia = $pdo->lastInsertId();

  // criar apenas uma revisão
  $stmtRev = $pdo->prepare("
    INSERT INTO revisoes (id_chat_conjunto)
    VALUES (:id)
  ");

  $stmtRev->execute([
    'id' => $id_referencia
  ]);
} else {
  $id_referencia = $ref['id_chat_conjunto'];
}
}

/*
2. Descobrir TODOS os conjuntos da família
*/
$sqlFamilia = "
WITH RECURSIVE familia AS (
  SELECT $id_referencia AS id
  UNION ALL
  SELECT cr.id_conjunto_filho
  FROM conjuntos_relacoes cr
  JOIN familia f ON f.id = cr.id_conjunto_pai
)
SELECT DISTINCT id FROM familia
";

$stmtFam = $pdo->query($sqlFamilia); // usar query direto, sem bind
$familiaIds = array_column($stmtFam->fetchAll(PDO::FETCH_ASSOC), 'id');

if (!$familiaIds) {
    echo json_encode(['success' => true, 'mensagens' => []]);
    exit;
}

/*
3. Escolher APENAS UM chat da família com tipo = 1
*/
$placeholders = implode(',', array_fill(0, count($familiaIds), '?'));

/*
Contar quantos chats da família já venceram (estão aptos)
*/
$sqlChatsEmFila = "
SELECT COUNT(DISTINCT r.id) AS total
FROM revisoes rev
JOIN conjuntos r ON r.id = rev.id_chat_conjunto
WHERE r.id IN ($placeholders)
  AND r.tipo = 1
  AND TIMESTAMPDIFF(MINUTE, rev.data_ultima_revisao, NOW()) >= (rev.quantidade_revisoes * 2)
";

$stmtFila = $pdo->prepare($sqlChatsEmFila);
$stmtFila->execute($familiaIds);
$fila = $stmtFila->fetch(PDO::FETCH_ASSOC);

$chatsEmFila = $fila ? (int)$fila['total'] : 0;


$sqlChatEscolhido = "
SELECT r.id AS id_chat_conjunto
FROM revisoes rev
JOIN conjuntos r ON r.id = rev.id_chat_conjunto
WHERE r.id IN ($placeholders)
  AND r.tipo = 1
  AND TIMESTAMPDIFF(MINUTE, rev.data_ultima_revisao, NOW()) >= (rev.quantidade_revisoes * 15)
ORDER BY rev.quantidade_revisoes DESC,
         rev.data_ultima_revisao ASC
LIMIT 1
";

$stmtChat = $pdo->prepare($sqlChatEscolhido);
$stmtChat->execute($familiaIds);
$chatEscolhido = $stmtChat->fetch(PDO::FETCH_ASSOC);

if (!$chatEscolhido) {
  echo json_encode(['success' => true, 'mensagens' => []]);
  exit;
}

$id_chat_final = $chatEscolhido['id_chat_conjunto'];

/*
4. Buscar mensagens SOMENTE do chat escolhido
   (A ordem de carregamento agora obedece "f.ordem ASC" antes de "f.id ASC")
*/
$sqlMensagens = "
SELECT 
  f.id_chat_conjunto,
  f.id,
  f.texto,
  f.imagem,
  f.audio,
  f.idioma,
  r.data_de_criacao AS data_chat,
  f.data_de_criacao AS data_mensagem
FROM mensagens f
JOIN conjuntos r ON r.id = f.id_chat_conjunto
WHERE f.id_chat_conjunto = :id_chat
ORDER BY f.ordem ASC, f.id ASC
";

$stmtMsg = $pdo->prepare($sqlMensagens);
$stmtMsg->execute(['id_chat' => $id_chat_final]);
$mensagens = $stmtMsg->fetchAll(PDO::FETCH_ASSOC);

/*
ANTES da resposta final (passo 5),
buscar o valor de concluidos_hoje do usuário id = 1
*/
$sqlUsuario = "
  SELECT concluidos_hoje
  FROM usuarios
  WHERE id = 1
  LIMIT 1
";

$stmtUser = $pdo->prepare($sqlUsuario);
$stmtUser->execute();
$usuario = $stmtUser->fetch(PDO::FETCH_ASSOC);

$concluidosHoje = $usuario ? (int)$usuario['concluidos_hoje'] : 0;

/*
ANTES da resposta final (passo 5),
buscar a quantidade de revisões do chat escolhido
*/
$sqlRevisoes = "
  SELECT quantidade_revisoes
  FROM revisoes
  WHERE id_chat_conjunto = :id_chat
  LIMIT 1
";

$stmtRevQtd = $pdo->prepare($sqlRevisoes);
$stmtRevQtd->execute(['id_chat' => $id_chat_final]);
$rev = $stmtRevQtd->fetch(PDO::FETCH_ASSOC);

$quantidadeRevisoes = $rev ? (int)$rev['quantidade_revisoes'] : 0;

/*
5. Resposta
*/
echo json_encode([
  'success' => true,
  'id_chat' => $id_chat_final,
  'mensagens' => $mensagens,
  'familia_de_conjuntos' => $familiaIds,
  'concluidos_hoje' => $concluidosHoje,
  'quantidade_revisoes' => $quantidadeRevisoes,
  'chats_em_fila' => $chatsEmFila
]);
