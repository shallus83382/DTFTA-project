@extends('layouts.auth')

@section('title', 'DTFTA CRM - Set New Password')

@section('content')
    <h2 class="form-title">Set New Password</h2>
    <p class="form-subtitle">Enter your new password below. It must be at least 8 characters.</p>

    <div class="error-message" id="errorMessage"></div>
    <div class="success-message" id="successMessage"></div>

    <form id="resetPasswordForm">
        @csrf
        <input type="hidden" id="email" name="email" value="{{ old('email', $email ?? '') }}">
        <input type="hidden" id="token" name="token" value="{{ old('token', $token ?? '') }}">

        <div class="form-group">
            <label for="password">New Password</label>
            <input type="password" id="password" name="password" placeholder="At least 8 characters" required minlength="8" autocomplete="new-password">
        </div>

        <div class="form-group">
            <label for="password_confirmation">Confirm Password</label>
            <input type="password" id="password_confirmation" name="password_confirmation" placeholder="Confirm new password" required minlength="8" autocomplete="new-password">
        </div>

        <div class="loading" id="loading">
            <div class="spinner"></div>
            <p>Updating password...</p>
        </div>

        <button type="submit" class="login-btn" id="submitBtn">Reset Password</button>

        <div class="login-link bottom-link">
            <a href="/login">Back to Sign In →</a>
        </div>
    </form>
@endsection

@push('scripts')
<script>
    const form = document.getElementById('resetPasswordForm');
    const submitBtn = document.getElementById('submitBtn');
    const errorMessage = document.getElementById('errorMessage');
    const successMessage = document.getElementById('successMessage');
    const loading = document.getElementById('loading');

    form.addEventListener('submit', async (e) => {
        e.preventDefault();

        const email = document.getElementById('email').value;
        const token = document.getElementById('token').value;
        const password = document.getElementById('password').value;
        const password_confirmation = document.getElementById('password_confirmation').value;

        if (!email || !token) {
            errorMessage.textContent = 'Invalid reset link. Please request a new password reset.';
            errorMessage.classList.add('show');
            return;
        }
        if (password.length < 8) {
            errorMessage.textContent = 'Password must be at least 8 characters.';
            errorMessage.classList.add('show');
            return;
        }
        if (password !== password_confirmation) {
            errorMessage.textContent = 'Passwords do not match.';
            errorMessage.classList.add('show');
            return;
        }

        loading.classList.add('show');
        submitBtn.disabled = true;
        errorMessage.classList.remove('show');
        successMessage.classList.remove('show');

        try {
            const csrfToken = document.querySelector('input[name="_token"]').value;
            const response = await fetch('{{ route("reset-password.submit") }}', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',

                },
                body: JSON.stringify({
                    email: email,
                    token: token,
                    password: password,
                    password_confirmation: password_confirmation,
                    _token: csrfToken
                }),
            });

            let data = null;
            const contentType = response.headers.get('content-type');
            if (contentType && contentType.includes('application/json')) {
                try { data = await response.json(); } catch (_) { data = null; }
            }

            if (!response.ok) {
                const msg = (data && data.message) ? data.message : 'Something went wrong. Please try again.';
                throw new Error(msg);
            }

            successMessage.textContent = (data && data.message) ? data.message : 'Password reset successfully. You can now sign in.';
            successMessage.classList.add('show');
            form.querySelector('#password').value = '';
            form.querySelector('#password_confirmation').value = '';

            setTimeout(() => { window.location.href = '/login'; }, 2000);
        } catch (error) {
            errorMessage.textContent = error.message || 'Something went wrong. Please try again.';
            errorMessage.classList.add('show');
        } finally {
            loading.classList.remove('show');
            submitBtn.disabled = false;
        }
    });
</script>
@endpush
