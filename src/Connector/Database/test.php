<?php
declare(strict_types=1);

include_once __DIR__ . '/../../../vendor/autoload.php';

use Tabula17\Satelles\Nexus\Utilis\Connector\Database\DbConfig;
use Tabula17\Satelles\Nexus\Utilis\Connector\Database\Driver\MysqliDriver;
use Tabula17\Satelles\Nexus\Utilis\Connector\Database\Driver\OciDriver;
use Tabula17\Satelles\Nexus\Utilis\Connector\Database\Driver\PdoDriver;
use Tabula17\Satelles\Nexus\Utilis\Connector\Database\Driver\SqlSrvDriver;
use Tabula17\Satelles\Nexus\Utilis\Connector\Database\DriversEnum;
use Tabula17\Satelles\Nexus\Utilis\Connector\Database\OperationsEnum;

$tiempo_inicio = microtime(true);
$memoria_inicio = memory_get_usage();

$mysql =
    [
        'host' => '10.230.50.39',
        'port' => 3306,
        'username' => 'sig',
        'password' => 'popungaycarioca',
        'dbname' => 'SIG',
        'driver' => DriversEnum::MYSQL
    ];
$ora = [
    'host' => '10.230.50.31',
    'port' => 1521,
    'dbname' => 'EBS_PROD',
    'username' => 'APPS',
    'password' => 'iw8lqf',
    'driver' => DriversEnum::ORACLE
];
$sqlSrv = [
    'host' => '10.190.42.1',
    'port' => 1433,
    'username' => 'sa',
    'password' => '_alpha%.Centauri',
    'dbname' => 'SIG',
    'driver' => DriversEnum::SQLSRV,
    'dsnOptions' => [
        'Encrypt' => 'yes',
        'TrustServerCertificate' => 'yes'
    ]
];

$dbConfig = new DbConfig($sqlSrv);
if ($dbConfig->canConnect()) {
    echo "Connection successful!";
} else {
    echo "Connection failed: " . $dbConfig->lastConnectionError;
}
$driver = new SqlSrvDriver($dbConfig);

$result = $driver->query("SELECT * FROM SIG.hr.Empresas_V e", [], OperationsEnum::SELECT);
echo "Query result: ";
foreach ($result->fetchAll() as $row) {
    echo var_export($row, true) . "\n";
}
$tiempo_fin = microtime(true);
$memoria_fin = memory_get_usage();
$memoria_pico = memory_get_peak_usage();
$tiempo_total = $tiempo_fin - $tiempo_inicio;
$memoria_consumida = $memoria_fin - $memoria_inicio;
echo "--- ESTADÍSTICAS DE EJECUCIÓN ---\n";
echo "Tiempo de ejecución: " . number_format($tiempo_total, 4) . " segundos\n";
echo "Memoria al inicio: " . formatearBytes($memoria_inicio) . "\n";
echo "Memoria al final: " . formatearBytes($memoria_fin) . "\n";
echo "Memoria neta consumida: " . formatearBytes($memoria_consumida) . "\n";
echo "Pico máximo de memoria: " . formatearBytes($memoria_pico) . "\n";

// Función auxiliar para hacer la lectura de memoria más humana
function formatearBytes($bytes, $precision = 2)
{
    $unidades = ['B', 'KB', 'MB', 'GB'];
    $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
    $pow = min($pow, count($unidades) - 1);
    $bytes /= 1024 ** $pow;
    return round($bytes, $precision) . ' ' . $unidades[$pow];
}