<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <title>Buku Resep YokanCha</title>
    <style>
        @page { margin: 22mm 18mm 20mm 18mm; }
        body { font-family: "DejaVu Sans", sans-serif; font-size: 10.5pt; color: #222521; line-height: 1.45; }
        h1, h2, h3 { margin: 0; font-weight: bold; }
        .muted { color: #5B5F56; }
        .brand { color: #4A6B4F; }
        .small { font-size: 8.5pt; }

        /* Cover */
        .cover { text-align: center; padding-top: 55mm; }
        .cover img { width: 120px; height: 120px; }
        .cover h1 { font-size: 30pt; margin-top: 14px; letter-spacing: 1px; }
        .cover .sub { font-size: 12pt; margin-top: 6px; }
        .toc { margin: 18mm auto 0; width: 70%; border-collapse: collapse; }
        .toc td { padding: 5px 0; border-bottom: 1px solid #E2DDCF; font-size: 10pt; }

        /* One product per page */
        .product { page-break-before: always; }
        .product-head { border-bottom: 2px solid #4A6B4F; padding-bottom: 8px; margin-bottom: 14px; }
        .product-head h2 { font-size: 18pt; }
        .tag { display: inline-block; padding: 2px 8px; border-radius: 10px; font-size: 8pt; font-weight: bold; }
        .tag-off { background: #EFEADC; color: #5B5F56; }
        .tag-on { background: #E1E9DE; color: #4A6B4F; }

        table.grid { width: 100%; border-collapse: collapse; margin-top: 6px; }
        table.grid th { background: #F8F6F0; text-align: left; font-size: 8.5pt; color: #5B5F56; padding: 6px 8px; border-bottom: 1px solid #E2DDCF; }
        table.grid td { padding: 6px 8px; border-bottom: 1px solid #EFEADC; }
        table.grid .num { text-align: right; white-space: nowrap; }
        .section { margin-top: 16px; }
        .section h3 { font-size: 11pt; margin-bottom: 2px; }
        .warn { margin-top: 10px; padding: 8px 10px; background: #F4E1CE; color: #9A5A25; font-size: 9pt; }
        .footer { position: fixed; bottom: -12mm; left: 0; right: 0; font-size: 8pt; color: #5B5F56; text-align: center; }
    </style>
</head>
<body>
@php
    $num = fn ($n) => rtrim(rtrim(number_format((float) $n, 2, ',', '.'), '0'), ',');
    $rp = fn ($n) => 'Rp'.number_format((int) $n, 0, ',', '.');
@endphp

<div class="footer">Buku Resep YokanCha · dicetak {{ $printedAt->translatedFormat('j F Y, H.i') }}</div>

{{-- Cover --}}
<div class="cover">
    @if ($logo)
        <img src="{{ $logo }}" alt="YokanCha">
    @endif
    <h1>YokanCha</h1>
    <p class="sub muted">Buku Resep · {{ $products->count() }} menu</p>

    @if ($products->isNotEmpty())
        <table class="toc">
            @foreach ($products as $i => $entry)
                <tr>
                    <td>{{ $i + 1 }}. {{ $entry['product']->name }}</td>
                    <td style="text-align: right;" class="muted small">
                        {{ $entry['product']->is_active ? implode(', ', array_filter($entry['sizes'], fn ($s) => $s !== '-')) : 'Nonaktif' }}
                    </td>
                </tr>
            @endforeach
        </table>
    @else
        <p class="muted" style="margin-top: 20mm;">Belum ada produk.</p>
    @endif
</div>

@foreach ($products as $entry)
    @php
        $product = $entry['product'];
        $sizes = $entry['sizes'];
    @endphp
    <div class="product">
        <div class="product-head">
            <h2>{{ $product->name }}</h2>
            <p style="margin: 4px 0 0;">
                <span class="tag {{ $product->is_active ? 'tag-on' : 'tag-off' }}">{{ $product->is_active ? 'Aktif' : 'Nonaktif' }}</span>
                @if ($product->tag)
                    <span class="muted small">&nbsp;{{ $product->tag }}</span>
                @endif
            </p>
            @if ($product->description)
                <p class="muted" style="margin: 6px 0 0;">{{ $product->description }}</p>
            @endif
        </div>

        {{-- Harga --}}
        <div class="section">
            <h3>Harga per botol</h3>
            <table class="grid">
                <tr>
                    @foreach ($sizes as $size)
                        <th class="num">{{ $size === '-' ? 'Harga' : $size }}</th>
                    @endforeach
                </tr>
                <tr>
                    @foreach ($sizes as $size)
                        <td class="num"><strong>{{ $rp($product->priceFor($size === '-' ? null : $size)) }}</strong></td>
                    @endforeach
                </tr>
            </table>
        </div>

        {{-- Resep --}}
        <div class="section">
            <h3>Resep per botol</h3>
            <p class="muted small" style="margin: 0;">Takaran untuk tingkat manis Normal (100%).</p>
            @if ($entry['rows'])
                <table class="grid">
                    <tr>
                        <th>Bahan</th>
                        @foreach ($sizes as $size)
                            <th class="num">{{ $size === '-' ? 'Takaran' : $size }}</th>
                        @endforeach
                    </tr>
                    @foreach ($entry['rows'] as $row)
                        <tr>
                            <td>{{ $row['name'] }}@if ($row['sugar']) <span class="muted small">(gula)</span>@endif</td>
                            @foreach ($sizes as $size)
                                <td class="num">
                                    {{ $row['amounts'][$size] === null ? '-' : $num($row['amounts'][$size]).' '.$row['unit'] }}
                                </td>
                            @endforeach
                        </tr>
                    @endforeach
                </table>
            @else
                <p class="warn">Resep menu ini belum diisi.</p>
            @endif

            @if ($entry['missing'])
                <p class="warn">Resep belum diisi untuk ukuran: {{ implode(', ', $entry['missing']) }}.</p>
            @endif
        </div>

        {{-- Gula per tingkat manis --}}
        @if ($entry['sugarRows'] && $entry['levels'])
            <div class="section">
                <h3>Gula per tingkat manis</h3>
                <p class="muted small" style="margin: 0;">Takaran gula dikali persen tingkat manis.</p>
                <table class="grid">
                    <tr>
                        <th>Tingkat manis</th>
                        <th>Bahan</th>
                        @foreach ($sizes as $size)
                            <th class="num">{{ $size === '-' ? 'Takaran' : $size }}</th>
                        @endforeach
                    </tr>
                    @foreach ($entry['levels'] as $level => $percent)
                        @foreach ($entry['sugarRows'] as $i => $row)
                            <tr>
                                <td>@if ($i === 0)<strong>{{ $level }}</strong> <span class="muted small">{{ $percent }}%</span>@endif</td>
                                <td>{{ $row['name'] }}</td>
                                @foreach ($sizes as $size)
                                    <td class="num">
                                        @if ($row['amounts'][$size] === null)
                                            -
                                        @elseif ($percent === 0)
                                            <span class="muted">tanpa</span>
                                        @else
                                            {{ $num(round($row['amounts'][$size] * $percent / 100, 2)) }} {{ $row['unit'] }}
                                        @endif
                                    </td>
                                @endforeach
                            </tr>
                        @endforeach
                    @endforeach
                </table>
            </div>
        @endif
    </div>
@endforeach
</body>
</html>
