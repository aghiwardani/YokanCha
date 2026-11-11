@if ($errors->any())
    <div class="rounded-brand border border-charcoal bg-amber-soft px-5 py-4 text-sm leading-[22px] text-charcoal" role="alert">
        <ul class="flex flex-col gap-1">
            @foreach ($errors->all() as $error)
                <li>{{ $error }}</li>
            @endforeach
        </ul>
    </div>
@endif
