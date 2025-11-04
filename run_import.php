<?php
require 'vendor/autoload.php';
$app = require 'bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Maatwebsite\Excel\Excel as ExcelSvc;
use App\Imports\SanPhamImport;
use Illuminate\Database\QueryException;

$import = new SanPhamImport();
$file   = storage_path('app/import/sanpham4112025.xlsx');
if (!file_exists($file)) { fwrite(STDERR, "MISSING: $file\n"); exit(1); }

try {
    app(ExcelSvc::class)->import($import, $file);
    echo "=== IMPORT DONE ===\n";
    printf("ThanhCong: %d\n", $import->getThanhCong());
    printf("ThatBai  : %d\n", $import->getThatBai());
} catch (QueryException $e) {
    echo "[QUERY EXCEPTION]\n";
    echo $e->getMessage(), "\n";
    echo "SQL= ", $e->getSql(), "\n";
    echo "BINDINGS= ", json_encode($e->getBindings(), JSON_UNESCAPED_UNICODE), "\n";
    exit(2);
} catch (Throwable $e) {
    echo "[THROWABLE]\n", $e->getMessage(), "\nFILE=", $e->getFile(), ":", $e->getLine(), "\n";
    exit(3);
}
