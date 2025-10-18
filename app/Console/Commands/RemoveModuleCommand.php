<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

class RemoveModuleCommand extends Command
{
  /**
   * Cho phép lệnh tự động đăng ký trong Laravel 12
   */
  protected static $autoRegisterCommands = true;

  protected $signature = 'remove:module {name : Tên module cần xóa}';
  protected $description = 'Xóa module đã tạo và các route liên quan';

  public function handle()
  {
    $name = $this->argument('name');
    $moduleName = Str::studly($name);
    $modulePath = app_path('Modules/' . $moduleName);
    $routeName = Str::kebab($moduleName);

    // Kiểm tra xem module có tồn tại không
    if (!File::exists($modulePath)) {
      $this->error("Module {$moduleName} không tồn tại!");
      return 1;
    }

    // Xác nhận từ người dùng
    if (!$this->confirm("Bạn có chắc chắn muốn xóa module {$moduleName}?", true)) {
      $this->info('Đã hủy xóa module.');
      return 0;
    }

    // Xóa thư mục module
    try {
      File::deleteDirectory($modulePath);
      $this->info("Đã xóa thư mục module: {$modulePath}");
    } catch (\Exception $e) {
      $this->error("Không thể xóa thư mục module: {$e->getMessage()}");
      return 1;
    }

    // Xóa route từ file routes/api.php
    $this->removeRoutes($routeName, $moduleName);

    // Xóa quyền từ file config/permission.php
    $this->removePermission($routeName);

    $this->info("Module {$moduleName} đã được xóa thành công!");
    return 0;
  }

  /**
   * Xóa route khỏi file routes/api.php
   */
  protected function removeRoutes($routeName, $moduleName)
  {
    $apiRoutePath = base_path('routes/api.php');

    if (!File::exists($apiRoutePath)) {
      $this->warn("File routes/api.php không tồn tại, không thể xóa route.");
      return;
    }

    // Đọc nội dung file
    $content = File::get($apiRoutePath);
    $lines = explode("\n", $content);
    $newLines = [];
    $skipLines = false;
    $commentFound = false;
    $skipCount = 0;
    $braceCount = 0;

    // Tìm comment hoặc prefix của module
    $commentPattern = "// " . $moduleName;
    $prefixPattern = "Route::prefix('" . $routeName . "')";
    $prefixPattern2 = "Route::prefix(\"" . $routeName . "\")";

    foreach ($lines as $line) {
      // Nếu tìm thấy comment module
      if (strpos($line, $commentPattern) !== false) {
        $commentFound = true;
        $skipLines = true;
        continue;
      }

      // Nếu tìm thấy prefix route
      if (!$commentFound && (strpos($line, $prefixPattern) !== false || strpos($line, $prefixPattern2) !== false)) {
        $skipLines = true;
        continue;
      }

      // Nếu đang trong trạng thái bỏ qua
      if ($skipLines) {
        // Đếm số dấu { và }
        $braceCount += substr_count($line, '{');
        $braceCount -= substr_count($line, '}');

        // Nếu tất cả dấu ngoặc đã đóng, ngừng bỏ qua
        if ($braceCount === 0 && strpos($line, '});') !== false) {
          $skipLines = false;
          $commentFound = false;
        }
        continue;
      }

      // Thêm dòng vào mảng kết quả
      $newLines[] = $line;
    }

    // Loại bỏ các dòng trống liên tiếp
    $newContent = implode("\n", $newLines);
    $newContent = preg_replace("/\n\s*\n\s*\n/", "\n\n", $newContent);

    // Ghi nội dung mới vào file
    File::put($apiRoutePath, $newContent);
    $this->info("Đã xóa route cho module '{$routeName}' khỏi routes/api.php");
  }

  /**
   * Kiểm tra xem các dấu ngoặc { và } có cân đối không
   */
  protected function checkBracesBalance($content)
  {
    $openBraces = substr_count($content, '{');
    $closeBraces = substr_count($content, '}');

    // Kiểm tra cân bằng ngoặc nhọn
    if ($openBraces !== $closeBraces) {
      return false;
    }

    // Kiểm tra cân bằng ngoặc tròn
    $openParentheses = substr_count($content, '(');
    $closeParentheses = substr_count($content, ')');

    if ($openParentheses !== $closeParentheses) {
      return false;
    }

    // Kiểm tra cân bằng ngoặc vuông
    $openBrackets = substr_count($content, '[');
    $closeBrackets = substr_count($content, ']');

    if ($openBrackets !== $closeBrackets) {
      return false;
    }

    // Kiểm tra xem các đoạn code chính có đầy đủ dấu đóng không
    if (
      strpos($content, 'Route::group([') !== false &&
      strpos($content, "]);") === false
    ) {
      return false;
    }

    // Kiểm tra xem nhóm middleware có đầy đủ dấu đóng không
    if (
      strpos($content, "'middleware' => ['jwt', 'permission']") !== false &&
      !preg_match("/['\"]\s*middleware['\"]\s*=>\s*\[\s*['\"]\s*jwt['\"]\s*,\s*['\"]\s*permission['\"]\s*\]\s*.*?\]\s*,\s*function.*?\)\s*\{.*?\}\s*\);/s", $content)
    ) {
      return false;
    }

    return true;
  }

  /**
   * Xóa quyền khỏi file config/permission.php
   */
  protected function removePermission($routeName)
  {
    $permissionPath = config_path('permission.php');

    if (!File::exists($permissionPath)) {
      $this->warn("File config/permission.php không tồn tại, không thể xóa quyền.");
      return;
    }

    // Đọc file dưới dạng mảng để dễ xử lý
    $permissions = include($permissionPath);
    $originalPermissions = $permissions;

    // Tìm và xóa quyền tương ứng với routeName
    $found = false;
    foreach ($permissions as $key => $permission) {
      if (isset($permission['name']) && $permission['name'] === $routeName) {
        unset($permissions[$key]);
        $found = true;
        break;
      }
    }

    if ($found) {
      // Chỉnh lại index của mảng
      $permissions = array_values($permissions);

      // Ghi lại file
      $content = "<?php\n\nreturn " . $this->varExport($permissions, true) . ";\n";
      File::put($permissionPath, $content);

      $this->info("Đã xóa quyền cho module '{$routeName}' khỏi config/permission.php");
    } else {
      $this->info("Không tìm thấy quyền cho module '{$routeName}' trong config/permission.php");
    }
  }

  /**
   * Xuất biến thành chuỗi PHP với định dạng đẹp
   */
  protected function varExport($var, $indent = false)
  {
    switch (gettype($var)) {
      case 'string':
        return '"' . addcslashes($var, "\\\$\"\r\n\t\v\f") . '"';
      case 'array':
        $indexed = array_keys($var) === range(0, count($var) - 1);
        $r = [];
        foreach ($var as $key => $value) {
          $r[] = ($indent ? '  ' : '') .
            ($indexed ? '' : $this->varExport($key) . ' => ') .
            $this->varExport($value, true);
        }
        return "[\n" . implode(",\n", $r) . "\n" . ($indent ? '' : '') . "]";
      case 'boolean':
        return $var ? 'true' : 'false';
      case 'NULL':
        return 'null';
      case 'integer':
      case 'double':
      default:
        return $var;
    }
  }
}