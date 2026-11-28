<?php

namespace SignageFlow\Hrms\Services;

use SignageFlow\Hrms\Models\Employee;
use SignageFlow\Hrms\Models\WorkLocation;

class GeofenceService
{
    /**
     * Calculate Haversine distance in meters between two GPS coordinates.
     */
    public function distanceMeters(float $lat1, float $lon1, float $lat2, float $lon2): float
    {
        $earthRadius = 6371000;
        $dLat = deg2rad($lat2 - $lat1);
        $dLon = deg2rad($lon2 - $lon1);

        $a = sin($dLat / 2) * sin($dLat / 2)
            + cos(deg2rad($lat1)) * cos(deg2rad($lat2))
            * sin($dLon / 2) * sin($dLon / 2);

        $c = 2 * atan2(sqrt($a), sqrt(1 - $a));

        return round($earthRadius * $c, 2);
    }

    /**
     * Evaluate employee GPS coordinates against assigned work locations.
     *
     * @return array{location: ?WorkLocation, distance_meters: ?float, is_out_of_geofence: bool, action: string, selfie_required: bool}
     */
    public function evaluate(Employee $employee, float $lat, float $lon, ?WorkLocation $preferredLocation = null): array
    {
        $locations = collect();
        if ($preferredLocation) {
            $locations->push($preferredLocation);
        } else {
            if ($employee->workLocation) {
                $locations->push($employee->workLocation);
            }
            $locations = $locations->merge($employee->assignedWorkLocations()->where('is_active', true)->get())->unique('id');
        }

        $locations = $locations->filter(fn (WorkLocation $loc) => $loc->is_active && $loc->latitude !== null && $loc->longitude !== null);

        if ($locations->isEmpty()) {
            $mode = $employee->geofence_mode === 'inherit' ? 'flag' : $employee->geofence_mode;

            return [
                'location' => $employee->workLocation,
                'distance_meters' => null,
                'is_out_of_geofence' => false,
                'action' => $mode === 'strict' ? 'allow' : ($mode === 'bypass' ? 'allow' : 'allow'),
                'selfie_required' => (bool) ($employee->selfie_required ?? $employee->workLocation?->require_selfie ?? false),
            ];
        }

        $nearestLocation = null;
        $nearestDistance = null;

        foreach ($locations as $loc) {
            $dist = $this->distanceMeters($lat, $lon, (float) $loc->latitude, (float) $loc->longitude);
            if ($nearestDistance === null || $dist < $nearestDistance) {
                $nearestDistance = $dist;
                $nearestLocation = $loc;
            }
        }

        $mode = $employee->geofence_mode !== 'inherit'
            ? $employee->geofence_mode
            : ($nearestLocation->geofence_mode ?? 'flag');

        $selfieRequired = $employee->selfie_required !== null
            ? (bool) $employee->selfie_required
            : (bool) ($nearestLocation->require_selfie ?? false);

        if ($mode === 'bypass' || $mode === 'disabled') {
            return [
                'location' => $nearestLocation,
                'distance_meters' => $nearestDistance,
                'is_out_of_geofence' => false,
                'action' => 'allow',
                'selfie_required' => $selfieRequired,
            ];
        }

        $outOfRange = $nearestDistance > (float) $nearestLocation->radius_meters;

        return [
            'location' => $nearestLocation,
            'distance_meters' => $nearestDistance,
            'is_out_of_geofence' => $outOfRange,
            'action' => $outOfRange ? ($mode === 'strict' ? 'block' : 'flag') : 'allow',
            'selfie_required' => $selfieRequired,
        ];
    }
}
