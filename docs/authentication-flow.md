## Luồng đăng nhập cơ bản

### 1. Tiếp nhận yêu cầu đăng nhập

Khi người dùng gửi yêu cầu đăng nhập với email và mật khẩu, hệ thống sẽ thực hiện các bước sau:

```
POST /api/login
{
    "email": "example@email.com",
    "password": "mật_khẩu"
}
```

### 2. Kiểm tra tài khoản bị khóa

-   Hệ thống kiểm tra xem email có đang bị khóa tạm thời do đăng nhập sai nhiều lần không
-   Sử dụng hai khóa Cache:
    -   `login_lockout_{email}`: Thời gian khóa tài khoản
    -   `login_attempts_{email}`: Số lần đăng nhập sai

### 3. Kiểm tra thông tin đăng nhập

-   Hệ thống kiểm tra email và mật khẩu
-   Nếu không chính xác, hệ thống sẽ:
    -   Tăng bộ đếm đăng nhập sai
    -   Nếu vượt quá số lần quy định (lấy từ cấu hình), tài khoản sẽ bị khóa tạm thời
    -   Trả về thông báo lỗi và số lần đăng nhập còn lại

### 4. Kiểm tra thời gian làm việc

-   Nếu tính năng kiểm tra thời gian làm việc được kích hoạt trong cấu hình
-   Hệ thống sẽ kiểm tra thời gian hiện tại có nằm trong giờ làm việc không
-   Nếu ngoài giờ làm việc, hệ thống sẽ từ chối đăng nhập

### 5. Xử lý xác thực hai yếu tố (2FA)

-   Nếu tính năng 2FA được kích hoạt trong cấu hình
-   Hệ thống kiểm tra thiết bị đăng nhập:
    -   Nếu đã xác thực trước đó (thiết bị đã được lưu), tiếp tục đăng nhập
    -   Nếu chưa xác thực, gửi mã OTP qua email và yêu cầu người dùng nhập mã

### 6. Tạo JWT Token và Cookie

-   Tạo access token và refresh token
-   Lưu token vào cookie với các thiết lập bảo mật:
    -   HTTPOnly: Ngăn JavaScript truy cập vào cookie
    -   Secure: Chỉ gửi qua HTTPS
    -   SameSite=None: Cho phép sử dụng cookie trong yêu cầu cross-site
-   Nếu có thiết bị mới được xác thực, lưu thêm device_id vào cookie

### 7. Trả về thông tin người dùng

-   Trả về thông tin người dùng, token và thông báo đăng nhập thành công

## Xác thực hai yếu tố (2FA)

### 1. Gửi OTP

Khi người dùng đăng nhập từ thiết bị mới:

-   Hệ thống tạo mã OTP ngẫu nhiên
-   Lưu mã OTP vào Cache với thời gian hết hạn (từ cấu hình)
-   Gửi mã OTP qua email
-   Trả về thông báo yêu cầu xác thực OTP

### 2. Xác thực OTP

Khi người dùng gửi mã OTP:

```
POST /api/verify-otp
{
    "user_id": "id_người_dùng",
    "otp": "mã_otp"
}
```

-   Hệ thống kiểm tra mã OTP với mã đã lưu trong Cache
-   Nếu đúng:
    -   Tạo device_id mới
    -   Lưu thông tin thiết bị vào cơ sở dữ liệu
    -   Xóa mã OTP khỏi Cache
    -   Tạo JWT token và cookie
    -   Trả về thông tin người dùng

## Làm mới Token (Token Refresh)

### 1. Kiểm tra token hiện tại

Khi ứng dụng gửi yêu cầu làm mới token:

-   Kiểm tra access token còn hạn hay đã hết hạn
-   Nếu còn hạn, xác thực người dùng và kiểm tra refresh token

### 2. Sử dụng refresh token

-   Giải mã refresh token để lấy thông tin user_id và thời gian hết hạn
-   Kiểm tra thời gian hết hạn của refresh token
-   Tìm kiếm người dùng theo user_id

### 3. Tạo token mới

-   Nếu access token đã hết hạn: Sử dụng `Auth::login()` để tạo token mới
-   Nếu access token còn hạn: Sử dụng `Auth::refresh()` để làm mới token hiện tại
-   Tạo cookie mới cho access token
-   Trả về token mới và thông tin người dùng

## Quên mật khẩu

### 1. Gửi yêu cầu khôi phục mật khẩu

```
POST /api/forgot-password
{
    "email": "example@email.com"
}
```

-   Kiểm tra email có tồn tại trong hệ thống không
-   Tạo token ngẫu nhiên
-   Lưu token vào bảng `password_reset_tokens`
-   Gửi email với đường dẫn khôi phục mật khẩu kèm token

### 2. Đặt lại mật khẩu

```
POST /api/reset-password
{
    "token": "token_từ_email",
    "password": "mật_khẩu_mới"
}
```

-   Kiểm tra token có tồn tại trong bảng `password_reset_tokens` không
-   Tìm người dùng theo email liên kết với token
-   Cập nhật mật khẩu mới (đã băm)
-   Xóa token khỏi bảng `password_reset_tokens`

## Đăng xuất

```
POST /api/logout
```

-   Xóa mã OTP khỏi Cache (nếu có)
-   Xóa tất cả cookie (access_token, refresh_token, device_id)
-   Đăng xuất người dùng khỏi hệ thống

## Cơ chế bảo mật

### 1. Khóa tài khoản tạm thời

-   Số lần đăng nhập sai tối đa được cấu hình trong bảng `cau_hinh_chung`
-   Thời gian khóa tài khoản được cấu hình trong bảng `cau_hinh_chung`
-   Sử dụng Cache để theo dõi số lần đăng nhập sai và thời gian khóa

### 2. Kiểm tra thời gian làm việc

-   Chỉ cho phép đăng nhập trong giờ làm việc (nếu tính năng được kích hoạt)
-   Thời gian làm việc được cấu hình trong bảng `thoi_gian_lam_viec`

### 3. Xác thực hai yếu tố (2FA)

-   Được kích hoạt/vô hiệu hóa thông qua cấu hình
-   Sử dụng OTP gửi qua email
-   Lưu thông tin thiết bị đã xác thực để không yêu cầu OTP lần sau
-   Thời hạn của thiết bị đã xác thực được cấu hình trong bảng `cau_hinh_chung`

### 4. JWT Token và Cookie

-   Access token: Có thời hạn ngắn (thường là 1 ngày)
-   Refresh token: Có thời hạn dài hơn (thường là 2 tuần)
-   Lưu token vào cookie với các thiết lập bảo mật cao
-   Cơ chế làm mới token tự động khi access token hết hạn
