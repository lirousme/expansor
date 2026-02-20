<?php
// criar_portal.php
header('Content-Type: application/json');

require 'db.php';

$input = json_decode(file_get_contents('php://input'), true);

if (!isset($input['id_conjunto'])) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => 'ID inválido'
    ]);
    exit;
}

// O id do conjunto (diretório) onde o portal será inserido
$id_pai_destino = $input['id_conjunto'];

try {
    $pdo->beginTransaction();

    // 1️⃣ Busca o valor de id_conjunto_copiado do usuário de id = 1
    $stmt = $pdo->prepare("SELECT id_conjunto_copiado FROM usuarios WHERE id = 1");
    $stmt->execute();
    $id_copiado = $stmt->fetchColumn();

    if (!$id_copiado) {
        $pdo->rollBack();
        http_response_code(404);
        echo json_encode([
            'success' => false,
            'error' => 'Nenhum conjunto copiado encontrado para o usuário'
        ]);
        exit;
    }

    // 2️⃣ Busca os dados originais do conjunto copiado para criar uma representação visual
    $stmtOrig = $pdo->prepare("SELECT texto, imagem FROM conjuntos WHERE id = :id");
    $stmtOrig->execute([':id' => $id_copiado]);
    $orig = $stmtOrig->fetch(PDO::FETCH_ASSOC);

    // O portal leva o mesmo texto/imagem do original para facilitar a visualização
    $textoPortal = $orig && $orig['texto'] ? $orig['texto'] : "Portal";
    $imagemPortal = $orig ? $orig['imagem'] : null;

    // 3️⃣ Cria o novo conjunto do tipo Portal (tipo = 2) apontando para o original
    $stmtInsert = $pdo->prepare("
        INSERT INTO conjuntos (tipo, texto, imagem, id_conjunto_portal)
        VALUES (2, :texto, :imagem, :id_portal)
    ");
    $stmtInsert->execute([
        ':texto' => $textoPortal,
        ':imagem' => $imagemPortal,
        ':id_portal' => $id_copiado
    ]);

    $id_novo_portal = $pdo->lastInsertId();

    // 4️⃣ Relaciona o portal recém-criado com o diretório atual
    if ($id_pai_destino !== 'no' && $id_pai_destino !== null) {
        $stmtRel = $pdo->prepare("
            INSERT INTO conjuntos_relacoes (id_conjunto_pai, id_conjunto_filho)
            VALUES (:pai, :filho)
        ");
        $stmtRel->execute([
            ':pai' => $id_pai_destino,
            ':filho' => $id_novo_portal
        ]);
    }

    $pdo->commit();
    echo json_encode(['success' => true]);

} catch (PDOException $e) {
    $pdo->rollBack();
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
