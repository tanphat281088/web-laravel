<?php

namespace App\Console\Commands;

use App\Models\Image;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;

class ClearImageNotUse extends Command
{
  protected $signature = 'images:clear';
  protected $description = 'Xóa các file ảnh không còn trong database';

  public function handle(): int
  {
    Log::info('Đang chạy task dọn dẹp ảnh...');
    $this->info('Đang chạy task dọn dẹp ảnh...');
    $rootPath = public_path('images');
    $count = 0;

    if (!File::isDirectory($rootPath)) {
      $this->error("Thư mục {$rootPath} không tồn tại!");
      return Command::FAILURE;
    }

    $directories = File::directories($rootPath);

    foreach ($directories as $directory) {
      $this->info("Đang kiểm tra thư mục: " . basename($directory));

      $files = File::glob("{$directory}/*.{jpg,jpeg,png,gif}", GLOB_BRACE);

      foreach ($files as $file) {
        $relativePath = 'images/' . basename($directory) . '/' . basename($file);
        $fullUrlPath = config('app.url') . '/' . $relativePath;

        $exists = Image::where('path', 'like', "%{$relativePath}")
          ->orWhere('path', 'like', "%{$fullUrlPath}")
          ->exists();

        if (!$exists) {
          try {
            File::delete($file);
            $count++;
            $this->line("Đã xóa: {$relativePath}");
          } catch (\Exception $e) {
            $this->error("Lỗi khi xóa {$file}: " . $e->getMessage());
          }
        }
      }
    }

    $this->info("Hoàn thành! Đã xóa {$count} file ảnh không sử dụng.");
    Log::info("Hoàn thành! Đã xóa {$count} file ảnh không sử dụng.");
    return Command::SUCCESS;
  }
}