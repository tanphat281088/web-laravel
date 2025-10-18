# Hướng Dẫn Chi Tiết Class FilterWithPagination

## 🎯 Mục đích của Class

Class `FilterWithPagination` được tạo ra để giải quyết các bài toán phổ biến khi làm việc với dữ liệu:

-   **Lọc dữ liệu** (filter) theo nhiều điều kiện khác nhau
-   **Phân trang** (pagination) để hiển thị dữ liệu theo từng trang
-   **Sắp xếp** (sorting) dữ liệu theo cột mong muốn
-   **Nối bảng** (joins) để lấy dữ liệu từ nhiều bảng

## 📋 Các Loại Filter Được Hỗ Trợ

```php
const OPERATORS = [
    'EQUAL' => 'equal',                          // Bằng (=)
    'NOT_EQUAL' => 'not_equal',                  // Khác (<>)
    'CONTAIN' => 'contain',                      // Chứa (LIKE %value%)
    'LESS_THAN' => 'less_than',                  // Nhỏ hơn (<)
    'EQUAL_TO' => 'equal_to',                    // Bằng với xử lý đặc biệt cho ngày
    'LESS_THAN_OR_EQUAL_TO' => 'less_than_or_equal_to',      // Nhỏ hơn hoặc bằng (<=)
    'GREATER_THAN' => 'greater_than',            // Lớn hơn (>)
    'GREATER_THAN_OR_EQUAL_TO' => 'greater_than_or_equal_to', // Lớn hơn hoặc bằng (>=)
    'BETWEEN' => 'between',                      // Trong khoảng (BETWEEN)
    'INCLUDES' => 'includes',                    // Nằm trong danh sách (IN)
    'NOT_INCLUDES' => 'not_includes',            // Không nằm trong danh sách (NOT IN)
];
```

## 🚀 Luồng Hoạt Động Chính

### 1. Hàm Entry Point: `findWithPagination()`

Đây là hàm chính mà bạn sẽ gọi từ bên ngoài:

```php
public static function findWithPagination(
    $query,           // Query builder của Laravel/Eloquent
    array $filters,   // Mảng chứa các filter từ request
    array $columns,   // Các cột cần select (mặc định là ['*'])
    array $joins      // Cấu hình joins (tùy chọn)
): array
```

**Luồng xử lý:**

```
Input Request → findWithPagination() → [4 bước xử lý] → Output với pagination
```

### 2. Bước 1: Chuẩn Bị Query và Joins

#### 2.1 Lấy tên bảng

```php
$tableName = self::getTableName($query);
```

-   Nếu là Eloquent Model: lấy từ `$query->getModel()->getTable()`
-   Nếu là Query Builder: lấy từ `$query->from`

#### 2.2 Xử lý Joins (nếu có)

```php
if (!empty($joins)) {
    self::processJoins($query, $joins);
}
```

**Cấu trúc join:**

```php
$joins = [
    [
        'table' => 'users',                    // Tên bảng
        'alias' => 'u',                       // Alias (tùy chọn)
        'condition' => 'posts.user_id = u.id', // Điều kiện join
        'type' => 'left'                      // Loại join: left, right, inner, cross
    ]
];
```

**Các loại join được hỗ trợ:**

-   `left`: LEFT JOIN
-   `right`: RIGHT JOIN
-   `inner`: INNER JOIN
-   `cross`: CROSS JOIN

### 3. Bước 2: Xử Lý Filters

#### 3.1 Cấu trúc filter đầu vào

```php
$filters = [
    'f' => [                                   // Mảng các filter
        [
            'field' => 'name',                 // Tên cột
            'operator' => 'contain',           // Toán tử
            'value' => 'John'                  // Giá trị filter
        ],
        [
            'field' => 'age',
            'operator' => 'greater_than',
            'value' => 18
        ]
    ]
];
```

#### 3.2 Quy trình xử lý filter

```
Lặp qua từng filter → Validate filter → Thêm table prefix → Áp dụng filter vào query
```

**Chi tiết từng bước:**

1. **Validate filter:**

    ```php
    private static function isValidFilter(array $filter): bool
    {
        return isset($filter['field']) && isset($filter['operator']) && isset($filter['value']);
    }
    ```

2. **Thêm table prefix:**

    ```php
    $fieldName = self::addTablePrefix($filter['field'], $tableName);
    // Ví dụ: 'name' → 'users.name'
    ```

3. **Chuẩn bị giá trị:**

    ```php
    $value = self::prepareFilterValue($filter, $operator);
    ```

4. **Áp dụng filter:**
    ```php
    self::applyFilter($query, $fieldName, $operator, $value);
    ```

#### 3.3 Chi tiết các loại filter

**EQUAL (Bằng):**

```php
$query->where($field, '=', $value);
// SQL: WHERE users.name = 'John'
```

**CONTAIN (Chứa):**

```php
$query->where($field, 'like', '%' . $value . '%');
// SQL: WHERE users.name LIKE '%John%'
```

**GREATER_THAN (Lớn hơn) - Có xử lý đặc biệt cho ngày:**

```php
if (self::isDateString($value)) {
    // Với ngày: lấy từ ngày hôm sau
    $nextDay = Carbon::parse($value)->addDay()->startOfDay()->format('Y-m-d H:i:s');
    $query->where($field, '>', $nextDay);
} else {
    $query->where($field, '>', $value);
}
```

**EQUAL_TO (Bằng với xử lý ngày):**

```php
if (self::isDateString($value)) {
    // Filter theo cả ngày (00:00:00 - 23:59:59)
    $startDate = Carbon::parse($value)->startOfDay();
    $endDate = Carbon::parse($value)->endOfDay();
    $query->whereBetween($field, [$startDate, $endDate]);
} else {
    $query->where($field, '=', $value);
}
```

**BETWEEN (Trong khoảng):**

```php
$values = self::parseArrayValue($value); // Parse JSON hoặc array
$query->whereBetween($field, $values);
// SQL: WHERE users.age BETWEEN 18 AND 65
```

**INCLUDES (Trong danh sách):**

```php
$values = self::parseArrayValue($value);
$query->whereIn($field, $values);
// SQL: WHERE users.status IN ('active', 'pending')
```

### 4. Bước 3: Xử Lý Sorting

```php
$filters = [
    'sort_column' => 'created_at',     // Cột sắp xếp
    'sort_direction' => 'desc'         // Hướng sắp xếp: asc/desc
];
```

**Quy trình:**

1. Kiểm tra có `sort_column` và `sort_direction` không
2. Thêm table prefix cho cột
3. Validate direction (chỉ chấp nhận 'asc' hoặc 'desc')
4. Áp dụng ORDER BY

```php
$query->orderBy($sortColumn, $sortDirection);
// SQL: ORDER BY users.created_at DESC
```

### 5. Bước 4: Xử Lý Pagination

#### 5.1 Cấu hình pagination

```php
$filters = [
    'page' => 1,      // Trang hiện tại (mặc định: 1)
    'limit' => 10     // Số bản ghi mỗi trang (mặc định: 10, -1 = lấy tất cả)
];
```

#### 5.2 Quy trình xử lý

```
Đếm tổng số bản ghi → Tính toán offset → Áp dụng LIMIT & OFFSET → Lấy dữ liệu
```

**Chi tiết:**

1. **Đếm tổng số bản ghi:**

    ```php
    $total = (clone $query)->count();
    ```

2. **Trường hợp lấy tất cả (limit = -1):**

    ```php
    if ($limit < 0) {
        $collection = $query->select($columns)->get();
        return [
            'collection' => $collection,
            'total' => $total,
            'total_current' => $collection->count(),
        ];
    }
    ```

3. **Tính toán pagination:**

    ```php
    $offset = ($page - 1) * $limit;
    $query->limit($limit)->offset($offset);
    ```

4. **Lấy dữ liệu và trả về:**

    ```php
    $collection = $query->select($columns)->get();

    return [
        'collection' => $collection,           // Dữ liệu
        'total' => $total,                    // Tổng số bản ghi
        'total_current' => $collection->count(), // Số bản ghi trang hiện tại
        'from' => ($page - 1) * $limit + 1,  // Bản ghi đầu
        'to' => ($page - 1) * $limit + $collection->count(), // Bản ghi cuối
        'current_page' => $page,              // Trang hiện tại
        'last_page' => ceil($total / $limit), // Trang cuối
        'next_page' => $page < ceil($total / $limit) ? $page + 1 : $page, // Trang tiếp
    ];
    ```

## 💡 Ví Dụ Sử Dụng Thực Tế

### Ví dụ 1: Filter đơn giản

```php
use App\Models\User;
use App\Class\FilterWithPagination;

// Tìm user có tên chứa "John" và tuổi > 18
$query = User::query();
$filters = [
    'f' => [
        ['field' => 'name', 'operator' => 'contain', 'value' => 'John'],
        ['field' => 'age', 'operator' => 'greater_than', 'value' => 18]
    ],
    'page' => 1,
    'limit' => 10,
    'sort_column' => 'created_at',
    'sort_direction' => 'desc'
];

$result = FilterWithPagination::findWithPagination($query, $filters);
```

### Ví dụ 2: Sử dụng với Joins

```php
$query = DB::table('posts');
$joins = [
    [
        'table' => 'users',
        'alias' => 'u',
        'condition' => 'posts.user_id = u.id',
        'type' => 'left'
    ],
    [
        'table' => 'categories',
        'alias' => 'c',
        'condition' => 'posts.category_id = c.id',
        'type' => 'left'
    ]
];

$filters = [
    'f' => [
        ['field' => 'u.name', 'operator' => 'contain', 'value' => 'John'],
        ['field' => 'c.name', 'operator' => 'equal', 'value' => 'Technology']
    ]
];

$columns = ['posts.*', 'u.name as user_name', 'c.name as category_name'];

$result = FilterWithPagination::findWithPagination($query, $filters, $columns, $joins);
```

### Ví dụ 3: Filter theo ngày

```php
$filters = [
    'f' => [
        // Bài viết được tạo vào ngày 2024-01-15
        ['field' => 'created_at', 'operator' => 'equal_to', 'value' => '2024-01-15'],

        // Bài viết được tạo sau ngày 2024-01-01
        ['field' => 'created_at', 'operator' => 'greater_than', 'value' => '2024-01-01'],

        // Bài viết được tạo trong khoảng thời gian
        ['field' => 'created_at', 'operator' => 'between', 'value' => ['2024-01-01', '2024-01-31']]
    ]
];
```

## 🔧 Các Hàm Helper Quan Trọng

### 1. validateFilters()

Dùng để validate và clean dữ liệu filter trước khi xử lý:

```php
$cleanFilters = FilterWithPagination::validateFilters($requestFilters);
```

### 2. findWithJoinsAndPagination()

Alias của `findWithPagination()` nhưng đặt joins làm tham số thứ 2:

```php
$result = FilterWithPagination::findWithJoinsAndPagination(
    $query,
    $joins,
    $filters,
    $columns
);
```

## ⚠️ Lưu Ý Quan Trọng

1. **Bảo mật:** Class này không có built-in validation cho SQL injection. Đảm bảo validate input từ phía client.

2. **Performance:** Với dataset lớn, nên:

    - Tạo index cho các cột thường xuyên filter
    - Sử dụng joins hợp lý
    - Hạn chế số lượng filter phức tạp

3. **Xử lý lỗi:** Tất cả lỗi đều được log, nhưng không throw exception để tránh crash ứng dụng.

4. **Định dạng ngày:** Class tự động detect format YYYY-MM-DD cho các filter ngày.

## 🎯 Kết Luận

Class `FilterWithPagination` là một công cụ mạnh mẽ giúp bạn:

-   Xử lý filter phức tạp một cách dễ dàng
-   Implement pagination chuẩn
-   Hỗ trợ joins giữa nhiều bảng
-   Xử lý sorting linh hoạt

Với cấu trúc modular và các hàm helper tiện ích, class này giúp code của bạn clean hơn và dễ maintain hơn.
