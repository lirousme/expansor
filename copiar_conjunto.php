<?php
// copiar_conjunto.php
header('Content-Type: application/json');

require 'db.php';

$input = json_decode(file_get_contents('php://input'), true);

if (
    !isset($input['id_conjunto']) ||
    (int)$input['id_conjunto'] === 0
) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => 'ID inválido'
    ]);
    exit;
}

$id = (int)$input['id_conjunto'];

try {
    // Atualiza o usuário de id = 1
    $stmt = $pdo->prepare(
        "UPDATE usuarios 
         SET id_conjunto_copiado = :id_conjunto 
         WHERE id = 1"
    );

    $stmt->execute([
        ':id_conjunto' => $id
    ]);

    echo json_encode(['success' => true]);

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
