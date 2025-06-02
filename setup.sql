-- setup.sql
DROP DATABASE IF EXISTS sistema_vendas;
CREATE DATABASE sistema_vendas;
USE sistema_vendas;

CREATE TABLE vendas (
    id_venda INT PRIMARY KEY,
    data_venda DATE,
    total_venda DECIMAL(10,2),
    cliente_id INT,
    vendedor_id INT
);

CREATE TABLE pedidos (
    id_pedido INT AUTO_INCREMENT PRIMARY KEY,
    data_pedido DATE,
    cliente_id INT,
    total_pedido DECIMAL(10,2)
);

CREATE TABLE produtos (
    id_produto INT AUTO_INCREMENT PRIMARY KEY,
    nome_produto VARCHAR(255),
    preco DECIMAL(10,2)
);

-- Índices opcionais iniciais
-- CREATE INDEX idx_vendas_data    ON vendas(data_venda);
-- CREATE INDEX idx_pedidos_data   ON pedidos(data_pedido);
-- CREATE INDEX idx_produtos_nome ON produtos(nome_produto);