<?php
// editar_mensagem.php
header('Content-Type: application/json');

require 'db.php';

$input = json_decode(file_get_contents('php://input'), true);

if (
    !isset($input['idMessage']) ||
    (int)$input['idMessage'] === 0
) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => 'Dados inválidos'
    ]);
    exit;
}

$id = (int)$input['idMessage'];

try {

    // 1. buscar o valor atual de tipo
    $stmtTipo = $pdo->prepare(
        "SELECT tipo
         FROM conjuntos
         WHERE id = :id"
    );
    $stmtTipo->execute([':id' => $id]);
    $row = $stmtTipo->fetch(PDO::FETCH_ASSOC);

    if (!$row) {
        http_response_code(404);
        echo json_encode([
            'success' => false,
            'error' => 'Registro não encontrado'
        ]);
        exit;
    }

    // 2. se já for 1, muda para 0 diretamente
    if ((int)$row['tipo'] === 1) {
        $stmtUpdate = $pdo->prepare(
            "UPDATE conjuntos
             SET tipo = 0
             WHERE id = :id"
        );

        $stmtUpdate->execute([':id' => $id]);

        echo json_encode(['success' => true]);
        exit;
    }

    // 3. se for 0, antes de mudar para 1 verifica relações
    $stmtCheck = $pdo->prepare(
        "SELECT 1
         FROM conjuntos_relacoes
         WHERE id_conjunto_pai = :id
         LIMIT 1"
    );
    $stmtCheck->execute([':id' => $id]);

    if ($stmtCheck->fetch()) {
        http_response_code(409);
        echo json_encode([
            'success' => false,
            'error' => 'Não é possível alterar: existem conjuntos filhos vinculados'
        ]);
        exit;
    }

    // 4. pode mudar para 1
    $stmtUpdate = $pdo->prepare(
        "UPDATE conjuntos
         SET tipo = 1
         WHERE id = :id"
    );

    $stmtUpdate->execute([':id' => $id]);

    echo json_encode(['success' => true]);

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
