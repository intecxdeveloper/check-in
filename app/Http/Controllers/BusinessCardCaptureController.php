<?php

namespace App\Http\Controllers;

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
        $data = $request->validated();
        $path = BusinessCardImage::store($request->file('image'));

        if ($path === null) {
            return response()->json([
                'message' => 'That photo could not be read. Try again in better light.',
            ], 422);
        }

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

        $card = BusinessCard::query()->create([
            'user_id' => $session->user_id,
            'scan_session_id' => $session->id,
            'image_path' => $path,
            'full_name' => $fields['full_name'] ?: null,
            'job_title' => $fields['job_title'] ?: null,
            'company_name' => $fields['company_name'] ?: null,
            'email' => $fields['email'] ?: null,
            'phone' => $fields['phone'] ?: null,
            'website' => $fields['website'] ?: null,
            'address' => $fields['address'] ?: null,
            'ocr_text' => $ocrText !== '' ? $ocrText : null,
        ]);

        $session->forceFill(['last_seen_at' => now()])->save();

        return response()->json([
            'ok' => true,
            'card' => $card->toGalleryArray(),
        ], 201);
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
