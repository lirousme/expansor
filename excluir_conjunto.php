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
    // 1️⃣ Descobre todos os IDs do conjunto e seus filhos recursivamente
    $sqlFamilia = "
        WITH RECURSIVE familia AS (
            SELECT id AS id_conjunto
            FROM conjuntos
            WHERE id = :id

            UNION ALL

            SELECT cr.id_conjunto_filho
            FROM conjuntos_relacoes cr
            INNER JOIN familia f ON f.id_conjunto = cr.id_conjunto_pai
        )
        SELECT id_conjunto FROM familia
    ";

    $stmtFam = $pdo->prepare($sqlFamilia);
    $stmtFam->execute([':id' => $id]);
    $ids = $stmtFam->fetchAll(PDO::FETCH_COLUMN);

    if (!$ids) {
        http_response_code(404);
        echo json_encode([
            'success' => false,
            'error' => 'Conjunto não encontrado'
        ]);
        exit;
    }

    $pdo->beginTransaction();

    // 2️⃣ Itera sobre todos os IDs encontrados
    foreach ($ids as $conjuntoId) {
        // pega tipo e imagem
        $stmt = $pdo->prepare("SELECT tipo, imagem FROM conjuntos WHERE id = :id");
        $stmt->execute([':id' => $conjuntoId]);
        $conjunto = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$conjunto) continue;

        $tipo = (int)$conjunto['tipo'];
        $imagem = $conjunto['imagem'];

        if ($tipo === 0) {
            ////////////////////////////////
            // CASO FOR CONJUNTO TIPO 0
            $stmt = $pdo->prepare("DELETE FROM conjuntos WHERE id = :id");
            $stmt->execute([':id' => $conjuntoId]);

            if ($imagem) {
                // verifica se outro conjunto usa a mesma imagem
                $stmt = $pdo->prepare("SELECT COUNT(*) FROM conjuntos WHERE imagem = :imagem");
                $stmt->execute([':imagem' => $imagem]);
                $uso = (int)$stmt->fetchColumn();

                if ($uso === 0) {
                    $caminho = __DIR__ . '/imagens/' . $imagem;
                    if (file_exists($caminho)) {
                        unlink($caminho);
                    }
                }
            }
            ////////////////////////////////
        } elseif ($tipo === 1) {
            ////////////////////////////////
            // CASO FOR CONJUNTO TIPO 1
            // 1️⃣ Excluir mensagens associadas
            $stmtMsg = $pdo->prepare("SELECT id, imagem FROM mensagens WHERE id_chat_conjunto = :id");
            $stmtMsg->execute([':id' => $conjuntoId]);
            $mensagens = $stmtMsg->fetchAll(PDO::FETCH_ASSOC);

            foreach ($mensagens as $msg) {
                $msgId = $msg['id'];
                $msgImagem = $msg['imagem'];

                if ($msgImagem) {
                    // verifica se outra mensagem usa a mesma imagem
                    $stmtCheck = $pdo->prepare("SELECT COUNT(*) FROM mensagens WHERE imagem = :imagem AND id <> :id");
                    $stmtCheck->execute([':imagem' => $msgImagem, ':id' => $msgId]);
                    $uso = (int)$stmtCheck->fetchColumn();

                    if ($uso === 0) {
                        $caminho = __DIR__ . '/imagens/' . $msgImagem;
                        if (file_exists($caminho)) {
                            unlink($caminho);
                        }
                    }
                }

                // exclui a mensagem
                $stmtDelMsg = $pdo->prepare("DELETE FROM mensagens WHERE id = :id");
                $stmtDelMsg->execute([':id' => $msgId]);
            }

            // depois, exclui o conjunto em si
            $stmt = $pdo->prepare("DELETE FROM conjuntos WHERE id = :id");
            $stmt->execute([':id' => $conjuntoId]);
            ////////////////////////////////
        } elseif ($tipo === 2) {
            ////////////////////////////////
            // CASO FOR CONJUNTO TIPO 2
            // mantém espaço para lógica futura
            ////////////////////////////////
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
