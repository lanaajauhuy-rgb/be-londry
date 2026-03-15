<?php

namespace App\Http\Controllers;

use App\Models\Service;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

// ServiceController menangani CRUD untuk data layanan laundry.
// Contoh data service: "Cuci Kiloan", "Cuci Sepatu", "Dry Clean".
class ServiceController extends Controller
{
    public function index(): JsonResponse
    {
        return response()->json([
            'data' => Service::latest()->get(),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'code'            => ['required', 'string', 'max:7', 'unique:services,code'],
            'name'            => ['required', 'string', 'max:50'],
            // Rule::in([...]) = nilai yang dikirim HARUS salah satu dari array ini.
            // Kalau kirim 'per_hari' misalnya → langsung error 422.
            // Ini lebih aman dari cek manual if/else di controller.
            'pricing_model'   => ['required', Rule::in(['per_kg', 'per_item', 'flat'])],
            // 'numeric' = boleh integer atau decimal. Berbeda dengan 'integer' yang hanya bilangan bulat.
            'unit_price'      => ['required', 'numeric', 'min:0'],
            'estimated_hours' => ['nullable', 'integer', 'min:0'],
            'description'     => ['nullable', 'string'],
            // 'boolean' = hanya terima true/false atau 1/0.
            'is_active'       => ['required', 'boolean'],
        ]);

        $service = Service::create($validated);

        return response()->json([
            'message' => 'Service berhasil dibuat',
            'data'    => $service,
        ], 201);
    }

    public function show(Service $service): JsonResponse
    {
        return response()->json([
            'data' => $service,
        ]);
    }

    public function update(Request $request, Service $service): JsonResponse
    {
        $validated = $request->validate([
            'code' => [
                'required',
                'string',
                'max:7',
                // ignore($service->id) — sama seperti di CustomerController.
                // Supaya update kode yang sama tidak dianggap duplikat oleh dirinya sendiri.
                Rule::unique('services', 'code')->ignore($service->id),
            ],
            'name'            => ['required', 'string', 'max:50'],
            'pricing_model'   => ['required', Rule::in(['per_kg', 'per_item', 'flat'])],
            'unit_price'      => ['required', 'numeric', 'min:0'],
            'estimated_hours' => ['nullable', 'integer', 'min:0'],
            'description'     => ['nullable', 'string'],
            'is_active'       => ['required', 'boolean'],
        ]);

        $service->update($validated);

        return response()->json([
            'message' => 'Service berhasil diupdate',
            'data'    => $service,
        ]);
    }

    public function destroy(Service $service): JsonResponse
    {
        $service->delete();

        return response()->json([
            'message' => 'Service berhasil dihapus',
        ]);
    }
}
