@extends('layouts.app')

@section('title', 'Masuk Admin - YokanCha')

@section('content')
<div class="min-h-screen bg-oat flex items-center justify-center py-12 px-4">
    <div class="w-full max-w-md">
        <div class="mb-8 text-center">
            <h1 class="text-3xl font-bold text-charcoal mb-2">Masuk Admin</h1>
            <p class="text-charcoal opacity-75">Khusus untuk pengelola YokanCha</p>
        </div>

        <div class="bg-surface rounded-brand shadow-sm p-8">
            @if ($errors->any())
                <div class="mb-6 p-4 bg-red-50 border-2 border-red-500 rounded-brand">
                    <ul class="text-red-600 text-sm space-y-1">
                        @foreach ($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            <form action="{{ route('admin.login.store') }}" method="POST" class="space-y-6">
                @csrf

                <div>
                    <label for="email" class="block text-sm font-semibold text-charcoal mb-2">
                        Email
                    </label>
                    <input
                        type="email"
                        id="email"
                        name="email"
                        value="{{ old('email') }}"
                        autofocus
                        class="w-full px-4 py-3 border-2 border-brand rounded-brand focus:outline-none focus:ring-2 focus:ring-cta @error('email') border-red-500 @enderror"
                    />
                </div>

                <div>
                    <label for="password" class="block text-sm font-semibold text-charcoal mb-2">
                        Kata Sandi
                    </label>
                    <input
                        type="password"
                        id="password"
                        name="password"
                        class="w-full px-4 py-3 border-2 border-brand rounded-brand focus:outline-none focus:ring-2 focus:ring-cta @error('password') border-red-500 @enderror"
                    />
                </div>

                <button
                    type="submit"
                    class="w-full bg-cta hover:bg-opacity-90 text-surface font-semibold py-3 px-6 rounded-brand transition duration-200"
                >
                    Masuk
                </button>
            </form>
        </div>
    </div>
</div>
@endsection
