<?php
require 'vendor/autoload.php';
$app = require 'bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Maatwebsite\Excel\Excel as ExcelSvc;
use App\Imports\KhachHangImport;

$path = storage_path('app/import/khachhang.xlsx');
if (!file_exists($path)) { fwrite(STDERR, "MISSING: $path\n"); exit(1); }

$imp = new KhachHangImport($path);
app(ExcelSvc::class)->import($imp, $path);

echo "=== KH IMPORT DONE ===\n";
printf("ThanhCong: %d\n", $imp->getThanhCong());
printf("ThatBai  : %d\n", $imp->getThatBai());
