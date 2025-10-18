<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="ie=edge">
    <title>Mã OTP Xác Thực</title>
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
            text-align: center;
        }

        p {
            margin-bottom: 15px;
            font-size: 16px;
        }

        .otp-code {
            font-size: 32px;
            font-weight: 700;
            letter-spacing: 5px;
            color: #2563EB;
            background-color: #EFF6FF;
            padding: 15px 30px;
            border-radius: 8px;
            display: inline-block;
            margin: 20px 0;
            border: 1px dashed #BFDBFE;
        }

        .expiry-notice {
            font-size: 14px;
            color: #EF4444;
            margin-top: 10px;
        }

        .help-text {
            font-size: 14px;
            color: #6B7280;
            margin-top: 25px;
            text-align: center;
        }
    </style>
</head>

<body>
    <div class="container">
        <div class="header">
            <h1>Mã OTP Xác Thực</h1>
            <p class="subtitle">Vui lòng sử dụng mã này để hoàn tất quá trình xác thực</p>
        </div>

        <div class="content">
            <p>Chào bạn,</p>

            <p>Đây là mã OTP của bạn:</p>

            <div class="otp-code">{{ $otp }}</div>

            <p class="expiry-notice">Mã OTP sẽ hết hạn sau {{ $thoiGianHetHanOTP }} phút</p>

            <p class="help-text">Nếu bạn không yêu cầu mã này, vui lòng bỏ qua email này hoặc liên hệ với chúng tôi nếu
                bạn có thắc mắc.</p>
        </div>
    </div>
</body>

</html>
