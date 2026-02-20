<?php
header('Content-Type: application/json');

require 'db.php';

$input = json_decode(file_get_contents('php://input'), true);

if (
    !isset($input['questoes']) ||
    !is_array($input['questoes']) ||
    empty($input['questoes'])
) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => 'JSON inválido'
    ]);
    exit;
}

//$idChatPai = $input['id_chat_pai'] ?? null;
$idChatPai = 3655;

try {
    $pdo->beginTransaction();

    $idsChatsCriados = [];

    foreach ($input['questoes'] as $objeto) {

        // cria chat
        $stmt = $pdo->prepare("INSERT INTO conjuntos (tipo) VALUES (1)");
        $stmt->execute();
        $id_chat_conjunto = $pdo->lastInsertId();

        // cria revisão inicial
        $stmtRev = $pdo->prepare(
            "INSERT INTO revisoes (id_chat_conjunto)
             VALUES (:id_chat_conjunto)"
        );
        $stmtRev->execute([
            ':id_chat_conjunto' => $id_chat_conjunto
        ]);

        // cria relação com o pai, se existir
        if ($idChatPai) {
            $stmtRel = $pdo->prepare(
                "INSERT INTO conjuntos_relacoes (id_conjunto_pai, id_conjunto_filho)
                 VALUES (:pai, :filho)"
            );
            $stmtRel->execute([
                ':pai'   => $idChatPai,
                ':filho' => $id_chat_conjunto
            ]);
        }

        // cada atributo vira uma mensagem
        $stmtMsg = $pdo->prepare(
            "INSERT INTO mensagens (texto, id_chat_conjunto)
             VALUES (:texto, :id_chat_conjunto)"
        );

        foreach ($objeto as $chave => $valor) {
            if ($valor === null || $valor === '') {
                continue;
            }

            $stmtMsg->execute([
                ':texto' => (string)$valor,
                ':id_chat_conjunto' => $id_chat_conjunto
            ]);
        }

        $idsChatsCriados[] = $id_chat_conjunto;
    }

    $pdo->commit();

    echo json_encode([
        'success' => true,
        'chats_criados' => $idsChatsCriados
    ]);

} catch (PDOException $e) {
    $pdo->rollBack();
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
