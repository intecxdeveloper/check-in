<?php

namespace App\Http\Controllers\Staff;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreBusinessCardOcrRequest;
use App\Http\Requests\UpdateBusinessCardRequest;
use App\Models\BusinessCard;
use App\Models\BusinessCardScanSession;
use App\Support\BusinessCardImage;
use App\Support\BusinessCardParser;
use App\Support\PublicUrl;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;

class BusinessCardController extends Controller
{
    public function index(Request $request): View
    {
        [$session, $token] = $this->activeLink($request);
        $scanUrl = PublicUrl::to('/scan/'.$token);

        $cards = BusinessCard::query()
            ->orderByDesc('id')
            ->limit(48)
            ->get();

        return view('staff.cards', [
            'scanUrl' => $scanUrl,
            'scanExpiresAt' => $session->expires_at,
            'phoneCannotReach' => PublicUrl::hostIsLoopback($scanUrl),
            'cards' => $cards,
            'tableCards' => BusinessCard::query()->orderByDesc('id')->paginate(15),
        ]);
    }

    public function rotate(Request $request): RedirectResponse
    {
        $this->issueLink($request);

        return redirect()
            ->route('staff.cards')
            ->with('status', 'New phone QR is ready. The previous link no longer works.');
    }

    public function feed(): JsonResponse
    {
        $cards = BusinessCard::query()
            ->orderByDesc('id')
            ->limit(48)
            ->get();

        return response()->json([
            'cards' => $cards->map(fn (BusinessCard $card): array => $card->toGalleryArray())->values(),
            'latest_id' => $cards->max('id') ?? 0,
        ]);
    }

    public function update(UpdateBusinessCardRequest $request, BusinessCard $businessCard): RedirectResponse
    {
        $businessCard->update($request->safe()->only([
            'full_name',
            'job_title',
            'company_name',
            'email',
            'phone',
            'website',
            'address',
            'notes',
        ]));

        return back()->with('status', 'Card details saved.');
    }

    /**
     * Saves text read by the staff desk from a stored photo, without overwriting
     * fields staff already typed.
     */
    public function ocr(StoreBusinessCardOcrRequest $request, BusinessCard $businessCard): JsonResponse
    {
        $data = $request->validated();
        $ocrText = trim((string) ($data['ocr_text'] ?? ''));
        $fields = BusinessCardParser::fillBlanks([
            'full_name' => $data['full_name'] ?? null,
            'job_title' => $data['job_title'] ?? null,
            'company_name' => $data['company_name'] ?? null,
            'email' => $data['email'] ?? null,
            'phone' => $data['phone'] ?? null,
            'website' => $data['website'] ?? null,
            'address' => $data['address'] ?? null,
        ], $ocrText);

        $upright = filled($data['image_base64'] ?? null)
            ? BusinessCardImage::fromDataUri((string) $data['image_base64'])
            : null;

        if ($upright !== null) {
            $businessCard->fill([
                'image_base64' => $upright['base64'],
                'image_mime' => $upright['mime'],
            ]);
        }

        $businessCard->update([
            'full_name' => $businessCard->full_name ?: ($fields['full_name'] ?: null),
            'job_title' => $businessCard->job_title ?: ($fields['job_title'] ?: null),
            'company_name' => $businessCard->company_name ?: ($fields['company_name'] ?: null),
            'email' => $businessCard->email ?: ($fields['email'] ?: null),
            'phone' => $businessCard->phone ?: ($fields['phone'] ?: null),
            'website' => $businessCard->website ?: ($fields['website'] ?: null),
            'address' => $businessCard->address ?: ($fields['address'] ?: null),
            'ocr_text' => $ocrText !== '' ? $ocrText : $businessCard->ocr_text,
            'ocr_completed_at' => now(),
        ]);

        return response()->json([
            'ok' => true,
            'card' => $businessCard->fresh()->toGalleryArray(),
        ]);
    }

    public function image(BusinessCard $businessCard): Response
    {
        $binary = $businessCard->imageBinary();

        if ($binary === null) {
            abort(404);
        }

        return response($binary, 200, [
            'Content-Type' => $businessCard->image_mime ?: 'image/webp',
            'Cache-Control' => 'private, max-age=3600',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    public function destroy(BusinessCard $businessCard): RedirectResponse
    {
        $path = $businessCard->image_path;
        $businessCard->delete();

        if (filled($path)) {
            Storage::disk('public')->delete($path);
        }

        return back()->with('status', 'Business card removed.');
    }

    /**
     * @return array{0: BusinessCardScanSession, 1: string}
     */
    private function activeLink(Request $request): array
    {
        $token = $request->session()->get('business_card_scan_token');

        if (is_string($token)) {
            $session = BusinessCardScanSession::findActive($token);

            if ($session !== null && $session->user_id === $request->user()?->id) {
                return [$session, $token];
            }
        }

        return $this->issueLink($request);
    }

    /**
     * @return array{0: BusinessCardScanSession, 1: string}
     */
    private function issueLink(Request $request): array
    {
        [$session, $token] = BusinessCardScanSession::issueFor($request->user());
        $request->session()->put('business_card_scan_token', $token);

        return [$session, $token];
    }
}
