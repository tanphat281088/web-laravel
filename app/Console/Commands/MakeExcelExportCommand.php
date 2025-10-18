<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Str;
use Illuminate\Filesystem\Filesystem;

class MakeExcelExportCommand extends Command
{
  /**
   * The name and signature of the console command.
   *
   * @var string
   */
  protected $signature = 'make:excel-export {name : Tên của class export}';

  /**
   * The console command description.
   *
   * @var string
   */
  protected $description = 'Tạo class export Excel mới từ stub';

  /**
   * The filesystem instance.
   *
   * @var \Illuminate\Filesystem\Filesystem
   */
  protected $files;

  /**
   * Create a new command instance.
   *
   * @param  \Illuminate\Filesystem\Filesystem  $files
   * @return void
   */
  public function __construct(Filesystem $files)
  {
    parent::__construct();
    $this->files = $files;
  }

  /**
   * Execute the console command.
   *
   * @return int
   */
  public function handle()
  {
    $name = $this->argument('name');

    // Kiểm tra nếu tên không có hậu tố "Export", thêm vào
    if (!Str::endsWith($name, 'Export')) {
      $name = $name . 'Export';
    }

    $className = Str::studly($name);
    $namespace = 'App\\Exports';

    // Đường dẫn đến file sẽ tạo
    $path = app_path('Exports/' . $className . '.php');

    // Kiểm tra xem file đã tồn tại chưa
    if ($this->files->exists($path)) {
      $this->error('Class ' . $className . ' đã tồn tại!');
      return 1;
    }

    // Đảm bảo thư mục tồn tại
    $this->files->ensureDirectoryExists(dirname($path));

    // Đọc nội dung từ file stub
    $stub = $this->files->get(base_path('stubs/excel/export.stub'));

    // Thay thế các biến trong stub
    $stub = str_replace(
      ['{{ namespace }}', '{{ class }}'],
      [$namespace, $className],
      $stub
    );

    // Tạo file mới
    $this->files->put($path, $stub);

    $this->info('Class export Excel ' . $className . ' đã được tạo thành công.');

    return 0;
  }
}