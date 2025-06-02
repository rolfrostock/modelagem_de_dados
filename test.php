<?php
$mysqli = new mysqli("localhost", "root", "", "sistema_vendas");
if ($mysqli->connect_error) {
    die("Falha: " . $mysqli->connect_error);
}
echo "Conexão OK na porta 3308!";
