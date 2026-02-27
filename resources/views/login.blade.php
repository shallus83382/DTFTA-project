@extends('layouts.auth')

@section('title', 'DTFTA CRM - Login')

@section('content')
    <div class="error-message" id="errorMessage"></div>

    <form id="loginForm">
        @csrf
        <div class="form-group">
            <label for="email">Email Address</label>
            <input type="email" id="email" name="email" placeholder="admin@test.com" required autocomplete="email">
        </div>

        <div class="form-group">
            <label for="password">Password</label>
            <input type="password" id="password" name="password" placeholder="********" required autocomplete="current-password">
        </div>

        <div class="loading" id="loading">
            <div class="spinner"></div>
            <p>Logging in...</p>
        </div>

        <button type="submit" class="login-btn" id="loginBtn">Login to Dashboard</button>

        <div class="login-link bottom-link">
            <a href="/forgot-password">Forgot password? Reset it here</a>
        </div>
    </form>
@endsection

@push('scripts')
<script>
    const loginForm = document.getElementById('loginForm');
    const loginBtn = document.getElementById('loginBtn');
    const errorMessage = document.getElementById('errorMessage');
    const loading = document.getElementById('loading');

    loginForm.addEventListener('submit', async (e) => {
        e.preventDefault();
        const email = document.getElementById('email').value;
        const password = document.getElementById('password').value;
        const csrfToken = document.querySelector('input[name="_token"]').value;

        loading.classList.add('show');
        loginBtn.disabled = true;
        errorMessage.classList.remove('show');

        try {
            const response = await fetch('/auth/login', {
                method: 'POST',
                credentials: 'same-origin',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': csrfToken,
                },
                body: JSON.stringify({ email: email, password: password, _token: csrfToken })
            });

            const data = await response.json();
            if (!response.ok) {
                throw new Error(data.message || 'Login failed. Please check your credentials.');
            }
            if (!data.token) {
                throw new Error('No token received from server. Please try again.');
            }

            localStorage.setItem('auth_token', data.token);
            localStorage.setItem('user_info', JSON.stringify(data.user));
            document.cookie = 'auth_token=' + encodeURIComponent(data.token) + '; path=/; SameSite=Lax';
            window.location.href = '/crm/dashboard';
        } catch (error) {
            loading.classList.remove('show');
            loginBtn.disabled = false;
            errorMessage.textContent = error.message;
            errorMessage.classList.add('show');
            console.error('Login error:', error);
        }
    });

    (async function bootstrapLoginRedirect() {
        const existingToken = localStorage.getItem('auth_token');
        if (!existingToken) return;
        try {
            const response = await fetch('/auth/me', {
                method: 'GET',
                credentials: 'same-origin',
                headers: {
                    'Accept': 'application/json',
                    'Authorization': 'Bearer ' + existingToken
                }
            });
            if (!response.ok) throw new Error('Invalid token');
            document.cookie = 'auth_token=' + encodeURIComponent(existingToken) + '; path=/; SameSite=Lax';
            window.location.href = '/crm/dashboard';
        } catch (error) {
            localStorage.removeItem('auth_token');
            localStorage.removeItem('user_info');
            document.cookie = 'auth_token=; path=/; expires=Thu, 01 Jan 1970 00:00:00 GMT; SameSite=Lax';
        }
    })();
</script>
@endpush
