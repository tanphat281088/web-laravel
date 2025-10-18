# Luồng thực hiện xác thực 2FA

## Kiểm tra và xác minh đăng nhập

-   Sau khi kiểm tra thông tin đăng nhập đã chính xác (email và mật khẩu)
-   Kiểm tra xem xác thực 2 yếu tố có được kích hoạt trong `CauHinhChung` hay không
-   Kiểm tra `device_id` trong cookie:
    -   Nếu không có `device_id` hoặc `device_id` không tồn tại trong bảng `thiet_bi` cho user hiện tại
    -   => Tiến hành xử lý xác thực 2FA

## Xử lý xác thực 2FA

1. **Tạo và gửi OTP**

    - Tạo OTP (code 6 số) bằng `Helper::generateOTP()`
    - Lưu OTP vào Cache: `Cache::put('otp:user:' . $user->id, $otp, now()->addMinutes((int)$cauHinhChung['THOI_GIAN_HET_HAN_OTP']))`
    - Gửi email chứa OTP cho người dùng qua `OTPMail`
    - Response về client chứa:
        - `is_2fa: true`
        - `user_id`: id của người dùng
        - Thông báo gửi OTP thành công và hướng dẫn kiểm tra email

2. **Xử lý phía Client**

    - Client lưu `user_id` vào localStorage
    - Hiển thị form nhập OTP
    - Client submit: OTP và `user_id`

3. **Xác minh OTP**

    - Gọi đến route POST `/verify-otp`
    - Validate các trường: `user_id` và `otp`
    - Kiểm tra người dùng có tồn tại không
    - Lấy OTP từ Cache đã lưu theo `user_id`
    - So sánh OTP client gửi xuống với OTP trong Cache
    - Nếu OTP không đúng hoặc hết hạn => trả lỗi

4. **Khi xác minh OTP thành công**
    - Tạo `device_id` mới với `Str::uuid()`
    - Tạo bản ghi trong bảng `thiet_bi` với các thông tin:
        - `user_id`: ID của người dùng
        - `device_id`: ID thiết bị vừa tạo
        - `ip_address`: IP của request
    - Xóa Cache OTP: `Cache::forget('otp:user:' . $user_id)`
    - Tạo JWT token bằng `Auth::login($user)`
    - Xử lý tạo cookies:
        - Cookie `access_token`
        - Cookie `refresh_token`
        - Cookie `device_id` với thời hạn dựa theo cấu hình `THOI_HAN_XAC_THUC_LAI_THIET_BI`
    - Trả về response thành công kèm thông tin người dùng và token

## Lưu ý quan trọng

-   Thời gian sống của OTP được lấy từ cấu hình `THOI_GIAN_HET_HAN_OTP` trong bảng `cau_hinh_chung`
-   Thời hạn xác thực lại thiết bị được lấy từ cấu hình `THOI_HAN_XAC_THUC_LAI_THIET_BI` trong bảng `cau_hinh_chung`
-   OTP được lưu vào Cache thay vì lưu vào cột trong bảng `users`
-   Quá trình đăng nhập sau khi xác thực 2FA sẽ trả về 3 cookies:
    -   `access_token`: JWT token chính để xác thực API
    -   `refresh_token`: Token để refresh JWT khi hết hạn
    -   `device_id`: ID thiết bị để kiểm tra cho lần đăng nhập sau
-   Trong trường hợp đăng nhập thông thường trên thiết bị đã đăng nhập trước đó: chỉ trả về 2 cookies `access_token` và `refresh_token`
