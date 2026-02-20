<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// adicionar_mensagem.php
header('Content-Type: application/json');

require 'db.php';

$imagemNome = null;

// trata upload da imagem (se existir)
if (isset($_FILES['imagem']) && $_FILES['imagem']['error'] === UPLOAD_ERR_OK) {
    $ext = pathinfo($_FILES['imagem']['name'], PATHINFO_EXTENSION);
    $imagemNome = date('Ymd_His') . '.' . $ext;

    $destino = __DIR__ . '/imagens/' . $imagemNome;

    if (!move_uploaded_file($_FILES['imagem']['tmp_name'], $destino)) {
        http_response_code(500);
        echo json_encode([
            'success' => false,
            'error' => 'Erro ao salvar a imagem'
        ]);
        exit;
    }
}

// dados de texto e chat
$texto = $_POST['texto'] ?? null;
$id_chat_conjunto = $_POST['id_chat'] ?? null;

if (
    ((!$texto || trim($texto) === '') && !$imagemNome) ||
    !$id_chat_conjunto
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

    // insere a mensagem já vinculada ao chat
    $stmt = $pdo->prepare(
        "INSERT INTO mensagens (texto, imagem, id_chat_conjunto)
         VALUES (:texto, :imagem, :id_chat_conjunto)"
    );
    $stmt->execute([
        ':texto'            => $texto,
        ':imagem'           => $imagemNome,
        ':id_chat_conjunto' => $id_chat_conjunto
    ]);

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
