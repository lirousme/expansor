<?php
// expandir.php
header('Content-Type: application/json');

require 'db.php';

$input = json_decode(file_get_contents('php://input'), true);

if (
    !isset($input['texto']) ||
    trim($input['texto']) === '' ||
    !isset($input['id_chat']) ||
    !is_numeric($input['id_chat'])
) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => 'Dados inválidos'
    ]);
    exit;
}

try {
    $pdo->beginTransaction();

    // cria novo chat
    $stmt = $pdo->prepare("INSERT INTO conjuntos (tipo) VALUES (1)");
    $stmt->execute();
    $id_chat_conjunto = $pdo->lastInsertId();

    // cria apenas uma revisão
    $stmtRev = $pdo->prepare(
        "INSERT INTO revisoes (id_chat_conjunto)
         VALUES (:id_chat_conjunto)"
    );
    $stmtRev->execute([
        ':id_chat_conjunto' => $id_chat_conjunto
    ]);

    // verifica se o chat original é filho de outro conjunto
    $stmtRel = $pdo->prepare("
        SELECT id_conjunto_pai
        FROM conjuntos_relacoes
        WHERE id_conjunto_filho = :id_chat
        LIMIT 1
    ");
    $stmtRel->execute([
        ':id_chat' => $input['id_chat']
    ]);
    $relacao = $stmtRel->fetch(PDO::FETCH_ASSOC);

    // se existir relação, replica para o novo chat
    if ($relacao) {
        $stmtNovaRel = $pdo->prepare("
            INSERT INTO conjuntos_relacoes (id_conjunto_pai, id_conjunto_filho)
            VALUES (:id_conjunto_pai, :id_conjunto_filho)
        ");
        $stmtNovaRel->execute([
            ':id_conjunto_pai'   => $relacao['id_conjunto_pai'],
            ':id_conjunto_filho' => $id_chat_conjunto
        ]);
    }

    // busca mensagem original
    $stmt = $pdo->prepare("
        SELECT texto, imagem 
        FROM mensagens 
        WHERE texto = :texto 
        ORDER BY id DESC 
        LIMIT 1
    ");
    $stmt->execute([':texto' => $input['texto']]);
    $original = $stmt->fetch(PDO::FETCH_ASSOC);

    $imagem = $original['imagem'] ?? null;

    // insere a mensagem já vinculada ao novo chat
    $stmt = $pdo->prepare(
        "INSERT INTO mensagens (texto, imagem, id_chat_conjunto)
         VALUES (:texto, :imagem, :id_chat_conjunto)"
    );
    $stmt->execute([
        ':texto'            => $input['texto'],
        ':imagem'           => $imagem,
        ':id_chat_conjunto' => $id_chat_conjunto
    ]);

    $pdo->commit();

    echo json_encode([
        'success' => true,
        'id_chat' => $id_chat_conjunto
    ]);

} catch (PDOException $e) {
    $pdo->rollBack();
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
