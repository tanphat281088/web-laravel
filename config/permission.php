<?php

return [

  [
    "name" => "dashboard",
    "actions" => [
      "index" => true,
      "create" => true,
      "show" => true,
      "edit" => true,
      "delete" => true,
      "export" => true,
      "showMenu" => true
    ]
  ],
          [
    "name" => "san-xuat",
    "actions" => [
      "index" => true,
      "create" => true,
      "show" => true,
      "edit" => true,
      "delete" => true,
      "export" => true,
      "showMenu" => true
    ]
  ],
[
    "name" => "cong-thuc-san-xuat",
    "actions" => [
      "index" => true,
      "create" => true,
      "show" => true,
      "edit" => true,
      "delete" => true,
      "export" => true,
      "showMenu" => true
    ]
  ],
[
    "name" => "phieu-thu",
    "actions" => [
      "index" => true,
      "create" => true,
      "show" => true,
      "edit" => true,
      "delete" => true,
      "export" => true,
      "showMenu" => true
    ]
  ],
[
    "name" => "phieu-xuat-kho",
    "actions" => [
      "index" => true,
      "create" => true,
      "show" => true,
      "edit" => true,
      "delete" => true,
      "export" => true,
      "showMenu" => true
    ]
  ],
[
    "name" => "quan-ly-ban-hang",
    "actions" => [
      "index" => true,
      "create" => true,
      "show" => true,
      "edit" => true,
      "delete" => true,
      "export" => true,
      "showMenu" => true
    ]
  ],
  [
    "name" => "phieu-chi",
    "actions" => [
      "index" => true,
      "create" => true,
      "show" => true,
      // "edit" => true,
      "delete" => true,
      "export" => true,
      "showMenu" => true
    ]
  ],
  [
    "name" => "quan-ly-ton-kho",
    "actions" => [
      "index" => true,
      // "create" => true,
      "show" => true,
      // "edit" => true,
      // "delete" => true,
      "export" => true,
      "showMenu" => true
    ]
  ],
  [
    "name" => "phieu-nhap-kho",
    "actions" => [
      "index" => true,
      "create" => true,
      "show" => true,
      "edit" => true,
      "delete" => true,
      "export" => true,
      "showMenu" => true
    ]
  ],
  [
    "name" => "quan-ly-cong-no",
    "actions" => [
      "index" => true,
      "export" => true,
      "showMenu" => true
    ]
  ],
  [
    "name" => "san-pham",
    "actions" => [
      "index" => true,
      "create" => true,
      "show" => true,
      "edit" => true,
      "delete" => true,
      "export" => true,
      "showMenu" => true
    ]
  ],
  [
    "name" => "don-vi-tinh",
    "actions" => [
      "index" => true,
      "create" => true,
      "show" => true,
      "edit" => true,
      "delete" => true,
      "export" => true,
      "showMenu" => true
    ]
  ],
  [
    "name" => "danh-muc-san-pham",
    "actions" => [
      "index" => true,
      "create" => true,
      "show" => true,
      "edit" => true,
      "delete" => true,
      "export" => true,
      "showMenu" => true
    ]
  ],
  [
    "name" => "nha-cung-cap",
    "actions" => [
      "index" => true,
      "create" => true,
      "show" => true,
      "edit" => true,
      "delete" => true,
      "export" => true,
      "showMenu" => true
    ]
  ],
  [
    "name" => "khach-hang",
    "actions" => [
      "index" => true,
      "create" => true,
      "show" => true,
      "edit" => true,
      "delete" => true,
      "export" => true,
      "showMenu" => true
    ]
  ],
  [
    "name" => "loai-khach-hang",
    "actions" => [
      "index" => true,
      "create" => true,
      "show" => true,
      "edit" => true,
      "delete" => true,
      "export" => true,
      "showMenu" => true
    ]
  ],
  [
    "name" => "cau-hinh-chung",
    "actions" => [
      "index" => true,
      "create" => true,
      "show" => true,
      "edit" => true,
      "delete" => true,
      "export" => true,
      "showMenu" => true
    ]
  ],
  [
    "name" => "thoi-gian-lam-viec",
    "actions" => [
      "index" => true,
      "create" => true,
      "show" => true,
      "edit" => true,
      "delete" => true,
      "export" => true,
      "showMenu" => true
    ]
  ],
  [
    "name" => "nguoi-dung",
    "actions" => [
      "index" => true,
      "create" => true,
      "show" => true,
      "edit" => true,
      "delete" => true,
      "export" => true,
      "showMenu" => true
    ]
  ],
  [
    "name" => "vai-tro",
    "actions" => [
      "index" => true,
      "create" => true,
      "show" => true,
      "edit" => true,
      "delete" => true,
      "export" => true,
      "showMenu" => true
    ]
  ],

  // ===== BỔ SUNG: CHĂM SÓC KHÁCH HÀNG =====
[
  "name" => "cskh",
  "actions" => [
    "showMenu" => true,
    "index"    => true   // << thêm dòng này
  ]
],

  [
    "name" => "cskh-points",
    "actions" => [
      "index" => true,     // xem danh sách biến động điểm
      "showMenu" => true,  // hiện menu con
      "send" => true       // cho phép bấm "Gửi ZNS" cho 1 biến động
      // "retry" => true    // (tuỳ chọn) cho phép gửi lại khi failed
    ]
  ]
];
