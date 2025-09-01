<?php
// Incluir el autoloader de Composer, que carga todas las librerías
require_once __DIR__ . '/../../vendor/autoload.php';
require_once __DIR__ . '/../db_connect.php';

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

// Obtener los datos de la BD
$result = $conn->query("SELECT id, name, ip_address, api_user, api_password, lat, lng FROM routers");
$routers = [];
if ($result && $result->num_rows > 0) {
    $rowNum = 2; // Empezamos a escribir datos en la fila 2
    while($row = $result->fetch_assoc()) {
        $routers[] = $row;
    }
}
$conn->close();

// Crear una nueva hoja de cálculo
$spreadsheet = new Spreadsheet();
$sheet = $spreadsheet->getActiveSheet();
$sheet->setTitle('MikroTik Routers');

// Escribir los encabezados
$sheet->setCellValue('A1', 'ID');
$sheet->setCellValue('B1', 'Nombre');
$sheet->setCellValue('C1', 'IP/Dominio');
$sheet->setCellValue('D1', 'Usuario API');
$sheet->setCellValue('E1', 'Password API');
$sheet->setCellValue('F1', 'Latitud');
$sheet->setCellValue('G1', 'Longitud');

// Poner los encabezados en negrita
$sheet->getStyle('A1:G1')->getFont()->setBold(true);

// Escribir los datos de cada router
$rowNum = 2;
foreach ($routers as $router) {
    $sheet->setCellValue('A' . $rowNum, $router['id']);
    $sheet->setCellValue('B' . $rowNum, $router['name']);
    $sheet->setCellValue('C' . $rowNum, $router['ip_address']);
    $sheet->setCellValue('D' . $rowNum, $router['api_user']);
    $sheet->setCellValue('E' . $rowNum, $router['api_password']);
    $sheet->setCellValue('F' . $rowNum, $router['lat']);
    $sheet->setCellValue('G' . $rowNum, $router['lng']);
    $rowNum++;
}

// Auto-ajustar el ancho de las columnas
foreach (range('A', 'G') as $columnID) {
    $sheet->getColumnDimension($columnID)->setAutoSize(true);
}

// Crear el "escritor" de archivos XLSX
$writer = new Xlsx($spreadsheet);

// Headers para forzar la descarga del archivo Excel
header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header('Content-Disposition: attachment; filename="mikrotik_backup_' . date('Y-m-d') . '.xlsx"');
header('Cache-Control: max-age=0');

// Escribir el archivo en la salida
$writer->save('php://output');
exit;
?>