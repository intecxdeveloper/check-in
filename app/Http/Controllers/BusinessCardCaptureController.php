<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreBusinessCardOcrRequest;
use App\Http\Requests\StoreCapturedBusinessCardRequest;
use App\Models\BusinessCard;
use App\Models\BusinessCardScanSession;
use App\Support\BusinessCardImage;
use App\Support\BusinessCardParser;
use Illuminate\Http\JsonResponse;
use Illuminate\View\View;

class BusinessCardCaptureController extends Controller
{
    public function show(string $token): View
    {
        $session = $this->usableSession($token);
        $session->forceFill(['last_seen_at' => now()])->save();

        return view('scan.capture', [
            'token' => $token,
            'storeUrl' => url('/scan/'.$token),
        ]);
    }

    public function store(StoreCapturedBusinessCardRequest $request, string $token): JsonResponse
    {
        $session = $this->usableSession($token);
        $encoded = $this->encodedImage($request);

        if ($encoded === null) {
            return response()->json([
                'message' => 'That photo could not be read. Try again in better light.',
            ], 422);
        }

        $card = BusinessCard::query()->create([
            'user_id' => $session->user_id,
            'scan_session_id' => $session->id,
            'image_path' => null,
            'image_base64' => $encoded['base64'],
            'image_mime' => $encoded['mime'],
        ]);

        $session->forceFill(['last_seen_at' => now()])->save();

        return response()->json([
            'ok' => true,
            'card' => $card->toGalleryArray(),
            'ocr_url' => url('/scan/'.$token.'/cards/'.$card->id),
        ], 201);
    }

    public function ocr(StoreBusinessCardOcrRequest $request, string $token, BusinessCard $businessCard): JsonResponse
    {
        $session = $this->usableSession($token);

        if ($businessCard->scan_session_id !== $session->id) {
            abort(404);
        }

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

        $businessCard->update([
            'full_name' => $fields['full_name'] ?: $businessCard->full_name,
            'job_title' => $fields['job_title'] ?: $businessCard->job_title,
            'company_name' => $fields['company_name'] ?: $businessCard->company_name,
            'email' => $fields['email'] ?: $businessCard->email,
            'phone' => $fields['phone'] ?: $businessCard->phone,
            'website' => $fields['website'] ?: $businessCard->website,
            'address' => $fields['address'] ?: $businessCard->address,
            'ocr_text' => $ocrText !== '' ? $ocrText : $businessCard->ocr_text,
        ]);

        return response()->json([
            'ok' => true,
            'card' => $businessCard->fresh()->toGalleryArray(),
        ]);
    }

    /**
     * @return array{base64: string, mime: string}|null
     */
    private function encodedImage(StoreCapturedBusinessCardRequest $request): ?array
    {
        if ($request->hasFile('image')) {
            return BusinessCardImage::fromUpload($request->file('image'));
        }

        $uri = trim((string) $request->input('image_base64', ''));

        return $uri !== '' ? BusinessCardImage::fromDataUri($uri) : null;
    }

    private function usableSession(string $token): BusinessCardScanSession
    {
        $session = BusinessCardScanSession::findActive($token);

        if ($session === null) {
            abort(404);
        }

        return $session;
    }
}
