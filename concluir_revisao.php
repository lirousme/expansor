<?php
// expandir.php
header('Content-Type: application/json');

require 'db.php';

$input = json_decode(file_get_contents('php://input'), true);

if (
    !isset($input['id_chat']) ||
    !is_numeric($input['id_chat'])
) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => 'ID da chat inválido'
    ]);
    exit;
}

$id_chat_conjunto = (int)$input['id_chat'];

try {
    $pdo->beginTransaction();

    // busca a revisão do chat
    $stmt = $pdo->prepare("
        SELECT 
            r.id AS id_revisao,
            r.quantidade_revisoes,
            f.id AS id_mensagem,
            f.imagem
        FROM revisoes r
        JOIN mensagens f ON f.id_chat_conjunto = r.id_chat_conjunto
        WHERE r.id_chat_conjunto = :id_chat_conjunto
        LIMIT 1
    ");
    $stmt->execute([':id_chat_conjunto' => $id_chat_conjunto]);
    $revisao = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$revisao) {
        $pdo->rollBack();
        echo json_encode([
            'success' => false,
            'error' => 'Nenhuma revisão encontrada'
        ]);
        exit;
    }

    // incrementa +1 na quantidade_revisoes
    $stmt = $pdo->prepare("
        UPDATE revisoes
        SET quantidade_revisoes = quantidade_revisoes + 1,
            data_ultima_revisao = NOW()
        WHERE id = :id
    ");
    $stmt->execute([':id' => $revisao['id_revisao']]);

    // controle de concluidos_hoje do usuário id = 1
    $stmt = $pdo->prepare("
        SELECT concluidos_hoje, ultima_conclusao
        FROM usuarios
        WHERE id = 1
        LIMIT 1
    ");
    $stmt->execute();
    $usuario = $stmt->fetch(PDO::FETCH_ASSOC);

    $agora = date('Y-m-d H:i:s');
    $hoje = date('Y-m-d');
    $concluidosHoje = 1;

    if ($usuario && !empty($usuario['ultima_conclusao'])) {
        $dataUltima = date('Y-m-d', strtotime($usuario['ultima_conclusao']));
        if ($dataUltima === $hoje) {
            $concluidosHoje = (int)$usuario['concluidos_hoje'] + 1;
        }
    }

    $stmt = $pdo->prepare("
        UPDATE usuarios
        SET concluidos_hoje = :concluidos,
            ultima_conclusao = :ultima
        WHERE id = 1
    ");
    $stmt->execute([
        ':concluidos' => $concluidosHoje,
        ':ultima' => $agora
    ]);

    // verifica se a imagem da mensagem precisa ser removida
    if (!empty($revisao['imagem'])) {
        $stmt = $pdo->prepare("
            SELECT COUNT(*)
            FROM mensagens
            WHERE imagem = :imagem
        ");
        $stmt->execute([':imagem' => $revisao['imagem']]);
        $uso = (int)$stmt->fetchColumn();

        if ($uso === 0) {
            $caminho = __DIR__ . '/imagens/' . $revisao['imagem'];
            if (file_exists($caminho)) {
                unlink($caminho);
            }
        }
    }
    
    // depois de atualizar quantidade_revisoes
if ((int)$revisao['quantidade_revisoes'] + 1 >= 100) {
    // deleta mensagens do chat
    $stmt = $pdo->prepare("DELETE FROM mensagens WHERE id_chat_conjunto = :id_chat");
    $stmt->execute([':id_chat' => $id_chat_conjunto]);

    // deleta a revisão
    $stmt = $pdo->prepare("DELETE FROM revisoes WHERE id_chat_conjunto = :id_chat");
    $stmt->execute([':id_chat' => $id_chat_conjunto]);
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
