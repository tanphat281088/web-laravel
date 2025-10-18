```php

// Tạo query cơ bản
$query = User::query();

    // Cấu hình joins
    $joins = [
      [
        'table' => 'nguoi_dung',
        'alias' => 'nguoi_tao',
        'condition' => 'users.nguoi_tao = nguoi_tao.id',
        'type' => 'left'
      ],
      [
        'table' => 'nguoi_dung',
        'alias' => 'nguoi_cap_nhat',
        'condition' => 'users.nguoi_cap_nhat = nguoi_cap_nhat.id',
        'type' => 'left'
      ]
    ];

    // Định nghĩa columns cần select
    $columns = [
      'users.*',
      'nguoi_tao.ho_va_ten as nguoi_tao',
      'nguoi_cap_nhat.ho_va_ten as nguoi_cap_nhat'
    ];

    // Sử dụng helper method
    return FilterWithPagination::findWithJoinsAndPagination(
      $query,
      $joins,
      $params,
      $columns
    );
```
