<?php
// 4º parâmetro é nome do DB, 5º é porta
// depois: incluindo a porta
$mysqli = new mysqli("localhost", "root", "", "sistema_vendas", 3308);

if ($mysqli->connect_error) {
    die("Erro na conexão: " . $mysqli->connect_error);
}
?>
