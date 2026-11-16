@extends('layouts.admin')

@section('title', 'Master Data - YokanCha')

@section('admin-content')
<div class="min-h-screen bg-oat py-12 px-4">
    <div class="max-w-5xl mx-auto space-y-8">
        <div>
            <h1 class="text-3xl font-bold text-charcoal">Master Data</h1>
            <nav class="mt-3 flex gap-4 text-sm font-semibold">
                <a href="#produk" class="text-brand underline">Produk</a>
                <a href="#bahan" class="text-brand underline">Bahan</a>
                <a href="#ukuran" class="text-brand underline">Ukuran</a>
            </nav>
        </div>

        @if (session('status'))
            <div class="p-4 bg-brand bg-opacity-10 border-2 border-brand rounded-brand text-charcoal text-sm">
                {{ session('status') }}
            </div>
        @endif

        @if (session('error'))
            <div class="p-4 bg-red-50 border-2 border-red-500 rounded-brand text-red-600 text-sm">
                {{ session('error') }}
            </div>
        @endif

        @include('admin.master._products')
        @include('admin.master._ingredients')
        @include('admin.master._sizes')
    </div>
</div>
@endsection
