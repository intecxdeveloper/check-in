<?php

namespace App\Http\Controllers\Staff;

use App\Http\Controllers\Controller;
use App\Models\Gift;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\View\View;

class GiftController extends Controller
{
    public function index(): View
    {
        return view('staff.gifts', [
            'gifts' => Gift::query()->orderBy('sort_order')->orderBy('id')->get(),
            'nextColor' => Gift::nextColor(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validatedGift($request);

        Gift::query()->create([
            'key' => Gift::uniqueKeyFromLabel($data['label']),
            'label' => $data['label'],
            'short' => $data['short'],
            'color' => $data['color'],
            'weight' => 1,
            'sort_order' => Gift::nextSortOrder(),
        ]);

        return back()->with('status', 'Gift added to the wheel. Reload the live map to see it.');
    }

    public function update(Request $request, Gift $gift): RedirectResponse
    {
        $data = $this->validatedGift($request, $gift);

        $gift->update([
            'label' => $data['label'],
            'short' => $data['short'],
            'color' => $data['color'],
            'sort_order' => $data['sort_order'],
        ]);

        return back()->with('status', 'Gift updated. Reload the live map to see it.');
    }

    public function destroy(Gift $gift): RedirectResponse
    {
        $gift->delete();

        return back()->with('status', 'Gift removed from the wheel. Reload the live map.');
    }

    public function loadStarter(): RedirectResponse
    {
        $added = Gift::loadStarterPrizes();

        $message = $added === 0
            ? 'Starter gifts are already on the wheel.'
            : $added.' starter '.Str::plural('gift', $added).' added to the wheel.';

        return back()->with('status', $message);
    }

    /**
     * @return array{label: string, short: string, color: string, sort_order: int}
     */
    private function validatedGift(Request $request, ?Gift $gift = null): array
    {
        $data = $request->validate([
            'label' => ['required', 'string', 'max:80'],
            'short' => ['nullable', 'string', 'max:16'],
            'color' => ['required', 'string', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'sort_order' => ['nullable', 'integer', 'min:1', 'max:99'],
        ]);

        $label = trim($data['label']);
        $short = trim((string) ($data['short'] ?? ''));
        if ($short === '') {
            $short = Str::limit($label, 12, '');
        }

        return [
            'label' => $label,
            'short' => $short,
            'color' => $data['color'],
            'sort_order' => (int) ($data['sort_order'] ?? $gift?->sort_order ?? Gift::nextSortOrder()),
        ];
    }
}
