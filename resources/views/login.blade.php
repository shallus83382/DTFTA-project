@extends('layouts.auth')

@section('title', 'DTFTA CRM - Login')

@section('content')
    @if ($errors->any())
        <div class="error-message show">
            {{ $errors->first() }}
        </div>
    @endif

    <form method="POST" action="{{ route('auth.login') }}">
        @csrf
        <div class="form-group">
            <label for="email">Email Address</label>
            <input type="email" id="email" name="email" value="{{ old('email') }}" placeholder="admin@test.com" required autocomplete="email">
        </div>

        <div class="form-group">
            <label for="password">Password</label>
            <input type="password" id="password" name="password" placeholder="********" required autocomplete="current-password">
        </div>
        <button type="submit" class="login-btn">Login to Dashboard</button>

        <div class="login-link bottom-link">
            <a href="/forgot-password">Forgot password? Reset it here</a>
        </div>
    </form>
@endsection
