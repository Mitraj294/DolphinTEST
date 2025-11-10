<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Country;
use App\Models\State;
use App\Models\City;
use App\Models\ReferralSource;
use Illuminate\Support\Facades\Log;

class LocationController extends Controller

{
    public function countries()
    {
        try {
            Log::info('[LocationController] Fetching countries');
            $countries = Country::orderBy('name')->get(['id', 'name']);
            Log::info('[LocationController] Countries fetched', ['count' => count($countries)]);
            return response()->json($countries);
        } catch (\Throwable $e) {
            Log::warning('[LocationController] failed to fetch countries', ['error' => $e->getMessage()]);
            return response()->json([], 200);
        }
    }

    public function states(Request $request)
    {
        $countryId = $request->query('country_id');
        try {
            Log::info('[LocationController] Fetching states for country', ['country_id' => $countryId]);

            if ($countryId !== null && !is_numeric($countryId)) {
                return response()->json(['error' => 'country_id must be an integer'], 422);
            }

            if ($countryId !== null) {
                $states = State::where('country_id', (int) $countryId)->orderBy('name')->get(['id', 'name']);
            } else {
                // return all states if country not specified
                $states = State::orderBy('name')->get(['id', 'name']);
            }

            Log::info('[LocationController] States fetched', ['count' => count($states)]);
            return response()->json($states);
        } catch (\Throwable $e) {
            Log::warning('[LocationController] failed to fetch states', ['error' => $e->getMessage()]);
            return response()->json([], 200);
        }
    }

    public function cities(Request $request)
    {
        $stateId = $request->query('state_id');
        try {
            Log::info('[LocationController] Fetching cities for state', ['state_id' => $stateId]);

            if ($stateId !== null && !is_numeric($stateId)) {
                return response()->json(['error' => 'state_id must be an integer'], 422);
            }

            if ($stateId !== null) {
                $cities = City::where('state_id', (int) $stateId)->orderBy('name')->get(['id', 'name']);
            } else {
                $cities = City::orderBy('name')->get(['id', 'name']);
            }

            Log::info('[LocationController] Cities fetched', ['count' => count($cities)]);
            return response()->json($cities);
        } catch (\Throwable $e) {
            Log::warning('[LocationController] failed to fetch cities', ['error' => $e->getMessage()]);
            return response()->json([], 200);
        }
    }

    public function getCountryById($id)
    {
        try {
            Log::info('[LocationController] Fetching country by id', ['id' => $id]);
            $country = Country::find($id);
            if ($country) {
                return response()->json(['id' => $country->id, 'name' => $country->name]);
            }
            return response()->json(['error' => 'Country not found'], 404);
        } catch (\Throwable $e) {
            Log::warning('[LocationController] failed to fetch country', ['id' => $id, 'error' => $e->getMessage()]);
            return response()->json(['error' => 'Country not found'], 404);
        }
    }

    public function getStateById($id)
    {
        try {
            Log::info('[LocationController] Fetching state by id', ['id' => $id]);
            $state = State::find($id);
            if ($state) {
                return response()->json(['id' => $state->id, 'name' => $state->name]);
            }
            return response()->json(['error' => 'State not found'], 404);
        } catch (\Throwable $e) {
            Log::warning('[LocationController] failed to fetch state', ['id' => $id, 'error' => $e->getMessage()]);
            return response()->json(['error' => 'State not found'], 404);
        }
    }

    public function getCityById($id)
    {
        try {
            Log::info('[LocationController] Fetching city by id', ['id' => $id]);
            $city = City::find($id);
            if ($city) {
                return response()->json(['id' => $city->id, 'name' => $city->name]);
            }
            return response()->json(['error' => 'City not found'], 404);
        } catch (\Throwable $e) {
            Log::warning('[LocationController] failed to fetch city', ['id' => $id, 'error' => $e->getMessage()]);
            return response()->json(['error' => 'City not found'], 404);
        }
    }

    public function referralSources()
    {
        try {
            Log::info('[LocationController] Fetching referral sources');
            // Order strictly by id to preserve canonical ordering user expects (no alphabetical reordering)
            $sources = ReferralSource::orderBy('id')->get(['id', 'name']);
            Log::info('[LocationController] Referral sources fetched', ['count' => count($sources)]);
            return response()->json($sources);
        } catch (\Throwable $e) {
            Log::warning('[LocationController] failed to fetch referral sources', ['error' => $e->getMessage()]);
            return response()->json([], 200);
        }
    }
}
