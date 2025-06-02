<?php
require_once 'Database.php';

class IndexController {
    private $db;

    public function __construct() {
        $this->db = new Database();
    }

    public function handleCriarIndice($table, $cols) {
        $response = ['indice_criado' => false, 'indice_existente' => false, 'mensagem' => ''];

        if (!$this->validateColumns($table, $cols)) {
            $response['mensagem'] = '⚠️ Colunas inválidas para criação do índice.';
            return $response;
        }

        $index = 'idx_' . $table . '_' . str_replace(',', '_', $cols);

        if ($this->db->indexExists($table, $index)) {
            $response['indice_existente'] = true;
            $response['mensagem'] = "⚠️ Índice '$index' já existe.";
        } elseif ($this->indexCoversColumns($table, $cols)) {
            $response['mensagem'] = "⚠️ Índice não criado: já coberto por outro índice existente.";
        } else {
            $sql = "CREATE INDEX `$index` ON `$table`($cols)";
            try {
                $this->db->query($sql);
                $response['indice_criado'] = true;
                $response['mensagem'] = "✅ Índice '$index' criado.";
                $this->db->query("ANALYZE TABLE `$table`");
            } catch (Exception $e) {
                $response['mensagem'] = '⚠️ Erro ao criar índice: ' . $e->getMessage();
            }
        }

        return $response;
    }

    private function indexCoversColumns($table, $selectedCols) {
        $result = $this->db->query("SHOW INDEX FROM `$table`");
        $existingIndexes = [];
        while ($row = $result->fetch_assoc()) {
            $indexName = $row['Key_name'];
            $column = $row['Column_name'];
            if (!isset($existingIndexes[$indexName])) {
                $existingIndexes[$indexName] = [];
            }
            $existingIndexes[$indexName][] = $column;
        }
        $selectedColsArray = explode(',', $selectedCols);
        foreach ($existingIndexes as $indexCols) {
            $matches = 0;
            foreach ($selectedColsArray as $col) {
                if (in_array($col, $indexCols)) $matches++;
            }
            if ($matches == count($selectedColsArray)) {
                return true;
            }
        }
        return false;
    }

    public function handleExecutarConsulta($table, $tipoConsulta, $colunasIndice, $execucoes = 10, $ignorarIndice = false) {
        $response = ['tempo_medio' => 0, 'total' => 0, 'dados' => [], 'explain' => []];
        $index = 'idx_' . $table . '_' . str_replace(',', '_', $colunasIndice);

        // Desativar cache de consulta para garantir resultados consistentes
        $this->db->query("SET SESSION query_cache_type = OFF");

        // Se "Sem Índice" for selecionado, remover todos os índices dinâmicos da tabela, se existirem
        if ($ignorarIndice) {
            $result = $this->db->query("SHOW INDEX FROM `$table`");
            $indicesRemoved = false;
            while ($row = $result->fetch_assoc()) {
                $indexName = $row['Key_name'];
                if ($indexName !== 'PRIMARY' && strpos($indexName, 'idx_') === 0) {
                    try {
                        $this->db->query("DROP INDEX `$indexName` ON `$table`");
                        error_log("Índice '$indexName' removido da tabela '$table'.");
                        $indicesRemoved = true;
                    } catch (Exception $e) {
                        // Ignorar erros se o índice não existir
                        if (strpos($e->getMessage(), "doesn't exist") === false) {
                            error_log("Erro ao remover índice '$indexName': " . $e->getMessage());
                        }
                    }
                }
            }
            if ($indicesRemoved) {
                $this->db->query("ANALYZE TABLE `$table`");
            }
        }

        // Verificar se o índice existe antes de passar para buildQuery
        $indexExists = !$ignorarIndice && $this->db->indexExists($table, $index);

        list($query, $params, $types) = $this->buildQuery($table, $tipoConsulta, $index, $indexExists);

        // Log da consulta para depuração
        error_log("Consulta gerada: $query");

        $tempos = [];
        $lastParams = $params;
        for ($i = 0; $i < $execucoes; $i++) {
            $stmt = $this->db->prepare($query);
            $currentParams = $params;
            if ($table === 'vendas' && $tipoConsulta === 'data') {
                $offset = $i;
                $startDate = date('Y-m-d', strtotime('2023-01-01 + ' . $offset . ' days'));
                $endDate = date('Y-m-d', strtotime('2023-01-31 + ' . $offset . ' days'));
                $currentParams = [$startDate, $endDate];
            } elseif ($table === 'vendas' && $tipoConsulta === 'cliente') {
                $currentParams = [rand(1, 1000)];
            } elseif ($table === 'vendas' && $tipoConsulta === 'vendedor_data') {
                $offset = $i;
                $startDate = date('Y-m-d', strtotime('2023-01-01 + ' . $offset . ' days'));
                $endDate = date('Y-m-d', strtotime('2023-01-31 + ' . $offset . ' days'));
                $currentParams = [rand(1, 100), $startDate, $endDate];
            }
            $stmt->bind_param($types, ...$currentParams);
            $start = microtime(true);
            $stmt->execute();
            $tempos[] = round((microtime(true) - $start) * 1000, 4);
            if ($i === 0) {
                $result = $stmt->get_result();
                $response['total'] = $result->num_rows;
                while ($row = $result->fetch_assoc()) {
                    $response['dados'][] = $row;
                }
            }
            $lastParams = $currentParams;
            $stmt->close();
        }

        if (count($tempos) > 2) {
            sort($tempos);
            array_shift($tempos);
            array_pop($tempos);
        }
        $response['tempo_medio'] = round(array_sum($tempos) / count($tempos), 4);

        $stmt = $this->db->prepare("EXPLAIN $query");
        $stmt->bind_param($types, ...$lastParams);
        $stmt->execute();
        $result = $stmt->get_result();
        while ($row = $result->fetch_assoc()) {
            $response['explain'][] = $row;
        }
        $stmt->close();

        return $response;
    }

    private function buildQuery($table, $tipoConsulta, $index, $indexExists) {
        if ($table === 'vendas') {
            if ($tipoConsulta === 'data') {
                $query = "SELECT id_venda, cliente_id, total_venda, data_venda FROM vendas";
                if ($indexExists) {
                    $query .= " FORCE INDEX (`$index`)";
                }
                $query .= " WHERE data_venda BETWEEN ? AND ?";
                return [$query, ['2023-01-01', '2023-01-31'], 'ss'];
            } elseif ($tipoConsulta === 'cliente') {
                $query = "SELECT id_venda, cliente_id, total_venda, data_venda FROM vendas";
                if ($indexExists) {
                    $query .= " FORCE INDEX (`$index`)";
                }
                $query .= " WHERE cliente_id = ?";
                return [$query, [rand(1, 1000)], 'i'];
            } elseif ($tipoConsulta === 'vendedor_data') {
                $query = "SELECT id_venda, cliente_id, total_venda, data_venda FROM vendas";
                if ($indexExists) {
                    $query .= " FORCE INDEX (`$index`)";
                }
                $query .= " WHERE vendedor_id = ? AND data_venda BETWEEN ? AND ?";
                return [$query, [rand(1, 100), '2023-01-01', '2023-01-31'], 'iss'];
            }
        } elseif ($table === 'pedidos') {
            $query = "SELECT * FROM pedidos";
            if ($indexExists) {
                $query .= " FORCE INDEX (`$index`)";
            }
            $query .= " WHERE data_pedido BETWEEN ? AND ?";
            return [$query, ['2023-01-01', '2023-01-31'], 'ss'];
        } elseif ($table === 'produtos') {
            $query = "SELECT * FROM produtos";
            if ($indexExists) {
                $query .= " FORCE INDEX (`$index`)";
            }
            $query .= " WHERE nome LIKE ?";
            return [$query, ['%produto%'], 's'];
        }
        throw new Exception('Combinação de tabela e tipo de consulta inválida.');
    }

    private function validateColumns($table, $cols) {
        $validColumns = [
            'vendas' => ['data_venda', 'cliente_id', 'vendedor_id'],
            'pedidos' => ['data_pedido'],
            'produtos' => ['nome']
        ];
        $colsArray = explode(',', $cols);
        foreach ($colsArray as $col) {
            if (!in_array($col, $validColumns[$table])) {
                return false;
            }
        }
        return true;
    }
}