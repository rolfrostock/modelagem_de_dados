<?php
require_once __DIR__ . '/classes/Database.php';

ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', 'php_errors.log');
error_reporting(E_ALL);

header('Content-Type: application/json');

try {
    $db = new Database();
} catch (Exception $e) {
    error_log("Database connection error: " . $e->getMessage());
    echo json_encode(['erro' => 'Erro ao conectar com o banco']);
    exit;
}

$acao = $_GET['acao'] ?? null;
$table = $_GET['table'] ?? 'vendas';
$tipoConsulta = $_GET['tipoConsulta'] ?? 'data';
$colunas = $_GET['colunas'] ?? '';
$execucoes = intval($_GET['execucoes'] ?? 10);

$validTables = ['vendas', 'pedidos', 'produtos'];
if (!in_array($table, $validTables, true)) {
    echo json_encode(['erro' => 'Tabela inválida']);
    exit;
}

if ($acao !== 'remover_todos') {
    $cols = preg_replace('/[^a-zA-Z0-9_,]/', '', $colunas);
    if (!$cols) {
        echo json_encode(['erro' => 'Nenhuma coluna selecionada']);
        exit;
    }

    try {
        $result = $db->query("SHOW COLUMNS FROM `$table`");
        $validColumns = [];
        while ($row = $result->fetch_assoc()) {
            $validColumns[] = $row['Field'];
        }
        $selectedCols = explode(',', $cols);
        foreach ($selectedCols as $col) {
            if (!in_array($col, $validColumns)) {
                echo json_encode(['erro' => "Coluna inválida: $col"]);
                exit;
            }
        }
    } catch (Exception $e) {
        error_log("Column validation error: " . $e->getMessage());
        echo json_encode(['erro' => 'Erro ao validar colunas']);
        exit;
    }

    $index = 'idx_' . $table . '_' . str_replace(',', '_', $cols);
    $response = [
        'mensagem' => '',
        'dados' => [],
        'tempo_medio' => 0,
        'total' => 0,
        'explain' => [],
        'indice_existente' => $db->indexExists($table, $index),
    ];
} else {
    $index = '';
    $response = [
        'mensagem' => '',
        'dados' => [],
        'tempo_medio' => 0,
        'total' => 0,
        'explain' => [],
        'indice_existente' => false,
    ];
}

try {
    if ($acao === 'criar') {
        if (!$response['indice_existente']) {
            $sql = "CREATE INDEX `$index` ON `$table`($cols)";
            if ($db->query($sql)) {
                $db->query("ANALYZE TABLE `$table`");
                $response['mensagem'] = "✅ Índice '$index' criado.";
                $response['indice_existente'] = true;
            } else {
                throw new Exception("Falha ao criar índice: " . $db->getMysqli()->error);
            }
        } else {
            $response['mensagem'] = "⚠️ Índice já existe.";
        }
        echo json_encode($response);
        exit;
    }

    if ($acao === 'remover') {
        if ($response['indice_existente']) {
            $sql = "DROP INDEX `$index` ON `$table`";
            if ($db->query($sql)) {
                $response['mensagem'] = "✅ Índice '$index' removido.";
                $response['indice_existente'] = false;
            } else {
                throw new Exception("Falha ao remover índice: " . $db->getMysqli()->error);
            }
        } else {
            $response['mensagem'] = "ℹ️ Índice '$index' não existe na tabela '$table'.";
            $response['indice_existente'] = false;
        }
        echo json_encode($response);
        exit;
    }

    if ($acao === 'remover_todos') {
        try {
            $db->dropAllCustomIndexes($table);
            $response['mensagem'] = "🧹 Todos os índices com prefixo 'idx_' foram removidos da tabela '$table'.";
        } catch (Exception $e) {
            $response['mensagem'] = "❌ Erro ao remover índices: " . $e->getMessage();
        }
        echo json_encode($response);
        exit;
    }

    if ($acao === 'testar_insert') {
        $usarIndice = filter_var($_GET['usarIndice'] ?? 'false', FILTER_VALIDATE_BOOLEAN);
        
        // Remover todos os registros da tabela pedidos para garantir um teste limpo
        $db->query("TRUNCATE TABLE pedidos");

        // Verificar se o índice existe
        $indexExists = $db->indexExists($table, $index);
        error_log("usarIndice: " . ($usarIndice ? 'true' : 'false'));
        error_log("indice_existente: " . ($indexExists ? 'true' : 'false'));
        error_log("index: $index");

        // Número de inserções para o teste
        $numInsercoes = 10000;
        $tempos = [];

        // Preparar a consulta de inserção
        $query = "INSERT INTO pedidos (data_pedido, cliente_id, total_pedido) VALUES (?, ?, ?)";
        $stmt = $db->prepare($query);
        if (!$stmt) {
            throw new Exception("Erro na preparação do INSERT: " . $db->getMysqli()->error);
        }

        // Gerar dados aleatórios para inserção
        for ($i = 0; $i < $execucoes; $i++) {
            $start = microtime(true);
            
            for ($j = 0; $j < $numInsercoes; $j++) {
                $data_pedido = sprintf('2023-%02d-%02d', rand(1, 12), rand(1, 28));
                $cliente_id = rand(1, 1000);
                $total_pedido = rand(100, 10000) / 100.0;
                
                $stmt->bind_param('sid', $data_pedido, $cliente_id, $total_pedido);
                if (!$stmt->execute()) {
                    throw new Exception("Erro na execução do INSERT: " . $stmt->error);
                }
            }

            $tempos[] = round((microtime(true) - $start) * 1000, 4);
        }

        $stmt->close();

        $response['tempo_medio'] = round(array_sum($tempos) / count($tempos), 4);
        $response['indice_existente'] = $indexExists;
        $response['mensagem'] = "✅ Teste de $numInsercoes inserções concluído.";
    }

    if ($acao === 'executar') {
        $usarIndice = $_POST['ignorarIndice'] ?? 'false';
        $usarIndice = filter_var($usarIndice, FILTER_VALIDATE_BOOLEAN);
        
        error_log("usarIndice: " . ($usarIndice ? 'true' : 'false'));
        error_log("indice_existente: " . ($response['indice_existente'] ? 'true' : 'false'));
        error_log("index: $index");

        if ($usarIndice || !$response['indice_existente']) {
            $indexHint = "";
        } else {
            $indexHint = " FORCE INDEX (`$index`)";
        }
        
        error_log("indexHint: $indexHint");

        switch ($table) {
            case 'pedidos':
                if ($tipoConsulta === 'cliente') {
                    $query = "SELECT id_pedido, cliente_id, total_pedido FROM pedidos$indexHint WHERE cliente_id = ?";
                    $types = 'i';
                    $params = [rand(1, 1000)];
                } else {
                    $query = "SELECT id_pedido, cliente_id, total_pedido FROM pedidos$indexHint WHERE data_pedido BETWEEN ? AND ?";
                    $types = 'ss';
                    $params = ['2023-01-01', '2023-01-10'];
                }
                break;

            case 'produtos':
                $query = "SELECT id_produto, nome_produto, preco FROM produtos$indexHint WHERE nome_produto LIKE ?";
                $types = 's';
                $params = ['Produto%'];
                break;

            default:
                if ($tipoConsulta === 'cliente') {
                    $query = "SELECT id_venda, cliente_id, total_venda, data_venda FROM vendas$indexHint WHERE cliente_id = ?";
                    $types = 'i';
                    $params = [rand(1, 1000)];
                } elseif ($tipoConsulta === 'vendedor_data') {
                    $query = "SELECT id_venda, cliente_id, total_venda, data_venda FROM vendas$indexHint WHERE vendedor_id = ? AND data_venda BETWEEN ? AND ?";
                    $types = 'iss';
                    $params = [rand(1, 100), '2023-01-01', '2023-01-10'];
                } else {
                    $query = "SELECT id_venda, cliente_id, total_venda, data_venda FROM vendas$indexHint WHERE data_venda BETWEEN ? AND ?";
                    $types = 'ss';
                    $params = ['2023-01-01', '2023-01-10'];
                }
        }

        $tempos = [];
        for ($i = 0; $i < $execucoes; $i++) {
            $stmt = $db->prepare($query);
            if (!$stmt) {
                throw new Exception("Erro na preparação da query: " . $db->getMysqli()->error);
            }
            $stmt->bind_param($types, ...$params);
            $start = microtime(true);
            if (!$stmt->execute()) {
                throw new Exception("Erro na execução da query: " . $stmt->error);
            }
            $result = $stmt->get_result();
            $tempos[] = round((microtime(true) - $start) * 1000, 4);

            if ($i === 0) {
                $response['total'] = $result->num_rows;
                while ($row = $result->fetch_assoc()) {
                    $response['dados'][] = $row;
                }
            }
            $stmt->close();
        }

        $response['tempo_medio'] = round(array_sum($tempos) / count($tempos), 4);

        $stmtExp = $db->prepare("EXPLAIN $query");
        if (!$stmtExp) {
            throw new Exception("Erro na preparação do EXPLAIN: " . $db->getMysqli()->error);
        }
        $stmtExp->bind_param($types, ...$params);
        if (!$stmtExp->execute()) {
            throw new Exception("Erro na execução do EXPLAIN: " . $stmtExp->error);
        }
        $expResult = $stmtExp->get_result();
        while ($r = $expResult->fetch_assoc()) {
            $response['explain'][] = $r;
        }
        $stmtExp->close();
    }
} catch (Exception $e) {
    error_log("Error in processa_acao.php: " . $e->getMessage());
    echo json_encode(['erro' => $e->getMessage()]);
    exit;
}

$db->close();
echo json_encode($response);