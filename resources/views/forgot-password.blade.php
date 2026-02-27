@extends('layouts.auth')

@section('title', 'DTFTA CRM - Reset Password')

@section('content')
    <h2 class="form-title">Reset Your Password</h2>
    <p class="form-subtitle">Enter your email and we will send you a link to reset your password.</p>

    <div class="error-message" id="errorMessage"></div>
    <div class="success-message" id="successMessage"></div>

    <form id="forgotPasswordForm">
        @csrf
        <div class="form-group">
            <label for="email">Email</label>
            <input type="email" id="email" name="email" placeholder="Enter your email" required autocomplete="email">
        </div>

        <div class="loading" id="loading">
            <div class="spinner"></div>
            <p>Sending reset link...</p>
        </div>

        <button type="submit" class="login-btn" id="submitBtn">Reset My Password</button>

        <div class="login-link bottom-link">
            Already have an account? <a href="/login">Sign In →</a>
        </div>
    </form>
@endsection

@push('scripts')
<script>
    const form = document.getElementById('forgotPasswordForm');
    const submitBtn = document.getElementById('submitBtn');
    const errorMessage = document.getElementById('errorMessage');
    const successMessage = document.getElementById('successMessage');
    const loading = document.getElementById('loading');

    form.addEventListener('submit', async (e) => {
        e.preventDefault();
        const email = document.getElementById('email').value;

        loading.classList.add('show');
        submitBtn.disabled = true;
        errorMessage.classList.remove('show');
        successMessage.classList.remove('show');

        try {
            const csrfToken = document.querySelector('input[name="_token"]').value;
            const response = await fetch('{{ route("forgot-password.submit") }}', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                },
                body: JSON.stringify({ email: email, _token: csrfToken })
            });

            let data = null;
            const contentType = response.headers.get('content-type');
            if (contentType && contentType.includes('application/json')) {
                try { data = await response.json(); } catch (_) { data = null; }
            }

            if (!response.ok) {
                throw new Error((data && data.message) ? data.message : 'Something went wrong. Please try again.');
            }

            successMessage.textContent = (data && data.message) ? data.message : 'Password reset link sent to your email. Please check your inbox.';
            successMessage.classList.add('show');
            form.reset();
        } catch (error) {
            errorMessage.textContent = error.message || 'Something went wrong. Please try again.';
            errorMessage.classList.add('show');
            console.error('Forgot password error:', error);
        } finally {
            loading.classList.remove('show');
            submitBtn.disabled = false;
        }
    });
</script>
@endpush
