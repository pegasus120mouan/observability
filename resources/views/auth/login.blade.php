@extends('layouts.guest', ['title' => 'Sign in'])

@section('content')
    <div class="auth-card">
        <div class="text-center mb-4">
            <div class="brand-mark mx-auto mb-3">{{ mb_substr(config('platform.short_name'), 0, 1) }}</div>
            <h1 class="h4 mb-1">{{ config('platform.name') }}</h1>
            <p class="text-secondary mb-0">Sign in to monitor your infrastructure</p>
        </div>

        <form method="POST" action="{{ route('login.store') }}">
            @csrf

            <div class="mb-3">
                <label for="email" class="form-label">Email</label>
                <input id="email" type="email" name="email" value="{{ old('email') }}" class="form-control @error('email') is-invalid @enderror" required autofocus autocomplete="username">
                @error('email')
                    <div class="invalid-feedback">{{ $message }}</div>
                @enderror
            </div>

            <div class="mb-3">
                <label for="password" class="form-label">Password</label>
                <input id="password" type="password" name="password" class="form-control @error('password') is-invalid @enderror" required autocomplete="current-password">
                @error('password')
                    <div class="invalid-feedback">{{ $message }}</div>
                @enderror
            </div>

            <div class="form-check mb-4">
                <input class="form-check-input" type="checkbox" name="remember" id="remember">
                <label class="form-check-label" for="remember">Remember me</label>
            </div>

            <button type="submit" class="btn btn-primary w-100">Sign in</button>
        </form>
    </div>
@endsection
