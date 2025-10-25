<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

class ScrapeDataGoogleMaps extends Controller
{
    //
    public function _invoke(Request $request){
        $key = env('GOOGLE_MAPS_API');
        $query = $request->get('query');

        $response = Http::withOptions(['verify' => false])
            ->withHeaders([
                'Content-Type' => 'application/json',
                'X-Goog-Api-Key' => $key,
                'X-Goog-FieldMask' => 'places.displayName,places.formattedAddress,places.priceLevel,places.googleMapsLinks'
            ])->post('https://places.googleapis.com/v1/places:searchText', [
                'textQuery' => $query,
            ]);

        return response()->json($response->json());
    }
}
