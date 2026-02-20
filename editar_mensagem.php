<?php
// editar_mensagem.php
header('Content-Type: application/json');

require 'db.php';

$input = json_decode(file_get_contents('php://input'), true);

if (
    !isset($input['texto'], $input['editarAtivado']) ||
    trim($input['texto']) === '' ||
    (int)$input['editarAtivado'] === 0
) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => 'Dados inválidos'
    ]);
    exit;
}

try {
    $stmt = $pdo->prepare(
        "UPDATE mensagens
         SET texto = :texto
         WHERE id = :id"
    );

    $stmt->execute([
        ':texto' => $input['texto'],
        ':id'    => $input['editarAtivado']
    ]);

    echo json_encode(['success' => true]);

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
