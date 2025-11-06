<?php

/**
 * Registry V2 cho RBAC chi tiết (default-deny).
 * - Mỗi mục gồm: name (module), actions (tất cả quyền = false mặc định).
 * - 7 action chuẩn: showMenu, index, show, create, edit, delete, export
 * - Action đặc thù (đã chốt): send/assign/status, sendZns, notifyAndSetStatus, convert, post/unpost
 *
 * Lưu ý:
 * - Parent modules (cskh, utilities) chủ yếu dùng showMenu; các endpoint thực tế nằm ở module con.
 * - VT ledger dùng quyền của vt-stocks (index/export).
 * - Cashflow chia nhỏ thành 4 module con để phân quyền hạt mịn.
 */

return [

  // ===== HỆ THỐNG =====
  ['name' => 'dashboard',          'actions' => ['showMenu'=>false,'index'=>false,'show'=>false,'create'=>false,'edit'=>false,'delete'=>false,'export'=>false]],
  ['name' => 'vai-tro',            'actions' => ['showMenu'=>false,'index'=>false,'show'=>false,'create'=>false,'edit'=>false,'delete'=>false,'export'=>false]],
  ['name' => 'nguoi-dung',         'actions' => ['showMenu'=>false,'index'=>false,'show'=>false,'create'=>false,'edit'=>false,'delete'=>false,'export'=>false]],
  ['name' => 'cau-hinh-chung',     'actions' => ['showMenu'=>false,'index'=>false,'show'=>false,'create'=>false,'edit'=>false,'delete'=>false,'export'=>false]],
  ['name' => 'thoi-gian-lam-viec', 'actions' => ['showMenu'=>false,'index'=>false,'show'=>false,'create'=>false,'edit'=>false,'delete'=>false,'export'=>false]],
  ['name' => 'lich-su-import',     'actions' => ['showMenu'=>false,'index'=>false,'show'=>false,'create'=>false,'edit'=>false,'delete'=>false,'export'=>false]],

  // ===== KHÁCH HÀNG / DANH MỤC =====
  ['name' => 'loai-khach-hang',        'actions' => ['showMenu'=>false,'index'=>false,'show'=>false,'create'=>false,'edit'=>false,'delete'=>false,'export'=>false]],
  ['name' => 'khach-hang',             'actions' => ['showMenu'=>false,'index'=>false,'show'=>false,'create'=>false,'edit'=>false,'delete'=>false,'export'=>false]],
  // Đặc thù: convert vãng lai → khách chuẩn
  ['name' => 'khach-hang-vang-lai',    'actions' => ['showMenu'=>false,'index'=>false,'show'=>false,'create'=>false,'edit'=>false,'delete'=>false,'export'=>false,'convert'=>false]],



  // ===== SẢN PHẨM / NCC =====
  ['name' => 'nha-cung-cap',       'actions' => ['showMenu'=>false,'index'=>false,'show'=>false,'create'=>false,'edit'=>false,'delete'=>false,'export'=>false]],
  ['name' => 'danh-muc-san-pham',  'actions' => ['showMenu'=>false,'index'=>false,'show'=>false,'create'=>false,'edit'=>false,'delete'=>false,'export'=>false]],
  ['name' => 'don-vi-tinh',        'actions' => ['showMenu'=>false,'index'=>false,'show'=>false,'create'=>false,'edit'=>false,'delete'=>false,'export'=>false]],
  ['name' => 'san-pham',           'actions' => ['showMenu'=>false,'index'=>false,'show'=>false,'create'=>false,'edit'=>false,'delete'=>false,'export'=>false]],
  // loai-san-pham (master options) nếu có trang riêng thì thêm showMenu/index

  // ===== KHO & BÁN HÀNG =====
  ['name' => 'phieu-nhap-kho',     'actions' => ['showMenu'=>false,'index'=>false,'show'=>false,'create'=>false,'edit'=>false,'delete'=>false,'export'=>false]],
  ['name' => 'phieu-xuat-kho',     'actions' => ['showMenu'=>false,'index'=>false,'show'=>false,'create'=>false,'edit'=>false,'delete'=>false,'export'=>false]],
  ['name' => 'quan-ly-ton-kho',    'actions' => ['showMenu'=>false,'index'=>false,'show'=>false,'create'=>false,'edit'=>false,'delete'=>false,'export'=>false]],
  ['name' => 'quan-ly-ban-hang',   'actions' => ['showMenu'=>false,'index'=>false,'show'=>false,'create'=>false,'edit'=>false,'delete'=>false,'export'=>false]],

  // ===== GIAO HÀNG (tách module riêng) =====
  // Đặc thù: notifyAndSetStatus (gửi SMS + đặt trạng thái)
  ['name' => 'giao-hang',          'actions' => ['showMenu'=>false,'index'=>false,'show'=>false,'create'=>false,'edit'=>false,'delete'=>false,'export'=>false,'notifyAndSetStatus'=>false]],

  // ===== SẢN XUẤT =====
  ['name' => 'cong-thuc-san-xuat', 'actions' => ['showMenu'=>false,'index'=>false,'show'=>false,'create'=>false,'edit'=>false,'delete'=>false,'export'=>false]],
  ['name' => 'san-xuat',           'actions' => ['showMenu'=>false,'index'=>false,'show'=>false,'create'=>false,'edit'=>false,'delete'=>false,'export'=>false]],

  // ===== TÀI CHÍNH & BÁO CÁO =====
  ['name' => 'phieu-thu',          'actions' => ['showMenu'=>false,'index'=>false,'show'=>false,'create'=>false,'edit'=>false,'delete'=>false,'export'=>false]],
  ['name' => 'phieu-chi',          'actions' => ['showMenu'=>false,'index'=>false,'show'=>false,'create'=>false,'edit'=>false,'delete'=>false,'export'=>false]],
  ['name' => 'bao-cao-thu-chi',    'actions' => ['showMenu'=>false,'index'=>false,'show'=>false,'create'=>false,'edit'=>false,'delete'=>false,'export'=>false]],
  ['name' => 'bao-cao-quan-tri',   'actions' => ['showMenu'=>false,'index'=>false,'show'=>false,'create'=>false,'edit'=>false,'delete'=>false,'export'=>false]],

  // ===== CSKH =====
  ['name' => 'cskh',               'actions' => ['showMenu'=>false,'index'=>false]], // cha (menu)
  // Đặc thù: sendZns
  ['name' => 'cskh-points',        'actions' => ['showMenu'=>false,'index'=>false,'show'=>false,'create'=>false,'edit'=>false,'delete'=>false,'export'=>false,'sendZns'=>false]],

  // ===== UTILITIES =====
  ['name' => 'utilities',          'actions' => ['showMenu'=>false,'index'=>false]], // cha (menu)
  // Facebook: send/assign/status
  ['name' => 'utilities-fb',       'actions' => ['showMenu'=>false,'index'=>false,'show'=>false,'create'=>false,'edit'=>false,'delete'=>false,'export'=>false,'send'=>false,'assign'=>false,'status'=>false]],
  // Zalo: send/assign/status
  ['name' => 'utilities-zl',       'actions' => ['showMenu'=>false,'index'=>false,'show'=>false,'create'=>false,'edit'=>false,'delete'=>false,'export'=>false,'send'=>false,'assign'=>false,'status'=>false]],

  // ===== VT (VẬT TƯ) — hạt mịn =====
  ['name' => 'vt-items',           'actions' => ['showMenu'=>false,'index'=>false,'show'=>false,'create'=>false,'edit'=>false,'delete'=>false,'export'=>false]],
  ['name' => 'vt-receipts',        'actions' => ['showMenu'=>false,'index'=>false,'show'=>false,'create'=>false,'edit'=>false,'delete'=>false,'export'=>false]],
  ['name' => 'vt-issues',          'actions' => ['showMenu'=>false,'index'=>false,'show'=>false,'create'=>false,'edit'=>false,'delete'=>false,'export'=>false]],
  // ledger đi chung quyền với stocks (index/export)
  ['name' => 'vt-stocks',          'actions' => ['showMenu'=>false,'index'=>false,'show'=>false,'create'=>false,'edit'=>false,'delete'=>false,'export'=>false]],

  // ===== CASHFLOW — hạt mịn =====
  ['name' => 'cash-accounts',            'actions' => ['showMenu'=>false,'index'=>false,'show'=>false,'create'=>false,'edit'=>false,'delete'=>false,'export'=>false]],
  ['name' => 'cash-aliases',             'actions' => ['showMenu'=>false,'index'=>false,'show'=>false,'create'=>false,'edit'=>false,'delete'=>false,'export'=>false]],
  ['name' => 'cash-ledger',              'actions' => ['showMenu'=>false,'index'=>false,'show'=>false,'create'=>false,'edit'=>false,'delete'=>false,'export'=>false]],
  // Đặc thù: post/unpost
  ['name' => 'cash-internal-transfers',  'actions' => ['showMenu'=>false,'index'=>false,'show'=>false,'create'=>false,'edit'=>false,'delete'=>false,'export'=>false,'post'=>false,'unpost'=>false]],


// ===== QUẢN LÝ CÔNG NỢ =====
['name' => 'quan-ly-cong-no', 'actions' => [
    'showMenu' => true,  // hiện mục trên Sidebar
    'index'    => true,  // GET /cong-no/summary
    'show'     => true,  // GET /cong-no/customers/{id}
    'export'   => true,  // GET /cong-no/export
    // giữ read-only:
    'create'   => false,
    'edit'     => false,
    'delete'   => false,
]],


  // ===== NHÂN SỰ =====
['name' => 'nhan-su', 'actions' => [
  'showMenu' => false,
  'index'    => false,
  'show'     => false,
  'create'   => false,
  'edit'     => false,
  'delete'   => false,
  'export'   => false,
]],

];
