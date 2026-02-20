<?php
// adicionar_mensagem.php
header('Content-Type: application/json');

require 'db.php';

$imagemNome = null;

/*
1. Upload da imagem (se existir)
*/
if (isset($_FILES['imagem']) && $_FILES['imagem']['error'] === UPLOAD_ERR_OK) {
    $ext = pathinfo($_FILES['imagem']['name'], PATHINFO_EXTENSION);
    $imagemNome = date('Ymd_His') . '.' . $ext;

    $destino = __DIR__ . '/imagens/' . $imagemNome;

    if (!move_uploaded_file($_FILES['imagem']['tmp_name'], $destino)) {
        http_response_code(500);
        echo json_encode([
            'success' => false,
            'error'   => 'Erro ao salvar a imagem'
        ]);
        exit;
    }
}

/*
2. Dados recebidos
*/
$texto            = $_POST['texto'] ?? null;
$id_chat_conjunto = $_POST['id_chat'] ?? null;

if ((!$texto || trim($texto) === '') && !$imagemNome) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error'   => 'Dados inválidos'
    ]);
    exit;
}

try {
    $pdo->beginTransaction();

    /*
    3. Criar o conjunto (mensagem também é conjunto)
    */
$stmt = $pdo->prepare(
    "INSERT INTO conjuntos (texto, imagem)
     VALUES (:texto, :imagem)"
);
$stmt->execute([
    ':texto'  => $texto,
    ':imagem' => $imagemNome
]);

$id_conjunto = $pdo->lastInsertId();

/*
4. Criar apenas uma revisão para o novo conjunto
*/
$stmtRevisao = $pdo->prepare(
    "INSERT INTO revisoes (id_chat_conjunto)
     VALUES (:id_chat_conjunto)"
);

$stmtRevisao->execute([
    ':id_chat_conjunto' => $id_conjunto
]);

    /*
    5. Relacionar com o conjunto pai (se existir)
    */
    if ($id_chat_conjunto !== 'no' && $id_chat_conjunto !== null) {
        $stmt = $pdo->prepare(
            "INSERT INTO conjuntos_relacoes (id_conjunto_filho, id_conjunto_pai)
             VALUES (:filho, :pai)"
        );
        $stmt->execute([
            ':filho' => $id_conjunto,
            ':pai'   => $id_chat_conjunto
        ]);
    }

    $pdo->commit();

    echo json_encode([
        'success'      => true,
        'id_conjunto'  => $id_conjunto
    ]);

} catch (PDOException $e) {
    $pdo->rollBack();
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error'   => $e->getMessage()
    ]);
}
