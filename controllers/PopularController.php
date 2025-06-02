<?php
require_once __DIR__ . '/../classes/Database.php';

class PopularController {
    public function handle() {
        header('Content-Type: application/json');

        if ($_GET['acao'] !== 'popular') {
            http_response_code(400);
            echo json_encode(['erro' => '❌ Ação inválida.']);
            exit;
        }

        $total = intval($_GET['total'] ?? 1000);
        $batch = 5000;
        $tables = ['vendas', 'pedidos', 'produtos'];

        try {
            $db = new Database();
        } catch (Exception $e) {
            error_log("Database error: " . $e->getMessage());
            http_response_code(500);
            echo json_encode(['erro' => 'Erro ao conectar com o banco']);
            exit;
        }

        $resumo = [];

        try {
            foreach ($tables as $table) {
                $db->query("TRUNCATE TABLE `$table`");
                $inserted = 0;

                while ($inserted < $total) {
                    $current = min($batch, $total - $inserted);
                    $placeholders = [];
                    $params = [];

                    for ($i = 0; $i < $current; $i++) {
                        switch ($table) {
                            case 'pedidos':
                                $d = date('Y-m-d', strtotime('2023-01-01 +' . rand(0, 364) . ' days'));
                                $c = rand(1, 1000);
                                $v = round(rand(100, 2000) + rand()/getrandmax(), 2);
                                $placeholders[] = '(?, ?, ?)';
                                array_push($params, $d, $c, $v);
                                break;

                            case 'produtos':
                                $n = 'Produto ' . str_pad($inserted + $i + 1, 5, '0', STR_PAD_LEFT);
                                $p = round(rand(10, 500) + rand()/getrandmax(), 2);
                                $placeholders[] = '(?, ?)';
                                array_push($params, $n, $p);
                                break;

                            default: // vendas
                                $id = $inserted + $i + 1;
                                $d = date('Y-m-d', strtotime('2023-01-01 +' . rand(0, 364) . ' days'));
                                $c = rand(1, 1000);
                                $v = round(rand(100, 1000) + rand()/getrandmax(), 2);
                                $vend = rand(1, 100);
                                $placeholders[] = '(?, ?, ?, ?, ?)';
                                array_push($params, $id, $d, $v, $c, $vend);
                        }
                    }

                    switch ($table) {
                        case 'pedidos':
                            $sql = "INSERT INTO pedidos (data_pedido, cliente_id, total_pedido) VALUES " . implode(', ', $placeholders);
                            $types = str_repeat('sid', $current);
                            break;

                        case 'produtos':
                            $sql = "INSERT INTO produtos (nome_produto, preco) VALUES " . implode(', ', $placeholders);
                            $types = str_repeat('sd', $current);
                            break;

                        default:
                            $sql = "INSERT INTO vendas (id_venda, data_venda, total_venda, cliente_id, vendedor_id) VALUES " . implode(', ', $placeholders);
                            $types = str_repeat('isdii', $current);
                    }

                    $stmt = $db->prepare($sql);
                    if (!$stmt) {
                        throw new Exception("Erro na preparação: " . $db->getMysqli()->error);
                    }

                    $stmt->bind_param($types, ...$params);
                    $stmt->execute();
                    $stmt->close();
                    $inserted += $current;
                }

                $resumo[] = "✅ Inseridos {$total} registros na tabela {$table}.";
            }

            echo json_encode([
                'sucesso' => true,
                'mensagem' => implode("\n", $resumo)
            ]);
        } catch (Throwable $e) {
            error_log("Error in PopularController: " . $e->getMessage());
            http_response_code(500);
            echo json_encode([
                'sucesso' => false,
                'erro' => $e->getMessage()
            ]);
        } finally {
            $db->close();
        }
    }
}

$controller = new PopularController();
$controller->handle();