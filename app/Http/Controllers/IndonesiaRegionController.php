<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * API endpoint untuk data wilayah Indonesia (laravolt/indonesia v4+).
 *
 * Skema laravolt/indonesia v4 menggunakan `code` sebagai foreign key:
 *   indonesia_provinces   : id, code (PK logis), name
 *   indonesia_cities      : id, code, province_code (FK → provinces.code), name
 *   indonesia_districts   : id, code, city_code (FK → cities.code), name
 *   indonesia_villages    : id, code, district_code (FK → districts.code), name
 *
 * Frontend memakai `code` (string) sebagai value select, bukan integer `id`.
 * Semua response di-cache 24 jam — data wilayah tidak berubah.
 */
class IndonesiaRegionController extends Controller
{
    /** GET /region/provinces */
    public function provinces(): JsonResponse
    {
        $data = Cache::remember('indonesia.provinces', 86400, fn () =>
            DB::table('indonesia_provinces')
                ->select('code as id', 'name')
                ->orderBy('name')
                ->get()
                ->toArray()
        );

        return response()->json($data);
    }

    /** GET /region/cities?province_id=CODE */
    public function cities(Request $request): JsonResponse
    {
        $code = trim($request->query('province_id', ''));

        if ($code === '') {
            return response()->json([]);
        }

        $data = Cache::remember("indonesia.cities.{$code}", 86400, fn () =>
            DB::table('indonesia_cities')
                ->select('code as id', 'name')
                ->where('province_code', $code)
                ->orderBy('name')
                ->get()
                ->toArray()
        );

        return response()->json($data);
    }

    /** GET /region/districts?city_id=CODE */
    public function districts(Request $request): JsonResponse
    {
        $code = trim($request->query('city_id', ''));

        if ($code === '') {
            return response()->json([]);
        }

        $data = Cache::remember("indonesia.districts.{$code}", 86400, fn () =>
            DB::table('indonesia_districts')
                ->select('code as id', 'name')
                ->where('city_code', $code)
                ->orderBy('name')
                ->get()
                ->toArray()
        );

        return response()->json($data);
    }

    /** GET /region/villages?district_id=CODE */
    public function villages(Request $request): JsonResponse
    {
        $code = trim($request->query('district_id', ''));

        if ($code === '') {
            return response()->json([]);
        }

        $data = Cache::remember("indonesia.villages.{$code}", 86400, fn () =>
            DB::table('indonesia_villages')
                ->select('code as id', 'name')
                ->where('district_code', $code)
                ->orderBy('name')
                ->get()
                ->toArray()
        );

        return response()->json($data);
    }
}
