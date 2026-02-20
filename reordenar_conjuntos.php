<?php
/**
 * reordenar_conjuntos.php
 * * Este script reordena conjuntos dependendo do contexto:
 * - Se houver um id_pai: altera a ordem na tabela 'conjuntos_relacoes' (contexto específico).
 * - Se id_pai for 'no': altera a ordem na tabela 'conjuntos' (raiz).
 */

ini_set('display_errors', 0);
header('Content-Type: application/json');

require 'db.php';

$input = file_get_contents('php://input');
$data = json_decode($input, true);

$ids = $data['ids'] ?? [];
$id_pai = $data['id_pai'] ?? 'no';

if (empty($ids)) {
    echo json_encode(['success' => false, 'error' => 'Lista de IDs vazia.']);
    exit;
}

try {
    $pdo->beginTransaction();

    if ($id_pai !== 'no') {
        // Cenário: Reordenar filhos dentro de um pai específico
        // A tabela 'conjuntos_relacoes' deve ter a coluna 'ordem'
        $stmt = $pdo->prepare("
            UPDATE conjuntos_relacoes 
            SET ordem = :ordem 
            WHERE id_conjunto_pai = :id_pai AND id_conjunto_filho = :id_filho
        ");

        foreach ($ids as $posicao => $id_filho) {
            $stmt->execute([
                'ordem'  => $posicao,
                'id_pai' => $id_pai,
                'id_filho' => $id_filho
            ]);
        }
    } else {
        // Cenário: Reordenar elementos da raiz (sem pai)
        $stmt = $pdo->prepare("UPDATE conjuntos SET ordem = :ordem WHERE id = :id");

        foreach ($ids as $posicao => $id) {
            $stmt->execute([
                'ordem' => $posicao,
                'id'    => $id
            ]);
        }
    }

    $pdo->commit();
    echo json_encode(['success' => true]);

} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}