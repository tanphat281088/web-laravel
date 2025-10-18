<?php

namespace App\Class;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class FilterWithPagination
{
  // Các loại filter được hỗ trợ
  const OPERATORS = [
    'EQUAL' => 'equal',                          // Bằng
    'NOT_EQUAL' => 'not_equal',                  // Khác
    'CONTAIN' => 'contain',                      // Chứa
    'LESS_THAN' => 'less_than',                  // Nhỏ hơn
    'EQUAL_TO' => 'equal_to',                    // Bằng (đặc biệt cho ngày)
    'LESS_THAN_OR_EQUAL_TO' => 'less_than_or_equal_to',      // Nhỏ hơn hoặc bằng
    'GREATER_THAN' => 'greater_than',            // Lớn hơn
    'GREATER_THAN_OR_EQUAL_TO' => 'greater_than_or_equal_to', // Lớn hơn hoặc bằng
    'BETWEEN' => 'between',                      // Trong khoảng
    'INCLUDES' => 'includes',                    // Nằm trong danh sách
    'NOT_INCLUDES' => 'not_includes',            // Không nằm trong danh sách
  ];

  /**
   * Hàm chính để xử lý filter và pagination
   * 
   * @param Builder|QueryBuilder $query - Query cần xử lý
   * @param array $filters - Các filter từ request
   * @param array $columns - Cột cần select
   * @param array $joins - Cấu hình joins (optional)
   * @return array - Kết quả có data và thông tin pagination
   */
  public static function findWithPagination(
    $query,
    array $filters,
    array $columns = ['*'],
    array $joins = []
  ): array {
    // Log::debug('FilterWithPagination: Bắt đầu xử lý', ['filters' => $filters]);

    // Bước 1: Chuẩn bị query
    $tableName = self::getTableName($query);

    // Bước 1.5: Áp dụng joins nếu có
    if (!empty($joins)) {
      self::processJoins($query, $joins);
    }

    // Bước 2: Áp dụng các filter
    self::processFilters($query, $filters, $tableName);

    // Bước 3: Áp dụng sorting
    self::processSorting($query, $filters, $tableName);

    // Bước 4: Tính toán pagination và lấy data
    return self::processPagination($query, $filters, $columns);
  }

  /**
   * Xử lý joins
   */
  private static function processJoins($query, array $joins): void
  {
    foreach ($joins as $join) {
      if (!isset($join['table'])) {
        continue;
      }

      $table = $join['table'];
      $alias = $join['alias'] ?? $table;
      $condition = $join['condition'] ?? null;
      $type = strtolower($join['type'] ?? 'left');

      self::applyJoin($query, $table, $alias, $condition, $type);
    }
  }

  /**
   * Áp dụng join vào query
   */
  private static function applyJoin($query, string $table, string $alias, $condition, string $type): void
  {
    $joinTable = $table . ($table !== $alias ? " as $alias" : "");

    switch ($type) {
      case 'left':
        $query->leftJoin($joinTable, function ($join) use ($condition) {
          self::buildJoinCondition($join, $condition);
        });
        break;

      case 'right':
        $query->rightJoin($joinTable, function ($join) use ($condition) {
          self::buildJoinCondition($join, $condition);
        });
        break;

      case 'inner':
        $query->join($joinTable, function ($join) use ($condition) {
          self::buildJoinCondition($join, $condition);
        });
        break;

      case 'cross':
        $query->crossJoin($joinTable);
        break;

      default:
        Log::warning('Loại join không hỗ trợ: ' . $type);
        break;
    }
  }

  /**
   * Xây dựng điều kiện join
   */
  private static function buildJoinCondition($join, $condition): void
  {
    if (is_string($condition)) {
      // Xử lý điều kiện dạng string: "table1.id = table2.foreign_id"
      $parts = explode(' = ', $condition);
      if (count($parts) === 2) {
        $join->on(trim($parts[0]), '=', trim($parts[1]));
      }
    } elseif (is_array($condition)) {
      // Xử lý điều kiện dạng mảng
      foreach ($condition as $cond) {
        if (isset($cond['first']) && isset($cond['second'])) {
          $operator = $cond['operator'] ?? '=';
          $join->on($cond['first'], $operator, $cond['second']);
        }
      }
    }
  }

  /**
   * Xử lý tất cả các filter
   */
  private static function processFilters($query, array $filters, ?string $tableName): void
  {
    if (!isset($filters['f']) || !is_array($filters['f'])) {
      return;
    }

    foreach ($filters['f'] as $filter) {
      if (!self::isValidFilter($filter)) {
        continue;
      }

      $fieldName = self::addTablePrefix($filter['field'], $tableName);
      $operator = strtolower($filter['operator']);
      $value = self::prepareFilterValue($filter, $operator);

      try {
        self::applyFilter($query, $fieldName, $operator, $value);
      } catch (\Exception $e) {
        Log::warning('Lỗi filter', [
          'field' => $fieldName,
          'operator' => $operator,
          'value' => $value,
          'error' => $e->getMessage()
        ]);
      }
    }
  }

  /**
   * Xử lý sorting
   */
  private static function processSorting($query, array $filters, ?string $tableName): void
  {
    if (!isset($filters['sort_column']) || !isset($filters['sort_direction'])) {
      return;
    }

    $sortColumn = self::addTablePrefix($filters['sort_column'], $tableName, true);
    $sortDirection = strtolower($filters['sort_direction']) === 'asc' ? 'asc' : 'desc';

    $query->orderBy($sortColumn, $sortDirection);
  }

  /**
   * Xử lý pagination và lấy data
   */
  private static function processPagination($query, array $filters, array $columns): array
  {
    // Cấu hình pagination
    $limit = (int) ($filters['limit'] ?? 10);
    $page = max(1, (int) ($filters['page'] ?? 1));

    if ($limit <= 0 && $limit !== -1) {
      $limit = 10;
    }

    // Đếm tổng số record
    $total = (clone $query)->count();

    // Trường hợp lấy tất cả (limit = -1)
    if ($limit < 0) {
      $collection = $query->select($columns)->get();
      return [
        'collection' => $collection,
        'total' => $total,
        'total_current' => $collection->count(),
        'from' => 1,
        'to' => $total,
        'current_page' => 1,
        'last_page' => 1,
        'next_page' => 1,
      ];
    }

    // Áp dụng pagination
    $query->limit($limit)->offset(($page - 1) * $limit);
    $collection = $query->select($columns)->get();

    return [
      'collection' => $collection,
      'total' => $total,
      'total_current' => $collection->count(),
      'from' => ($page - 1) * $limit + 1,
      'to' => ($page - 1) * $limit + $collection->count(),
      'current_page' => $page,
      'last_page' => $limit > 0 ? ceil($total / $limit) : 1,
      'next_page' => $page < ceil($total / $limit) ? $page + 1 : $page,
    ];
  }

  /**
   * Áp dụng filter cho query dựa trên operator
   */
  private static function applyFilter($query, string $field, string $operator, $value): void
  {
    switch ($operator) {
      case self::OPERATORS['EQUAL']:
        $query->where($field, '=', $value);
        break;

      case self::OPERATORS['NOT_EQUAL']:
        $query->where($field, '<>', $value);
        break;

      case self::OPERATORS['CONTAIN']:
        $query->where($field, 'like', '%' . $value . '%');
        break;

      case self::OPERATORS['LESS_THAN']:
        $query->where($field, '<', $value);
        break;

      case self::OPERATORS['LESS_THAN_OR_EQUAL_TO']:
        $query->where($field, '<=', $value);
        break;

      case self::OPERATORS['GREATER_THAN']:
        self::applyGreaterThanFilter($query, $field, $value);
        break;

      case self::OPERATORS['GREATER_THAN_OR_EQUAL_TO']:
        $query->where($field, '>=', $value);
        break;

      case self::OPERATORS['EQUAL_TO']:
        self::applyEqualToFilter($query, $field, $value);
        break;

      case self::OPERATORS['BETWEEN']:
        self::applyBetweenFilter($query, $field, $value);
        break;

      case self::OPERATORS['INCLUDES']:
        self::applyIncludesFilter($query, $field, $value);
        break;

      case self::OPERATORS['NOT_INCLUDES']:
        self::applyNotIncludesFilter($query, $field, $value);
        break;

      default:
        Log::warning('Operator không được hỗ trợ: ' . $operator);
    }
  }

  /**
   * Xử lý filter GREATER_THAN (có xử lý đặc biệt cho ngày)
   */
  private static function applyGreaterThanFilter($query, string $field, $value): void
  {
    if (self::isDateString($value)) {
      // Với ngày: lấy từ ngày hôm sau
      $nextDay = Carbon::parse($value)->addDay()->startOfDay()->format('Y-m-d H:i:s');
      $query->where($field, '>', $nextDay);
    } else {
      $query->where($field, '>', $value);
    }
  }

  /**
   * Xử lý filter EQUAL_TO (có xử lý đặc biệt cho ngày)
   */
  private static function applyEqualToFilter($query, string $field, $value): void
  {
    if (self::isDateString($value)) {
      // Với ngày: filter theo cả ngày (từ 00:00:01 đến 23:59:59)
      $startDate = Carbon::parse($value)->startOfDay();
      $endDate = Carbon::parse($value)->endOfDay();
      $query->whereBetween($field, [$startDate, $endDate]);
    } else {
      $query->where($field, '=', $value);
    }
  }

  /**
   * Xử lý filter BETWEEN
   */
  private static function applyBetweenFilter($query, string $field, $value): void
  {
    $values = self::parseArrayValue($value);
    if (count($values) !== 2) {
      throw new \InvalidArgumentException('BETWEEN cần 2 giá trị');
    }

    $startValue = $values[0];
    $endValue = $values[1];

    // Kiểm tra xem có phải trường ngày tháng không
    if (self::isDateTimeField($field) && self::isDateTimeString($endValue)) {
      // Với trường ngày tháng: chuyển ngày kết thúc thành cuối ngày
      $endValue = Carbon::parse($endValue)->endOfDay()->format('Y-m-d H:i:s');
    }

    $query->whereBetween($field, [$startValue, $endValue]);
  }

  /**
   * Xử lý filter INCLUDES (WHERE IN)
   */
  private static function applyIncludesFilter($query, string $field, $value): void
  {
    $values = self::parseArrayValue($value);
    $query->whereIn($field, $values);
  }

  /**
   * Xử lý filter NOT_INCLUDES (WHERE NOT IN)
   */
  private static function applyNotIncludesFilter($query, string $field, $value): void
  {
    $values = self::parseArrayValue($value);
    $query->whereNotIn($field, $values);
  }

  // === HELPER METHODS ===

  /**
   * Kiểm tra filter có hợp lệ không
   */
  private static function isValidFilter(array $filter): bool
  {
    return isset($filter['field']) && isset($filter['operator']) && isset($filter['value']);
  }

  /**
   * Chuẩn bị giá trị filter (xử lý đặc biệt cho ngày)
   */
  private static function prepareFilterValue(array $filter, string $operator): mixed
  {
    $value = $filter['value'];

    // Xử lý đặc biệt cho trường ngày với LESS_THAN_OR_EQUAL_TO
    if (preg_match('/ngay|created_at|updated_at/', $filter['field']) && $operator === self::OPERATORS['LESS_THAN_OR_EQUAL_TO']) {
      return $value . ' 23:59:59';
    }

    return $value;
  }

  /**
   * Thêm table prefix cho field name
   */
  private static function addTablePrefix(string $fieldName, ?string $tableName, bool $ignoreFunction = false): string
  {
    if (!$tableName || str_contains($fieldName, '.')) {
      return $fieldName;
    }

    if ($ignoreFunction && str_contains($fieldName, '(')) {
      return $fieldName;
    }

    return $tableName . '.' . $fieldName;
  }

  /**
   * Lấy table name từ query
   */
  private static function getTableName($query): ?string
  {
    if (method_exists($query, 'getModel')) {
      return $query->getModel()->getTable();
    }

    return $query->from ?? null;
  }

  /**
   * Kiểm tra chuỗi có phải format ngày không (YYYY-MM-DD)
   */
  private static function isDateString(string $value): bool
  {
    return preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) === 1;
  }

  /**
   * Kiểm tra xem field có phải là trường ngày tháng không
   */
  private static function isDateTimeField(string $field): bool
  {
    // Loại bỏ table prefix nếu có
    $fieldName = str_contains($field, '.') ? explode('.', $field)[1] : $field;

    return preg_match('/ngay|created_at|updated_at|date|time/', $fieldName) === 1;
  }

  /**
   * Kiểm tra chuỗi có phải format datetime không (YYYY-MM-DD hoặc YYYY-MM-DD HH:MM:SS)
   */
  private static function isDateTimeString(string $value): bool
  {
    // Kiểm tra format YYYY-MM-DD HH:MM:SS
    if (preg_match('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/', $value) === 1) {
      return true;
    }

    // Kiểm tra format YYYY-MM-DD
    return preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) === 1;
  }

  /**
   * Parse giá trị thành array (hỗ trợ JSON)
   */
  private static function parseArrayValue($value): array
  {
    if (is_array($value)) {
      return $value;
    }

    if (is_string($value)) {
      $decoded = json_decode($value, true);
      if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
        return $decoded;
      }
    }

    throw new \InvalidArgumentException('Giá trị phải là array hoặc JSON array');
  }

  /**
   * Validate và clean filter data
   */
  public static function validateFilters(array $filters): array
  {
    $result = [];

    foreach ($filters as $key => $value) {
      switch ($key) {
        case 'f':
          $result[$key] = is_array($value)
            ? array_values(array_filter($value, [self::class, 'isValidFilter']))
            : [];
          break;

        case 'page':
          $result[$key] = max(1, (int) $value);
          break;

        case 'limit':
          $result[$key] = (int) $value;
          break;

        case 'sort_direction':
          $result[$key] = in_array(strtolower($value), ['asc', 'desc']) ? $value : 'desc';
          break;

        default:
          $result[$key] = $value;
      }
    }

    return $result;
  }

  /**
   * Hàm helper để sử dụng joins dễ dàng hơn
   */
  public static function findWithJoinsAndPagination(
    $query,
    array $joins,
    array $filters,
    array $columns = ['*']
  ): array {
    return self::findWithPagination($query, $filters, $columns, $joins);
  }
}