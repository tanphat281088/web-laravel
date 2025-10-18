<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

class MakeModuleCommand extends Command
{
  protected $signature = 'make:module {name : Tên module} {--model= : Tên model sẽ sử dụng (mặc định tự tạo từ tên module)} {--route= : Tên route (mặc định sẽ tạo từ tên module)} {--api : Tạo API route}';
  protected $description = 'Tạo module bao gồm controller, service, validates và đăng ký route tự động';

  public function handle()
  {
    $name = $this->argument('name');
    $moduleName = Str::studly($name);
    $modulePath = app_path('Modules/' . $moduleName);

    // Xác định tên model từ tùy chọn hoặc từ tên module
    $modelName = $this->option('model') ?: Str::singular($moduleName);
    $modelName = Str::studly($modelName);

    // Xác định tên route từ tùy chọn hoặc từ tên module
    $routeName = $this->option('route') ?: Str::kebab($moduleName);

    // Tạo thư mục module
    if (!File::exists($modulePath)) {
      File::makeDirectory($modulePath, 0755, true);
    }

    // Tạo thư mục Validates trong module
    if (!File::exists($modulePath . '/Validates')) {
      File::makeDirectory($modulePath . '/Validates', 0755, true);
    }

    // Tạo Controller
    $this->createController($moduleName, $modelName, $modulePath);

    // Tạo Service
    $this->createService($moduleName, $modelName, $modulePath);

    // Tạo Request Validate files
    $this->createValidates($moduleName, $modulePath);

    // Đăng ký route
    if ($this->option('api')) {
      $this->registerApiRoutes($moduleName, $routeName);
    }

    // Thêm quyền vào file config/permission.php
    $this->addPermission($routeName);

    $this->info("Module {$moduleName} đã được tạo thành công!");
    $this->info("Controller: App\\Modules\\{$moduleName}\\{$moduleName}Controller");
    $this->info("Service: App\\Modules\\{$moduleName}\\{$moduleName}Service");
    $this->info("Validates: App\\Modules\\{$moduleName}\\Validates\\Create{$moduleName}Request, Update{$moduleName}Request");
    $this->info("Sử dụng model: {$modelName}");

    if ($this->option('api')) {
      $this->info("Route API đã được đăng ký: api/{$routeName}");
      $this->info("Quyền đã được thêm vào config/permission.php: {$routeName}");
    }
  }

  protected function createController($moduleName, $modelName, $modulePath)
  {
    $controllerContent = $this->getControllerStub($moduleName, $modelName);
    File::put("$modulePath/{$moduleName}Controller.php", $controllerContent);
  }

  protected function createService($moduleName, $modelName, $modulePath)
  {
    $serviceContent = $this->getServiceStub($moduleName, $modelName);
    File::put("$modulePath/{$moduleName}Service.php", $serviceContent);
  }

  protected function createValidates($moduleName, $modulePath)
  {
    // Tạo file Create Request
    $createRequestContent = $this->getCreateRequestStub($moduleName);
    File::put("$modulePath/Validates/Create{$moduleName}Request.php", $createRequestContent);

    // Tạo file Update Request
    $updateRequestContent = $this->getUpdateRequestStub($moduleName);
    File::put("$modulePath/Validates/Update{$moduleName}Request.php", $updateRequestContent);
  }

  protected function registerApiRoutes($moduleName, $routeName)
  {
    $apiRoutePath = base_path('routes/api.php');
    $routeContent = File::get($apiRoutePath);

    // Tạo chuỗi route để thêm vào
    $newRouteContent = $this->generateRouteContent($moduleName, $routeName);

    // Kiểm tra xem route đã tồn tại chưa
    if (strpos($routeContent, "Route::prefix('{$routeName}')") !== false) {
      $this->warn("Route cho '{$routeName}' đã tồn tại trong file routes/api.php");
      return;
    }

    // Tìm vị trí kết thúc của middleware group với ['jwt', 'permission']
    $pattern = '/Route::group\(\[\s*\n*\s*\'middleware\'\s*=>\s*\[\s*\'jwt\'\s*,\s*\'permission\'\s*\]\s*,*\s*\n*\s*\]\s*,\s*function\s*\(\s*\$router\s*\)\s*\{/s';
    if (preg_match($pattern, $routeContent, $matches, PREG_OFFSET_CAPTURE)) {
      $startPos = $matches[0][1];

      // Tìm dấu đóng tương ứng
      $openBraces = 1;
      $closePos = $startPos + strlen($matches[0][0]);

      while ($openBraces > 0 && $closePos < strlen($routeContent)) {
        if ($routeContent[$closePos] === '{') {
          $openBraces++;
        } elseif ($routeContent[$closePos] === '}') {
          $openBraces--;
          if ($openBraces === 0) {
            // Đây là vị trí đóng của nhóm middleware
            $updatedContent = substr($routeContent, 0, $closePos) .
              "\n  " . $newRouteContent . "\n" .
              substr($routeContent, $closePos);

            // Ghi nội dung cập nhật vào file
            File::put($apiRoutePath, $updatedContent);
            return;
          }
        }
        $closePos++;
      }
    }

    // Nếu không tìm thấy nhóm middleware hoặc có lỗi, thêm vào cuối file
    $this->warn("Không thể tìm thấy vị trí đúng trong nhóm middleware ['jwt', 'permission']. Thêm route vào cuối file.");
    $updatedContent = $routeContent . "\n" . $newRouteContent . "\n";
    File::put($apiRoutePath, $updatedContent);
  }

  protected function generateRouteContent($moduleName, $routeName)
  {
    $controllerClass = "\\App\\Modules\\{$moduleName}\\{$moduleName}Controller";
    $singularName = Str::singular($moduleName);

    return "// {$moduleName}\n  Route::prefix('{$routeName}')->group(function () {\n" .
      "    Route::get('/', [{$controllerClass}::class, 'index']);\n" .
      "    Route::get('/options', [{$controllerClass}::class, 'getOptions']);\n" .
      "    Route::get('/download-template-excel', [{$controllerClass}::class, 'downloadTemplateExcel']);\n" .
      "    Route::post('/', [{$controllerClass}::class, 'store']);\n" .
      "    Route::get('/{id}', [{$controllerClass}::class, 'show']);\n" .
      "    Route::put('/{id}', [{$controllerClass}::class, 'update']);\n" .
      "    Route::delete('/{id}', [{$controllerClass}::class, 'destroy']);\n" .
      "    Route::post('/import-excel', [{$controllerClass}::class, 'importExcel']);\n" .
      "  });";
  }

  protected function getControllerStub($moduleName, $modelName)
  {
    $singularName = Str::singular($moduleName);
    $pluralName = Str::plural($moduleName);
    $variableName = Str::camel($singularName);

    return "<?php

namespace App\Modules\\{$moduleName};

use App\Http\Controllers\Controller;
use App\Modules\\{$moduleName}\\Validates\\Create{$moduleName}Request;
use App\Modules\\{$moduleName}\\Validates\\Update{$moduleName}Request;
use App\Class\CustomResponse;
use App\Class\Helper;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Facades\Excel;
use App\Imports\\{$moduleName}Import;
use Illuminate\Support\Str;

class {$moduleName}Controller extends Controller
{
    protected \${$variableName}Service;
    
    public function __construct({$moduleName}Service \${$variableName}Service)
    {
        \$this->{$variableName}Service = \${$variableName}Service;
    }
    
    /**
     * Lấy danh sách {$pluralName}
     */
    public function index(Request \$request)
    {
        \$params = \$request->all();

        // Xử lý và validate parameters
        \$params = Helper::validateFilterParams(\$params);

        \$result = \$this->{$variableName}Service->getAll(\$params);

        if (\$result instanceof \Illuminate\Http\JsonResponse) {
          return \$result;
        }

        return CustomResponse::success([
          'collection' => \$result['data'],
          'total' => \$result['total'],
          'pagination' => \$result['pagination'] ?? null
        ]);
    }
    
    /**
     * Tạo mới {$singularName}
     */
    public function store(Create{$moduleName}Request \$request)
    {
        \$result = \$this->{$variableName}Service->create(\$request->validated());

        if (\$result instanceof \Illuminate\Http\JsonResponse) {
          return \$result;
        }

        return CustomResponse::success(\$result, 'Tạo mới thành công');
    }
    
    /**
     * Lấy thông tin {$singularName}
     */
    public function show(\$id)
    {
        \$result = \$this->{$variableName}Service->getById(\$id);

        if (\$result instanceof \Illuminate\Http\JsonResponse) {
          return \$result;
        }

        return CustomResponse::success(\$result);
    }
    
    /**
     * Cập nhật {$singularName}
     */
    public function update(Update{$moduleName}Request \$request, \$id)
    {
        \$result = \$this->{$variableName}Service->update(\$id, \$request->validated());

        if (\$result instanceof \Illuminate\Http\JsonResponse) {
          return \$result;
        }

        return CustomResponse::success(\$result, 'Cập nhật thành công');
    }
    
    /**
     * Xóa {$singularName}
     */
    public function destroy(\$id)
    {
        \$result = \$this->{$variableName}Service->delete(\$id);

        if (\$result instanceof \Illuminate\Http\JsonResponse) {
          return \$result;
        }

        return CustomResponse::success([], 'Xóa thành công');
    }

    /**
     * Lấy danh sách {$singularName} dạng option
     */
    public function getOptions(Request \$request)
    {
      \$params = \$request->all();

      \$params = Helper::validateFilterParams(\$params);

      \$result = \$this->{$variableName}Service->getOptions(\$params);

      if (\$result instanceof \Illuminate\Http\JsonResponse) {
        return \$result;
      }

      return CustomResponse::success(\$result);
    }

    public function downloadTemplateExcel()
    {
      \$path = public_path('mau-excel/{$moduleName}.xlsx');

      if (!file_exists(\$path)) {
        return CustomResponse::error('File không tồn tại');
      }

      return response()->download(\$path);
    }

    public function importExcel(Request \$request)
    {
      \$request->validate([
        'file' => 'required|file|mimes:xlsx,xls,csv',
      ]);

    try {
      \$data = \$request->file('file');
      \$filename = Str::random(10) . '.' . \$data->getClientOriginalExtension();
      \$path = \$data->move(public_path('excel'), \$filename);

      \$import = new {$moduleName}Import();
      Excel::import(\$import, \$path);

      \$thanhCong = \$import->getThanhCong();
      \$thatBai = \$import->getThatBai();

      if (\$thatBai > 0) {
        return CustomResponse::error('Import không thành công. Có ' . \$thatBai . ' bản ghi lỗi và ' . \$thanhCong . ' bản ghi thành công');
      }

      return CustomResponse::success([
        'success' => \$thanhCong,
        'fail' => \$thatBai
      ], 'Import thành công ' . \$thanhCong . ' bản ghi');
    } catch (\Exception \$e) {
      return CustomResponse::error('Lỗi import: ' . \$e->getMessage(), 500);
    }
  }
}
";
  }

  protected function getServiceStub($moduleName, $modelName)
  {
    $singularName = Str::singular($moduleName);
    $variableName = Str::camel($singularName);
    $lowerModelName = Str::lower($modelName);
    $snakeName = Str::snake($moduleName);
    $snakeNamePlural = Str::plural($snakeName);

    return "<?php

namespace App\Modules\\{$moduleName};

use App\\Models\\{$modelName};
use Illuminate\Support\Facades\DB;
use Exception;
use App\Class\CustomResponse;
use App\Class\FilterWithPagination;

class {$moduleName}Service
{
    /**
     * Lấy tất cả dữ liệu
     */
      public function getAll(array \$params = [])
      {
        try {
          // Tạo query cơ bản
          \$query = {$modelName}::query()->with('images');

          // Sử dụng FilterWithPagination để xử lý filter và pagination
          \$result = FilterWithPagination::findWithPagination(
            \$query,
            \$params,
            ['{$snakeNamePlural}.*'] // Columns cần select
          );

          return [
            'data' => \$result['collection'],
            'total' => \$result['total'],
            'pagination' => [
              'current_page' => \$result['current_page'],
              'last_page' => \$result['last_page'],
              'from' => \$result['from'],
              'to' => \$result['to'],
              'total_current' => \$result['total_current']
            ]
          ];
        } catch (Exception \$e) {
            throw new Exception('Lỗi khi lấy danh sách: ' . \$e->getMessage());
        }
      }
    
    /**
     * Lấy dữ liệu theo ID
     */
    public function getById(\$id)
    {
        \$data = {$modelName}::with('images')->find(\$id);
        if (!\$data) {
          return CustomResponse::error('Dữ liệu không tồn tại');
        }
        return \$data;
    }
    
    /**
     * Tạo mới dữ liệu
     */
    public function create(array \$data)
    {
      try {
        \$result = {$modelName}::create(\$data);

        // TODO: Thêm ảnh vào bảng images (nếu có)
        // \$result->images()->create([
        //   'path' => \$data['image'],
        // ]);

        return \$result;
      } catch (Exception \$e) {
        return CustomResponse::error(\$e->getMessage());
      }
    }
    
    /**
     * Cập nhật dữ liệu
     */
    public function update(\$id, array \$data)
    {
      try {
        \$model = {$modelName}::findOrFail(\$id);
        \$model->update(\$data);

        // TODO: Cập nhật ảnh vào bảng images (nếu có)
        // if (\$data['image']) {
        //   \$model->images()->get()->each(function (\$image) use (\$data) {
        //     \$image->update([
        //       'path' => \$data['image'],
        //     ]);
        //   });
        // }

        
        return \$model->fresh();
      } catch (Exception \$e) {
        return CustomResponse::error(\$e->getMessage());
      }
    }
    
    
    /**
     * Xóa dữ liệu
     */
    public function delete(\$id)
    {
      try {
        \$model = {$modelName}::findOrFail(\$id);
        
        // TODO: Xóa ảnh vào bảng images (nếu có)
        // \$model->images()->get()->each(function (\$image) {
        //   \$image->delete();
        // });
        
        return \$model->delete();
      } catch (Exception \$e) {
        return CustomResponse::error(\$e->getMessage());
      }
    }

    /**
     * Lấy danh sách {$singularName} dạng option
     */
    public function getOptions(array \$params = [])
    {
      \$query = {$modelName}::query();

      \$result = FilterWithPagination::findWithPagination(
        \$query,
        \$params,
        ['{$snakeNamePlural}.id as value', '{$snakeNamePlural}.ten_{$snakeName} as label']
      );

      return \$result['collection'];
    }
}
";
  }

  protected function getCreateRequestStub($moduleName)
  {
    $singularName = Str::singular($moduleName);

    return "<?php

namespace App\Modules\\{$moduleName}\\Validates;

use Illuminate\Foundation\Http\FormRequest;

class Create{$moduleName}Request extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \\Illuminate\\Contracts\\Validation\\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            // Thêm các quy tắc validation cho {$singularName} ở đây
            'name' => 'required|string|max:255',
            // 'description' => 'nullable|string',
            // 'active' => 'boolean',
        ];
    }

    /**
     * Get the error messages for the defined validation rules.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'name.required' => 'Tên {$singularName} là bắt buộc',
            'name.max' => 'Tên {$singularName} không được vượt quá 255 ký tự',
        ];
    }
}
";
  }

  protected function getUpdateRequestStub($moduleName)
  {
    $singularName = Str::singular($moduleName);

    return "<?php

namespace App\Modules\\{$moduleName}\\Validates;

use Illuminate\Foundation\Http\FormRequest;

class Update{$moduleName}Request extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \\Illuminate\\Contracts\\Validation\\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            // Thêm các quy tắc validation cho cập nhật {$singularName} ở đây
            'name' => 'sometimes|required|string|max:255',
            // 'description' => 'nullable|string',
            // 'active' => 'boolean',
        ];
    }

    /**
     * Get the error messages for the defined validation rules.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'name.required' => 'Tên {$singularName} là bắt buộc',
            'name.max' => 'Tên {$singularName} không được vượt quá 255 ký tự',
        ];
    }
}
";
  }

  /**
   * Thêm quyền vào file config/permission.php
   */
  protected function addPermission($routeName)
  {
    $permissionPath = config_path('permission.php');
    $content = File::get($permissionPath);

    // Tạo mẫu quyền mới
    $newPermission = "  [
    \"name\" => \"{$routeName}\",
    \"actions\" => [
      \"index\" => true,
      \"create\" => true,
      \"show\" => true,
      \"edit\" => true,
      \"delete\" => true,
      \"export\" => true,
      \"showMenu\" => true
    ]
  ],";

    // Kiểm tra xem quyền đã tồn tại hay chưa
    if (strpos($content, "\"name\" => \"{$routeName}\"") !== false) {
      $this->warn("Quyền cho '{$routeName}' đã tồn tại trong file config/permission.php");
      return;
    }

    // Tìm vị trí để thêm quyền mới
    $pattern = '/return\s*\[\s*/';
    if (preg_match($pattern, $content, $matches, PREG_OFFSET_CAPTURE)) {
      $position = $matches[0][1] + strlen($matches[0][0]);

      // Thêm quyền mới vào sau mẫu "return ["
      $updatedContent = substr($content, 0, $position) .
        $newPermission . "\n" .
        substr($content, $position);

      File::put($permissionPath, $updatedContent);
      return;
    }

    // Nếu mẫu không khớp, tìm vị trí trước dấu đóng ngoặc cuối cùng
    $lastBracePos = strrpos($content, '];');
    if ($lastBracePos !== false) {
      // Kiểm tra xem có dấu phẩy trước dấu đóng ngoặc không
      $beforeBrace = trim(substr($content, 0, $lastBracePos));
      $updatedContent = substr($content, 0, $lastBracePos);

      // Nếu ký tự cuối cùng không phải là dấu phẩy, thêm dấu phẩy
      if (substr($beforeBrace, -1) !== ',') {
        $updatedContent .= ",\n";
      } else {
        $updatedContent .= "\n";
      }

      $updatedContent .= $newPermission . "\n" . substr($content, $lastBracePos);

      File::put($permissionPath, $updatedContent);
      return;
    }

    $this->error("Không thể tìm thấy vị trí phù hợp để thêm quyền vào file config/permission.php");
  }
}