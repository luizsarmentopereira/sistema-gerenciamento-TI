<?php
// conexao_exemplo.php - Exemplo de configuração do PostgreSQL
// Renomeie este arquivo para conexao.php e ajuste as credenciais

$host = 'localhost';
$port = '5432';
$dbname = 'gerencia';
$user = 'postgres';
$password = 'sua_senha_aqui';

try {
    $conn = new PDO("pgsql:host=$host;port=$port;dbname=$dbname", $user, $password);
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $conn->exec("SET NAMES 'UTF8'");
} catch(PDOException $e) {
    die("Erro na conexão: " . $e->getMessage());
}
?>