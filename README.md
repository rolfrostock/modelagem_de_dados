<<<<<<< HEAD
# Projeto: Otimização de Consultas SQL com e sem Índices

Este projeto tem como objetivo demonstrar, na prática, a **melhoria de performance** em consultas SQL com o uso de **índices** em um banco de dados MySQL.

A aplicação foi desenvolvida com:

✅ PHP (com orientação a objetos)  
✅ Bootstrap 5 (para estilização)  
✅ MySQL (para modelagem)  
✅ VSCode (para desenvolvimento)  
✅ Wamp (para execução local)

---

## ✅ Estrutura dos Arquivos
```
projeto_vendas/
├── Database.php # Classe de conexão com construtor
├── demo_indice.php # Interface
└── setup.sql # Script SQL
```
---

## ✅ Pré-requisitos

- PHP = 8.1.32  
- MySQL Server  8.0.42
- Apache (via Wamp)  
- MySQL  

---

## ✅ Passos para execução

### 1) Configurar o banco de dados

- **phpMyAdmin**  
  
- Execução do script **`setup.sql`** para:

✅ Criar banco `sistema_vendas`  
✅ Criar tabela `vendas`  
✅ Popular com **100 mil registros**  
✅ Criar o índice `idx_vendas_data`

---

## ✅ Script SQL Completo (`setup.sql`)

```sql
-- Apagar banco se já existir
DROP DATABASE IF EXISTS sistema_vendas;

-- Criar banco de dados
CREATE DATABASE sistema_vendas;

-- Usar o banco criado
USE sistema_vendas;

-- Criar tabela 'vendas'
CREATE TABLE vendas (
    id_venda INT PRIMARY KEY,
    data_venda DATE,
    total_venda DECIMAL(10,2),
    cliente_id INT,
    vendedor_id INT
);

-- Criar procedure para popular com 100 mil registros
DELIMITER //
CREATE PROCEDURE popular_vendas()
BEGIN
  DECLARE i INT DEFAULT 1;
  WHILE i <= 100000 DO
    INSERT INTO vendas (id_venda, data_venda, total_venda, cliente_id, vendedor_id)
    VALUES (
      i,
      DATE_ADD('2023-01-01', INTERVAL FLOOR(RAND() * 365) DAY),
      ROUND(RAND() * 1000 + 100, 2),
      FLOOR(RAND() * 1000),
      FLOOR(RAND() * 100)
    );
    SET i = i + 1;
  END WHILE;
END;
//
DELIMITER ;

-- Executar a procedure para popular a tabela
CALL popular_vendas();

-- Criar índice para otimizar consultas por data
CREATE INDEX idx_vendas_data ON vendas(data_venda);

```

### 2) Configurar o ambiente PHP

- INserção arquivos `.php` na pasta do servidor local:  
  `C:/wamp/www/projeto_vendas/`

- Inicialização serviços **Apache** e **MySQL** no Wamp.

---

### 3) Configurações acesso BD

```php
private $host = "localhost";
private $user = "root";
private $pass = "";
private $dbname = "sistema_vendas";
```
---

### 4) Executar a demonstração

http://localhost/projeto_vendas/index.php

Botões disponíveis:

✅ Criar Índice → executa CREATE INDEX
✅ Remover Índice → executa DROP INDEX
✅ Executar Consulta → executa a consulta, mede tempo e mostra resultados
✅ Visualizar EXPLAIN → automaticamente exibido após cada execução


✅ Créditos
Desenvolvido como parte da atividade de Modelagem e Otimização de Banco de Dados.

[Rolf Heinz Rostock]
Data: [26052025]
=======
# modelagem_de_dados
Estudo de caso UniDomBosco
>>>>>>> 723c7a78b4bae85ee0ba7217bf7973662328e193
