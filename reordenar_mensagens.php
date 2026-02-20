<?php
/**
 * reordenar_mensagens.php
 * Este script reordena as mensagens atualizando a coluna `ordem`.
 */

ini_set('display_errors', 0);
header('Content-Type: application/json');

require 'db.php';

$input = file_get_contents('php://input');
$data = json_decode($input, true);

$ids = $data['ids'] ?? [];

if (empty($ids)) {
    echo json_encode(['success' => false, 'error' => 'Lista de IDs vazia.']);
    exit;
}

try {
    $pdo->beginTransaction();

    // Atualiza a ordem das mensagens baseada na posição do array
    $stmt = $pdo->prepare("UPDATE mensagens SET ordem = :ordem WHERE id = :id");

    foreach ($ids as $posicao => $id) {
        $stmt->execute([
            'ordem' => $posicao,
            'id'    => $id
        ]);
    }

    $pdo->commit();
    echo json_encode(['success' => true]);

} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
