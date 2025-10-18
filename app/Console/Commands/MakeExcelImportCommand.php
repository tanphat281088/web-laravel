<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Str;
use Illuminate\Filesystem\Filesystem;

class MakeExcelImportCommand extends Command
{
  /**
   * The name and signature of the console command.
   *
   * @var string
   */
  protected $signature = 'make:excel-import {name : Tên của class import}';

  /**
   * The console command description.
   *
   * @var string
   */
  protected $description = 'Tạo class import Excel mới từ stub';

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

    // Kiểm tra nếu tên không có hậu tố "Import", thêm vào
    if (!Str::endsWith($name, 'Import')) {
      $name = $name . 'Import';
    }

    $className = Str::studly($name);
    $namespace = 'App\\Imports';

    // Đường dẫn đến file sẽ tạo
    $path = app_path('Imports/' . $className . '.php');

    // Kiểm tra xem file đã tồn tại chưa
    if ($this->files->exists($path)) {
      $this->error('Class ' . $className . ' đã tồn tại!');
      return 1;
    }

    // Đảm bảo thư mục tồn tại
    $this->files->ensureDirectoryExists(dirname($path));

    // Đọc nội dung từ file stub
    $stub = $this->files->get(base_path('stubs/excel/import.stub'));

    // Thay thế các biến trong stub
    $stub = str_replace(
      ['{{ namespace }}', '{{ class }}'],
      [$namespace, $className],
      $stub
    );

    // Tạo file mới
    $this->files->put($path, $stub);

    $this->info('Class import Excel ' . $className . ' đã được tạo thành công.');

    return 0;
  }
}