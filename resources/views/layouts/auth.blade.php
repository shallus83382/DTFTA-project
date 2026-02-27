<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>@yield('title', 'DTFTA CRM')</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            justify-content: center;
            align-items: center;
            padding: 20px;
        }
        .login-container {
            width: 100%;
            max-width: 400px;
            background: white;
            border-radius: 10px;
            box-shadow: 0 10px 40px rgba(0, 0, 0, 0.2);
            overflow: hidden;
        }
        .login-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 40px 30px;
            text-align: center;
        }
        .login-header h1 { 
            font-size: 28px; 
            margin-bottom: 10px; 
            color: white;
        }
        .login-header p { 
            font-size: 14px; 
            opacity: 0.9; 
            color: white;
        }
        .login-body { 
            padding: 40px 30px;
         }
        .form-title {
            color: #333;
            font-size: 22px;
            font-weight: 700;
            text-align: center;
            margin-bottom: 10px;
        }
        .form-subtitle {
            color: #666;
            font-size: 14px;
            text-align: center;
            margin-bottom: 24px;
            line-height: 1.5;
        }
        .form-group { 
            margin-bottom: 20px; 
        }
        .form-group label {
            display: block;
            margin-bottom: 8px;
            color: #333;
            font-weight: 500;
            font-size: 14px;
        }
        .form-group input {
            width: 100%;
            padding: 12px 15px;
            border: 1px solid #ddd;
            border-radius: 5px;
            font-size: 14px;
            transition: border-color 0.3s;
        }
        .form-group input:focus {
            outline: none;
            border-color: #667eea;
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
        }
        .login-btn {
            width: 100%;
            padding: 12px 15px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border: none;
            border-radius: 5px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: transform 0.2s, box-shadow 0.2s;
            margin-top: 10px;
        }
        .login-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 20px rgba(102, 126, 234, 0.4);
        }
        .login-btn:active { 
            transform: translateY(0); 
        }
        .login-btn:disabled { 
            opacity: 0.6; cursor: not-allowed; 
            transform: none; 
        }
        .error-message {
            background: #fee;
            color: #c33;
            padding: 12px 15px;
            border-radius: 5px;
            margin-bottom: 20px;
            font-size: 14px;
            display: none;
        }
        .error-message.show { 
            display: block; 
        }
        .success-message {
            background: #efe;
            color: #363;
            padding: 12px 15px;
            border-radius: 5px;
            margin-bottom: 20px;
            font-size: 14px;
            display: none;
        }
        .success-message.show { 
            display: block; 
        }
        .loading { 
            display: none; 
            text-align: center; 
            margin: 10px 0; 
        }
        .spinner {
            border: 3px solid #f3f3f3;
            border-top: 3px solid #667eea;
            border-radius: 50%;
            width: 20px;
            height: 20px;
            animation: spin 1s linear infinite;
            display: inline-block;
        }
        @keyframes spin { 0% { transform: rotate(0deg); } 100% { transform: rotate(360deg); } }
        .loading.show { 
            display: block; 
        }
        .demo-credentials {
            background: #f5f5f5;
            padding: 15px;
            border-radius: 5px;
            margin-top: 20px;
            font-size: 13px;
            color: #666;
        }
        .demo-credentials strong { color: #333; }
        .login-link { text-align: center; margin-top: 20px; color: #666; font-size: 14px; }
        .login-link a { color: #667eea; text-decoration: none; font-weight: 600; }
        .login-link a:hover { text-decoration: underline; }
        .bottom-link { margin-top: 16px; }
    </style>
</head>
<body>
    <div class="login-container">
        <div class="login-header">
            <h1>DTFTA CRM</h1>
            <p>Shopify Fulfillment Automation</p>
        </div>
        <div class="login-body">
            @yield('content')
        </div>
    </div>
    @stack('scripts')
</body>
</html>
