<?php
// excluir_mensagem.php
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
    $pdo->beginTransaction();

    // busca a imagem do mensagem
    $stmt = $pdo->prepare(
        "SELECT imagem FROM mensagens WHERE id = :id"
    );
    $stmt->execute([':id' => $id]);
    $imagem = $stmt->fetchColumn();

    // exclui o mensagem
    $stmt = $pdo->prepare(
        "DELETE FROM mensagens WHERE id = :id"
    );
    $stmt->execute([':id' => $id]);

    if ($imagem) {
        // verifica se outro mensagem usa a mesma imagem
        $stmt = $pdo->prepare(
            "SELECT COUNT(*) FROM mensagens WHERE imagem = :imagem"
        );
        $stmt->execute([':imagem' => $imagem]);
        $uso = (int)$stmt->fetchColumn();

        // se ninguém mais usa, exclui o arquivo
        if ($uso === 0) {
            $caminho = __DIR__ . '/imagens/' . $imagem;
            if (file_exists($caminho)) {
                unlink($caminho);
            }
        }
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
