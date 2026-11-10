<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\PoPeriod;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class PoPeriodController extends Controller
{
    public function index(): View
    {
        $periods = PoPeriod::orderByDesc('created_at')->get();

        return view('admin.periods.index', [
            'periods' => $periods,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'opens_at' => ['nullable', 'date'],
            'closes_at' => ['nullable', 'date'],
        ]);

        PoPeriod::create([
            'name' => $validated['name'],
            'opens_at' => $validated['opens_at'] ?? null,
            'closes_at' => $validated['closes_at'] ?? null,
            'status' => 'draft',
        ]);

        return redirect()->route('admin.periods')->with('status', 'Periode berhasil dibuat.');
    }

    public function open(PoPeriod $period): RedirectResponse
    {
        $period->update(['status' => 'open']);

        return redirect()->route('admin.periods')->with('status', 'Periode dibuka.');
    }

    public function close(PoPeriod $period): RedirectResponse
    {
        $period->update(['status' => 'closed']);

        return redirect()->route('admin.periods')->with('status', 'Periode ditutup.');
    }
}
