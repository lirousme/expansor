<?php
// transferir_conjunto.php
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
    // 1️⃣ Busca o valor de id_conjunto_copiado do usuário de id = 1
    $stmt = $pdo->prepare("SELECT id_conjunto_copiado FROM usuarios WHERE id = 1");
    $stmt->execute();
    $id_copiado = $stmt->fetchColumn();

    if (!$id_copiado) {
        http_response_code(404);
        echo json_encode([
            'success' => false,
            'error' => 'Nenhum conjunto copiado encontrado para o usuário'
        ]);
        exit;
    }

    // 2️⃣ Verifica se já existe registro em conjuntos_relacoes
    $stmt = $pdo->prepare("
        SELECT COUNT(*) 
        FROM conjuntos_relacoes 
        WHERE id_conjunto_filho = :filho
    ");
    $stmt->execute([':filho' => $id_copiado]);
    $existe = (int)$stmt->fetchColumn();

    if ($existe > 0) {
        // Atualiza se já existe
        $stmt = $pdo->prepare("
            UPDATE conjuntos_relacoes
            SET id_conjunto_pai = :novo_pai
            WHERE id_conjunto_filho = :filho
        ");
        $stmt->execute([
            ':novo_pai' => $id,
            ':filho' => $id_copiado
        ]);
    } else {
        // Insere se não existe
        $stmt = $pdo->prepare("
            INSERT INTO conjuntos_relacoes (id_conjunto_pai, id_conjunto_filho)
            VALUES (:novo_pai, :filho)
        ");
        $stmt->execute([
            ':novo_pai' => $id,
            ':filho' => $id_copiado
        ]);
    }

    echo json_encode(['success' => true]);

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
