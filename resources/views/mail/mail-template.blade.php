<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="ie=edge">
    <title>Đặt lại mật khẩu</title>
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Roboto:wght@300;400;500;700&display=swap');

        body {
            font-family: 'Roboto', Arial, sans-serif;
            line-height: 1.6;
            color: #333333;
            background-color: #f9f9f9;
            margin: 0;
            padding: 0;
        }

        .container {
            max-width: 600px;
            margin: 0 auto;
            padding: 40px 20px;
            background-color: #ffffff;
            border-radius: 8px;
            box-shadow: 0 4px 10px rgba(0, 0, 0, 0.05);
        }

        .header {
            text-align: center;
            padding-bottom: 20px;
            border-bottom: 1px solid #eeeeee;
            margin-bottom: 30px;
        }

        .logo {
            margin-bottom: 20px;
        }

        .logo img {
            height: 50px;
        }

        h1 {
            color: #2563EB;
            font-size: 24px;
            font-weight: 700;
            margin-bottom: 5px;
        }

        .subtitle {
            color: #6B7280;
            font-size: 16px;
            font-weight: 400;
            margin-top: 0;
        }

        .content {
            padding: 0 15px;
            margin-bottom: 30px;
        }

        p {
            margin-bottom: 15px;
            font-size: 16px;
        }

        .name {
            font-weight: 600;
        }

        .button-container {
            text-align: center;
            margin: 30px 0;
        }

        .button {
            display: inline-block;
            background-color: #2563EB;
            color: #ffffff !important;
            text-decoration: none;
            padding: 12px 30px;
            border-radius: 6px;
            font-weight: 500;
            font-size: 16px;
            transition: background-color 0.3s ease;
        }

        .button:hover {
            background-color: #1D4ED8;
        }

        .help-text {
            font-size: 14px;
            color: #6B7280;
            margin-top: 25px;
        }

        .link-url {
            color: #6B7280;
            font-size: 13px;
            word-break: break-all;
            margin-top: 5px;
        }

        .footer {
            margin-top: 40px;
            text-align: center;
            color: #6B7280;
            font-size: 14px;
            padding-top: 20px;
            border-top: 1px solid #eeeeee;
        }

        .social-links {
            margin: 15px 0;
        }

        .social-icon {
            display: inline-block;
            margin: 0 5px;
        }
    </style>
</head>

<body>
    <div class="container">
        <div class="header">
            <h1>Đặt lại mật khẩu</h1>
            <p class="subtitle">Hướng dẫn khôi phục tài khoản của bạn</p>
        </div>

        <div class="content">
            <p>Xin chào <span class="name">{{ $data['name'] }}</span>,</p>

            <p>Chúng tôi nhận được yêu cầu đặt lại mật khẩu cho tài khoản của bạn. Vui lòng nhấn vào nút bên dưới để
                tiếp tục quy trình đặt lại mật khẩu.</p>

            <div class="button-container">
                <a href="{{ $data['url'] }}" class="button">Đặt lại mật khẩu</a>
            </div>

            <p class="help-text">Nếu bạn không yêu cầu đặt lại mật khẩu, vui lòng bỏ qua email này hoặc liên hệ với
                chúng tôi nếu bạn có thắc mắc.</p>

            <p class="link-url">Hoặc sao chép liên kết này vào trình duyệt: {{ $data['url'] }}</p>
        </div>
    </div>
</body>

</html>
