# **_ Đối với src BACKEND _**

## - SỬ DỤNG LARAGON VÀ BẬT SSL để sử dụng https

## - CHẠY LỆNH composer install

## - SỬA LẠI BIẾN APP_URL TRONG FILE .env THEO TÊN PROJECT

## - TẠO TABLE VÀ SEED DATABASE

### Tạo table

php artisan migrate hoặc php artisan migrate:fresh

### Seed database

php artisan db:seed

## - TẠO VÀ XOÁ MODULE

### Cách cơ bản - sẽ tự tạo model từ tên module

php artisan make:module SanPham --api

### Chỉ định model cụ thể

php artisan make:module SanPham --model=Product --api

### Chỉ định tên route và model

php artisan make:module SanPham --model=Product --route=products --api

### Xoá module đã tạo

php artisan remove:module TenModule

## - SỬ DỤNG SCHEDULE

### Xem danh sách Schedule

php artisan schedule:list

### Khởi động Schedule

php artisan schedule:work

## - TẠO FILE CẤU HÌNH NỘI DUNG IMPORT EXCEL

php artisan make:excel-import [TenMoDule]Import

# **_ Đối với src FRONTEND _**

## - CÀI THƯ VIỆN TỪ NPM

### Chạy lệnh cài thư viện

yarn hoặc npm i

## - THIẾT LẬP baseURL TRONG FILE src/configs/axios.ts

## - TẠO VÀ MODULE

### Cách tạo module

node cli.js add [TênModule]

### Khi tạo 1 module lưu ý các nội dung trong các file api-route-config, app-router, sidebar-config
